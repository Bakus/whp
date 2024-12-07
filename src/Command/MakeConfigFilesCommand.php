<?php

namespace App\Command;

use App\Service\{ConfigGeneratorService, OsFunctionsService};
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:make-config-files',
    description: 'Creates configuration files',
)]
class MakeConfigFilesCommand extends Command
{
    public function __construct(
        private ConfigGeneratorService $configGenerator,
        private EntityManagerInterface $entityManager,
        private OsFunctionsService $osFunctions,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $table = $io->createTable();
        $table->setHeaders(['File', 'Message']);

        $results = [];
        $io->info('Generating config files. This may take a while...');
        $files = $this->configGenerator->renderConfigFiles();
        $results['Clear old configs'] = [
            'status' => 'success',
            'content' => '',
            'message' => 'OK',
        ];
        try {
            $this->osFunctions->clearConfigs();
        } catch (Exception $e) {
            $results['Clear old configs']['status'] = 'error';
            $results['Clear old configs']['message'] = $e->getMessage();
        }
        foreach ($files as $file => $content) {
            $results[$file] = [
                'status' => 'success',
                'content' => $content,
                'message' => 'OK',
            ];

            try {
                $this->osFunctions->writeConfig($file, $content);
            } catch (Exception $e) {
                $results[$file]['status'] = 'error';
                $results[$file]['message'] = $e->getMessage();
            }
        }

        foreach ($results as $file => $contents) {
            $table->addRow([$file, $contents['message']]);
        }

        $table->render();

        $io->info('Restarting services...');
        $phps = $this->osFunctions->getPhpVersionsInstalled();
        $progressBar = new ProgressBar($output, count($phps) + 1);
        $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %elapsed:16s%/%estimated:-16s% %memory:6s% -- <comment>%message%</comment>');
        $progressBar->start();
        $progressBar->setMessage('Restarting Apache2');
        $this->osFunctions->restartService('apache2');
        $progressBar->advance();
        foreach ($phps as $version) {
            $progressBar->setMessage('Restarting PHP' . $version . '-FPM');
            $this->osFunctions->restartService('php' . $version . '-fpm');
            $progressBar->advance();
        }
        $progressBar->finish();
        $io->newLine();
        $io->success('All done!');

        return Command::SUCCESS;
    }
}
