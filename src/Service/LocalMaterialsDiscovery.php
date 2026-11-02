<?php

namespace App\Service;

/** Searches only this project and the current user's Downloads, never other profiles. */
final class LocalMaterialsDiscovery
{
    public function __construct(private string $projectDir) {}

    /** @param list<string>|null $downloads Explicit directories are useful for deterministic local tests. */
    public function find(?array $downloads = null): ?string
    {
        $explicit = getenv('CHASE_MATERIALS_ZIP');
        if (is_string($explicit) && $explicit !== '' && $this->allowed($explicit)) return realpath($explicit) ?: $explicit;
        return $this->newest($downloads, '/^Unterlagen(?:_Co)?(?:\s*\(\d+\))?\.zip$/iD');
    }

    public function findCatalog(?array $downloads = null): ?string
    {
        $explicit = getenv('CHASE_CATALOG_PDF');
        if (is_string($explicit) && $explicit !== '' && $this->allowed($explicit)) return realpath($explicit) ?: $explicit;
        return $this->newest($downloads, '/^Werbekatalog(?:\s*\(\d+\))?\.pdf$/iD');
    }

    private function newest(?array $downloads, string $pattern): ?string
    {
        $directories = [$this->projectDir.'/var/materials', $this->projectDir.'/var/materials/incoming', $this->projectDir.'/var/materials/source'];
        $matches = [];
        foreach (array_unique([...$directories, ...($downloads ?? $this->currentDownloads())]) as $directory) {
            if (!is_dir($directory) || !is_readable($directory)) continue;
            foreach (new \DirectoryIterator($directory) as $file) if (preg_match($pattern, $file->getFilename()) && $this->allowed($file->getPathname())) $matches[] = $file->getPathname();
        }
        usort($matches, static fn (string $a, string $b): int => filemtime($b) <=> filemtime($a));
        return $matches[0] ?? null;
    }

    /** Convert an existing private extraction into a temporary private ZIP; no arbitrary directories. */
    public function archiveFromPrivateSource(): ?string
    {
        $source = $this->projectDir.'/var/materials/source';
        if (!is_dir($source) || is_link($source) || !class_exists(\ZipArchive::class)) return null;
        $files = []; $total = 0;
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($source, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if ($file->isLink()) throw new \RuntimeException('Die privaten Quelldateien enthalten eine Verknüpfung.');
            if (!$file->isFile()) continue;
            $relative = str_replace(DIRECTORY_SEPARATOR, '/', substr($file->getPathname(), strlen($source) + 1));
            $category = preg_replace('/^\d+\s*[-–]\s*/u', '', explode('/', $relative)[0]);
            if (!in_array($category, MaterialsImporter::CATEGORIES, true)) continue;
            $total += $file->getSize();
            if (count($files) >= 500 || $file->getSize() > 20 * 1024 * 1024 || $total > 100 * 1024 * 1024) throw new \RuntimeException('Die privaten Quelldateien überschreiten das Importlimit.');
            $files[$relative] = $file->getPathname();
        }
        if (!$files) return null;
        ksort($files, SORT_STRING);
        $archive = $this->projectDir.'/var/materials/auto-source.zip';
        if (is_link($archive)) throw new \RuntimeException('Der private Archivpfad ist ungültig.');
        $temporary = $archive.'.'.bin2hex(random_bytes(8)).'.tmp';
        $zip = new \ZipArchive();
        if ($zip->open($temporary, \ZipArchive::CREATE | \ZipArchive::EXCL) !== true) throw new \RuntimeException('Das private Archiv konnte nicht vorbereitet werden.');
        try {
            foreach ($files as $relative => $path) {
                if (!$zip->addFile($path, $relative)) throw new \RuntimeException('Eine private Quelldatei konnte nicht gelesen werden.');
                $zip->setMtimeName($relative, filemtime($path));
            }
        } catch (\Throwable $error) { $zip->close(); @unlink($temporary); throw $error; }
        if (!$zip->close()) { @unlink($temporary); throw new \RuntimeException('Das private Archiv konnte nicht abgeschlossen werden.'); }
        chmod($temporary, 0600);
        if (is_file($archive) && hash_file('sha256', $archive) === hash_file('sha256', $temporary)) { unlink($temporary); return $archive; }
        if (!rename($temporary, $archive)) { @unlink($temporary); throw new \RuntimeException('Das private Archiv konnte nicht gespeichert werden.'); }
        return $archive;
    }

    private function allowed(string $path): bool { return is_file($path) && is_readable($path) && !is_link($path) && filesize($path) <= 128 * 1024 * 1024; }

    private function currentDownloads(): array
    {
        $home = PHP_OS_FAMILY === 'Windows' ? (getenv('USERPROFILE') ?: getenv('HOME')) : getenv('HOME');
        $directories = $home ? [rtrim($home, '/\\').DIRECTORY_SEPARATOR.'Downloads'] : [];
        $isWsl = getenv('WSL_DISTRO_NAME') !== false || (is_file('/proc/sys/kernel/osrelease') && str_contains(strtolower((string) file_get_contents('/proc/sys/kernel/osrelease')), 'microsoft'));
        if ($isWsl && function_exists('proc_open')) {
            // Ask Windows for its CURRENT profile; never enumerate C:\Users.
            $profile = self::windowsProfileFromOutput($this->run(['cmd.exe', '/d', '/c', 'chcp 65001>nul & echo %USERPROFILE%']));
            if ($profile !== null) {
                $linux = trim($this->run(['wslpath', '-u', $profile]));
                if ($linux !== '' && str_starts_with($linux, '/') && !str_contains($linux, "\n")) $directories[] = rtrim($linux, '/').'/Downloads';
            }
        }
        return array_values(array_unique($directories));
    }

    /** CMD may emit a UNC working-directory warning before printing its actual profile. */
    public static function windowsProfileFromOutput(string $output): ?string
    {
        foreach (array_reverse(preg_split('/\r\n|\r|\n/', $output) ?: []) as $line) {
            $line = trim($line);
            if (preg_match('/^[A-Za-z]:\\\\[^\r\n%\x00-\x1f]+$/uD', $line)) return $line;
        }
        return null;
    }

    private function run(array $arguments): string
    {
        $process = @proc_open($arguments, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($process)) return '';
        fclose($pipes[0]); stream_set_blocking($pipes[1], false); stream_set_blocking($pipes[2], false);
        $result = ''; $started = microtime(true);
        do {
            $result .= stream_get_contents($pipes[1]) ?: ''; stream_get_contents($pipes[2]);
            $status = proc_get_status($process);
            if (microtime(true) - $started > 3 || strlen($result) > 4096) { proc_terminate($process); $result = ''; break; }
            if ($status['running']) usleep(10000);
        } while ($status['running']);
        fclose($pipes[1]); fclose($pipes[2]); proc_close($process);
        return $result;
    }
}
