<?php

namespace App\Command;

use App\Service\MaterialsImporter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:materials:import', description: 'Bereitgestellte Unterlagen aus einem lokalen ZIP sicher und idempotent in die private Bibliothek übernehmen.')]
final class ImportMaterialsCommand extends Command
{
    public function __construct(private MaterialsImporter $importer) { parent::__construct(); }

    protected function configure(): void
    {
        $this->addArgument('zip', InputArgument::REQUIRED, 'Absoluter oder relativer Pfad zum lokalen ZIP-Archiv.');
        $this->addOption('tenant', null, InputOption::VALUE_REQUIRED, 'Zielmandant; nur durch berechtigte lokale Administration ausführen.', 'chase-demo');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $result = $this->importer->import((string) $input->getArgument('zip'), (string) $input->getOption('tenant'));
            $output->writeln(sprintf('Unterlagen: %d neu, %d aktualisiert, %d unverändert, %d übersprungen.', $result['created'], $result['updated'], $result['unchanged'], $result['skipped']));
            if ($result['catalogId']) $output->writeln('Original-Werbekatalog: '.$result['catalogId']);
            if ($result['lexiconId']) $output->writeln('Original-Immobilienlexikon: '.$result['lexiconId']);
            $output->writeln('Originale liegen ausschließlich unter var/materials/library und sind nur nach Anmeldung und Rechteprüfung abrufbar.');
            return Command::SUCCESS;
        } catch (\Throwable $error) {
            $output->writeln('<error>Import abgebrochen: '.htmlspecialchars($error->getMessage(), ENT_QUOTES, 'UTF-8').'</error>');
            return Command::FAILURE;
        }
    }
}
