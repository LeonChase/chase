<?php

namespace App\Command;

use App\Entity\Document;
use App\Service\{LocalMaterialsDiscovery, MaterialsImporter};
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\{ArrayInput, InputInterface};
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:materials:auto', description: 'Vorhandene private CHASE-Unterlagen automatisch erkennen und bei Bedarf lokal übernehmen.')]
final class AutoMaterialsCommand extends Command
{
    public function __construct(private LocalMaterialsDiscovery $discovery, private MaterialsImporter $importer, private CatalogPreviewCommand $preview, private EntityManagerInterface $em, private string $projectDir) { parent::__construct(); }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $archive = $this->discovery->find() ?? $this->discovery->archiveFromPrivateSource();
            if ($archive === null) return Command::SUCCESS;
            $hash = hash_file('sha256', $archive);
            $mapping = $this->projectDir.'/data/catalog-products.json'; $mappingHash = is_file($mapping) ? hash_file('sha256', $mapping) : '';
            $stateFile = $this->projectDir.'/var/materials/auto-import.json';
            if (is_link($stateFile)) throw new \RuntimeException('Ungültiger privater Statuspfad.');
            $state = is_file($stateFile) ? json_decode((string) file_get_contents($stateFile), true) : [];
            $records = $this->em->getRepository(Document::class)->findBy(['tenantId' => 'chase-demo']);
            $current = ($state['archiveHash'] ?? '') === $hash && ($state['mappingHash'] ?? '') === $mappingHash && !empty($state['ids']);
            $byId = []; foreach ($records as $record) $byId[$record->id] = $record;
            if ($current) foreach ($state['ids'] as $id) if (!isset($byId[$id]) || !$this->importer->filePath($byId[$id])) { $current = false; break; }
            if (!$current) {
                $result = $this->importer->import($archive, 'chase-demo');
                $records = $this->em->getRepository(Document::class)->findBy(['tenantId' => 'chase-demo']);
                $archiveEntries = []; $zip = new \ZipArchive();
                if ($zip->open($archive, \ZipArchive::RDONLY) === true) { for ($i = 0; $i < $zip->numFiles; $i++) $archiveEntries[$zip->getNameIndex($i)] = true; $zip->close(); }
                $ids = []; foreach ($records as $record) if (isset($archiveEntries[$record->data['sourcePath'] ?? '']) && !empty($record->data['provided'])) $ids[] = $record->id;
                $state = ['archiveHash' => $hash, 'mappingHash' => $mappingHash, 'ids' => $ids, 'importedAt' => (new \DateTimeImmutable())->format(DATE_ATOM)];
                $temporary = $stateFile.'.'.bin2hex(random_bytes(6));
                file_put_contents($temporary, json_encode($state, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), LOCK_EX); chmod($temporary, 0600); rename($temporary, $stateFile);
                $output->writeln(sprintf('Unterlagen automatisch bereit: %d neu, %d aktualisiert, %d unverändert.', $result['created'], $result['updated'], $result['unchanged']));
            }
            foreach ($records as $record) if (!empty($record->data['catalogOriginal'])) {
                // The renderer itself skips an existing intact preview and never uses a network service.
                $this->preview->run(new ArrayInput(['--tenant' => 'chase-demo']), $output);
                break;
            }
        } catch (\Throwable $error) {
            // Missing/unusable optional material must never prevent application startup or erase data.
            $output->writeln('<comment>Die automatische Unterlagenübernahme wurde nicht abgeschlossen. Vorhandene Unterlagen bleiben erhalten. '.$error->getMessage().'</comment>');
        }
        return Command::SUCCESS;
    }
}
