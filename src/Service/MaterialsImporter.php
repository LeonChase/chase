<?php

namespace App\Service;

use App\Entity\Document;
use App\Entity\AdvertisingProduct;
use App\Entity\Record;
use Doctrine\ORM\EntityManagerInterface;

/** Private, content-addressed storage. No ZIP path is ever extracted as a filesystem path. */
final class MaterialsImporter
{
    public const CATEGORIES = ['Start', 'Akquise & Kooperationen', 'Objektaufnahme & Bewertung', 'Verkauf', 'Vermietung', 'Marketing', 'Vorlagen', 'Links & Tools', 'Wissen & Rechtliches'];
    private const MAX_ARCHIVE = 134217728;
    private const MAX_FILE = 20971520;
    private const MAX_TOTAL = 104857600;
    private const MAX_ENTRIES = 500;
    private const EXTENSIONS = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'odt', 'ods', 'txt', 'md', 'csv', 'eml', 'url', 'html', 'htm', 'png', 'jpg', 'jpeg', 'webp', 'gif', 'mp3', 'wav', 'mp4', 'webm', 'psd', 'svg'];

    public function __construct(private EntityManagerInterface $em, private string $projectDir) {}

    /** @return array{created:int,updated:int,unchanged:int,skipped:int,total:int,catalogId:?string,lexiconId:?string} */
    public function import(string $archive, string $tenant = 'chase-demo'): array
    {
        if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]{0,79}$/D', $tenant)) {
            throw new \InvalidArgumentException('Die Mandantenkennung darf nur Buchstaben, Zahlen, Bindestriche und Unterstriche enthalten.');
        }
        if (!class_exists(\ZipArchive::class)) throw new \RuntimeException('Die lokale PHP-Erweiterung zip fehlt.');
        if (!is_file($archive) || is_link($archive) || filesize($archive) > self::MAX_ARCHIVE) {
            throw new \InvalidArgumentException('Die ZIP-Datei fehlt, ist verknüpft oder größer als 128 MiB.');
        }
        $zip = new \ZipArchive();
        if ($zip->open($archive, \ZipArchive::RDONLY | \ZipArchive::CHECKCONS) !== true) throw new \InvalidArgumentException('Das ZIP-Archiv konnte nicht gelesen werden.');
        try {
            // Validate the WHOLE archive before creating files or database entries.
            $entries = $this->validate($zip);
            $archiveHash = hash_file('sha256', $archive);
            $result = ['created' => 0, 'updated' => 0, 'unchanged' => 0, 'skipped' => 0, 'total' => count($entries), 'catalogId' => null, 'lexiconId' => null];
            foreach ($entries as $entry) {
                $source = $entry['name'];
                $extension = strtolower(pathinfo($source, PATHINFO_EXTENSION));
                if (!in_array($extension, self::EXTENSIONS, true)) { $result['skipped']++; continue; }
                $stream = $zip->getStream($source);
                if (!is_resource($stream)) throw new \RuntimeException('Eine Archivdatei konnte nicht gelesen werden.');
                $contents = stream_get_contents($stream, self::MAX_FILE + 1);
                fclose($stream);
                if ($contents === false || strlen($contents) !== $entry['size'] || hash('crc32b', $contents) !== sprintf('%08x', $entry['crc'])) throw new \RuntimeException('Dateigröße oder ZIP-Prüfung fehlgeschlagen.');
                $hash = hash('sha256', $contents);
                $relative = $this->write($contents, $extension, $tenant, $hash);
                $id = self::documentId($tenant, $source);
                $document = $this->em->find(Document::class, $id);
                if ($document && $document->tenantId !== $tenant) throw new \LogicException('Mandantenzuordnung ungültig.');
                $isNew = !$document;
                $now = (new \DateTimeImmutable('now', new \DateTimeZone('Europe/Berlin')))->format(DATE_ATOM);
                $fileName = basename($source);
                $title = str_replace('_', ' ', pathinfo($fileName, PATHINFO_FILENAME));
                [$category, $subfolder] = $this->classify($source);
                $isCatalog = mb_strtolower($fileName) === 'werbekatalog.pdf';
                $isLexicon = str_contains(mb_strtolower($fileName), 'immobilienlexikon') && $extension === 'pdf';
                if ($isCatalog) { $result['catalogId'] = $id; $this->importCatalogProducts($tenant, $id, $source, $hash); }
                if ($isLexicon) $result['lexiconId'] = $id;
                if ($document && ($document->data['sourceHash'] ?? '') === $hash && $this->filePath($document) !== null) { $result['unchanged']++; continue; }
                $mime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($contents) ?: 'application/octet-stream';
                $data = [
                    'title' => $title, 'category' => $category, 'subfolder' => $subfolder,
                    'description' => 'Bereitgestellte CHASE-Unterlage · fachliche Freigabe vor Verwendung prüfen.',
                    'format' => strtoupper($extension), 'fileName' => $fileName, 'filePath' => $relative,
                    'size' => strlen($contents), 'mimeType' => $mime, 'sourcePath' => $source,
                    'sourceHash' => $hash, 'sourceArchive' => basename($archive), 'sourceArchiveHash' => $archiveHash,
                    'sourceModifiedAt' => isset($entry['mtime']) ? date(DATE_ATOM, $entry['mtime']) : null,
                    'importedAt' => $document?->data['importedAt'] ?? $now, 'uploadedAt' => $now,
                    'lastUpdated' => substr($now, 0, 10), 'audience' => 'Alle Makler',
                    'provided' => true, 'isDemo' => false, 'catalogOriginal' => $isCatalog, 'lexiconOriginal' => $isLexicon,
                    'searchText' => $this->extractText($this->library().'/'.$relative, $extension, $contents),
                ];
                if ($extension === 'url' && preg_match('/^URL\s*=\s*(https?:\/\/[^\r\n]+)$/mi', $contents, $match)) {
                    $url = trim($match[1]);
                    if (filter_var($url, FILTER_VALIDATE_URL)) $data['linkUrl'] = $url;
                }
                if (!$document) { $document = new Document($data, null, $tenant); $document->id = $id; }
                else { $document->data = $data; $document->title = $title; $document->updatedAt = new \DateTimeImmutable(); }
                $this->em->persist($document);
                $result[$isNew ? 'created' : 'updated']++;
            }
            $this->em->flush();
            return $result;
        } finally { $zip->close(); }
    }

    public static function documentId(string $tenant, string $sourcePath): string
    {
        return 'material-'.substr(hash('sha256', $tenant."\0".$sourcePath), 0, 40);
    }

    /** Resolve only an intact file inside this tenant's private library. */
    public function filePath(Record $record): ?string
    {
        $relative = (string) ($record->data['filePath'] ?? '');
        $prefix = substr(hash('sha256', $record->tenantId), 0, 16).'/';
        if (!str_starts_with($relative, $prefix) || !preg_match('/^[a-f0-9]{16}\/[a-f0-9]{64}\.[a-z0-9]{1,8}$/D', $relative)) return null;
        $base = realpath($this->library());
        $path = realpath($this->library().'/'.$relative);
        if (!$base || !$path || !str_starts_with($path, $base.DIRECTORY_SEPARATOR) || !is_file($path) || is_link($this->library().'/'.$relative)) return null;
        $expected = (string) ($record->data['sourceHash'] ?? '');
        if (!preg_match('/^[a-f0-9]{64}$/D', $expected) || !hash_equals($expected, hash_file('sha256', $path))) return null;
        return $path;
    }

    /** A local admin upload uses the same tenant-bound private storage and checksum rules. */
    public function storeUploadedFile(string $path, string $extension, string $tenant): array
    {
        if (!is_file($path) || is_link($path) || filesize($path) > self::MAX_FILE || !in_array($extension, self::EXTENSIONS, true)) throw new \InvalidArgumentException('Datei oder Größe ungültig.');
        $contents = file_get_contents($path);
        if ($contents === false) throw new \RuntimeException('Datei konnte nicht gelesen werden.');
        $hash = hash('sha256', $contents);
        return ['filePath' => $this->write($contents, $extension, $tenant, $hash), 'sourceHash' => $hash, 'size' => strlen($contents)];
    }

    private function library(): string { return $this->projectDir.'/var/materials/library'; }

    private function write(string $contents, string $extension, string $tenant, string $hash): string
    {
        $base = $this->library();
        if (is_link($base)) throw new \RuntimeException('Das private Materialverzeichnis darf keine Verknüpfung sein.');
        if (!is_dir($base) && !mkdir($base, 0700, true) && !is_dir($base)) throw new \RuntimeException('Das Materialverzeichnis ist nicht beschreibbar.');
        $tenantFolder = substr(hash('sha256', $tenant), 0, 16);
        $directory = $base.'/'.$tenantFolder;
        if (is_link($directory)) throw new \RuntimeException('Ungültiges Mandantenverzeichnis.');
        if (!is_dir($directory) && !mkdir($directory, 0700) && !is_dir($directory)) throw new \RuntimeException('Das Mandantenverzeichnis ist nicht beschreibbar.');
        $path = $directory.'/'.$hash.'.'.$extension;
        if (is_link($path)) throw new \RuntimeException('Ungültiger Dateipfad.');
        if (is_file($path)) {
            if (!hash_equals($hash, hash_file('sha256', $path))) throw new \RuntimeException('Integritätsprüfung der gespeicherten Datei fehlgeschlagen.');
        } else {
            $temporary = $directory.'/.part-'.bin2hex(random_bytes(12));
            if (file_put_contents($temporary, $contents, LOCK_EX) !== strlen($contents)) throw new \RuntimeException('Die Datei konnte nicht vollständig gespeichert werden.');
            chmod($temporary, 0600);
            if (!rename($temporary, $path)) { @unlink($temporary); throw new \RuntimeException('Die Datei konnte nicht abgelegt werden.'); }
        }
        return $tenantFolder.'/'.$hash.'.'.$extension;
    }

    private function validate(\ZipArchive $zip): array
    {
        if ($zip->numFiles > self::MAX_ENTRIES) throw new \InvalidArgumentException('Zu viele Archiveinträge (höchstens 500).');
        $entries = []; $seen = []; $total = 0;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            if (!$stat) throw new \InvalidArgumentException('Ungültiger Archiveintrag.');
            $name = $stat['name'];
            $normal = str_replace('\\', '/', $name);
            if ($normal !== $name || strlen($name) > 600 || preg_match('/[\x00-\x1f\x7f]/', $name) || str_starts_with($name, '/') || preg_match('/^[A-Za-z]:/', $name)) throw new \InvalidArgumentException('Unsicherer Dateipfad im ZIP.');
            $segments = explode('/', rtrim($normal, '/'));
            if (array_intersect($segments, ['..', '.', '']) || isset($seen[$normal])) throw new \InvalidArgumentException('Ungültiger oder doppelter Dateipfad im ZIP.');
            $seen[$normal] = true;
            if (!$zip->getExternalAttributesIndex($i, $opsys, $attributes)) throw new \InvalidArgumentException('Ungültige ZIP-Dateiattribute.');
            $fileType = ($attributes >> 16) & 0170000;
            if ($fileType !== 0 && $fileType !== 0100000 && $fileType !== 0040000) throw new \InvalidArgumentException('Verknüpfungen und Spezialdateien sind im ZIP nicht erlaubt.');
            if (($stat['encryption_method'] ?? 0) !== 0) throw new \InvalidArgumentException('Verschlüsselte ZIP-Dateien werden nicht importiert.');
            if (str_ends_with($normal, '/')) continue;
            $size = (int) $stat['size']; $compressed = max(1, (int) ($stat['comp_size'] ?? $size));
            $total += $size;
            if ($size > self::MAX_FILE || $size < 0 || $total > self::MAX_TOTAL || ($size > 1048576 && $size / $compressed > 250)) throw new \InvalidArgumentException('ZIP-Sicherheitslimit überschritten (20 MiB je Datei, 100 MiB insgesamt, Kompressionsschutz).');
            if (str_starts_with($normal, '__MACOSX/') || basename($normal) === '.DS_Store') continue;
            $entries[] = $stat;
        }
        return $entries;
    }

    private function classify(string $source): array
    {
        $segments = explode('/', $source);
        $first = preg_replace('/^\d+\s*[-–]\s*/u', '', $segments[0]);
        $category = in_array($first, self::CATEGORIES, true) ? $first : 'Vorlagen';
        array_shift($segments); array_pop($segments);
        return [$category, implode(' / ', $segments)];
    }

    private function importCatalogProducts(string $tenant, string $documentId, string $source, string $hash): void
    {
        $mapping = $this->projectDir.'/data/catalog-products.json';
        if (!is_file($mapping)) return;
        $definitions = json_decode(file_get_contents($mapping), true, 512, JSON_THROW_ON_ERROR);
        foreach ($definitions['products'] ?? $definitions as $index => $row) {
            if (!is_array($row) || empty($row['title']) || (int) ($row['catalogPage'] ?? 0) < 1) continue;
            $id = 'catalog-'.substr(hash('sha256', $tenant."\0".$source."\0".($row['id'] ?? $row['title'])), 0, 40);
            $data = array_merge($row, [
                'title' => $row['title'], 'sourceDocumentId' => $documentId, 'sourcePath' => $source, 'sourceHash' => $hash,
                'provided' => true, 'isDemo' => false, 'priceLabel' => $row['priceLabel'] ?? 'Preis auf Anfrage',
                'status' => 'Lokaler Anfrageentwurf · nicht bestellt', 'catalogPage' => (int) $row['catalogPage'],
            ]);
            $record = $this->em->find(AdvertisingProduct::class, $id);
            if ($record && $record->data === $data) continue;
            if (!$record) { $record = new AdvertisingProduct($data, null, $tenant); $record->id = $id; }
            else { $record->data = $data; $record->title = $row['title']; $record->updatedAt = new \DateTimeImmutable(); }
            $this->em->persist($record);
        }
    }

    /** Read-only local extraction; embedded HTML/scripts/prompts are never executed. */
    private function extractText(string $path, string $extension, string $contents): string
    {
        if ($extension === 'pdf' && function_exists('proc_open')) {
            $process = @proc_open(['pdftotext', '-layout', '-enc', 'UTF-8', $path, '-'], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
            if (is_resource($process)) {
                fclose($pipes[0]); stream_set_blocking($pipes[1], false); stream_set_blocking($pipes[2], false);
                $text = ''; $started = microtime(true);
                do {
                    $text .= stream_get_contents($pipes[1], max(1, 524288 - strlen($text))) ?: '';
                    stream_get_contents($pipes[2]);
                    $status = proc_get_status($process);
                    if (strlen($text) >= 524288 || microtime(true) - $started > 10) { proc_terminate($process); break; }
                    if ($status['running']) usleep(10000);
                } while ($status['running']);
                fclose($pipes[1]); fclose($pipes[2]); proc_close($process);
                return trim(mb_substr($text, 0, 200000));
            }
        }
        if ($extension === 'docx') {
            $document = new \ZipArchive();
            if ($document->open($path, \ZipArchive::RDONLY) === true) {
                $stat = $document->statName('word/document.xml');
                $xml = $stat && $stat['size'] <= 2097152 ? $document->getFromName('word/document.xml') : '';
                $document->close();
                return trim(mb_substr(html_entity_decode(strip_tags(str_replace('</w:p>', "\n", $xml ?: '')), ENT_QUOTES | ENT_XML1, 'UTF-8'), 0, 200000));
            }
        }
        if (in_array($extension, ['txt', 'md', 'csv', 'url', 'eml', 'html', 'htm'], true)) {
            $text = substr($contents, 0, 524288);
            if (in_array($extension, ['html', 'htm'], true)) $text = html_entity_decode(strip_tags(preg_replace('/<(script|style)\b[^>]*>.*?<\/\1>/is', '', $text)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if ($extension === 'eml') $text = quoted_printable_decode($text);
            return trim(mb_substr(mb_convert_encoding($text, 'UTF-8', 'UTF-8'), 0, 200000));
        }
        return '';
    }
}
