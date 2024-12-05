<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\User;
use App\Service\OsFunctionsService;

#[AsCommand(
    name: 'app:deploy-system-users',
    description: 'Add admin user to database',
)]
class DeploySystemUsersCommand extends Command
{
    public function __construct(
        protected EntityManagerInterface $entityManager,
        protected OsFunctionsService $osFunctions,
    )
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $users = $this->entityManager->getRepository(User::class)->findBy([
            'parent_user' => null, // only parent users can be system users; subusers will be virtual only
            'is_active' => true,
        ]);
        $usersToCreate = [];
        $io->info('Found ' . count($users) . ' active users. Checking if they can be created or they exists with valid settings...');
        foreach ($users as $user) {
            $uid = $this->osFunctions->checkSystemUserExists($user->getUsername());
            if ($uid !== false) {
                if ($uid !== $user->getUid()) {
                    $io->warning('User ' . $user->getUsername() . ' already exists with different UID.');
                    return Command::FAILURE;
                }
                $io->info('User ' . $user->getUsername() . ' exists with valid settings.');
                continue;
            }
            $io->info('User ' . $user->getUsername() . ' can be created.');
            $usersToCreate[] = $user;
        }

        if (empty($usersToCreate)) {
            $io->success('No users to create.');
            return Command::SUCCESS;
        }

        $io->info('Creating users...');
        foreach ($usersToCreate as $user) {
            try {
                $this->osFunctions->createSystemUser($user->getUsername(), $user->getUid(), $user->getHomeDir());
            } catch (\Exception $e) {
                $io->error('Error creating user ' . $user->getUsername() . ': ' . $e->getMessage());
                return Command::FAILURE;
            }
            $io->info('User ' . $user->getUsername() . ' created.');
        }

        $io->success('Users created successfully.');

        return Command::SUCCESS;
    }
}
