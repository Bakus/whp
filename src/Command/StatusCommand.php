<?php

namespace App\Command;

use App\Service\OsFunctionsService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:status',
    description: 'Shows status of services and configuration files',
)]
class StatusCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private OsFunctionsService     $osFunctions,
    )
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $table = $io->createTable();
        $table->setHeaders(['Service', 'Enabled', 'Status']);
        $servicesStatus = $this->osFunctions->getServicesStatus();
        foreach ($servicesStatus as $service => $status) {
            $enabled = $status['enabled'] == "enabled" ? '<fg=green>enabled</>' : '<fg=red>disabled</>';
            $active = $this->osFunctions->isServiceActive($service);
            switch ($status['active']) {
                case 'active':
                    $active = '<fg=green>Active</>';
                    break;
                case 'inactive':
                    $active = '<fg=red>Inactive</>';
                    break;
                default:
                    $active = '<fg=red>' . $active . '</>';
                    break;
            }
            $table->addRow([
                str_replace('.service', '', $service),
                $enabled,
                $active,
            ]);
        }
        $table->render();

        return Command::SUCCESS;
    }
}
