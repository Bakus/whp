<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Question\Question;
use App\Entity\SystemUser;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:add-admin',
    description: 'Add admin user to database',
)]
class AddAdminCommand extends Command
{
    public function __construct(
        protected EntityManagerInterface $entityManager,
        protected UserPasswordHasherInterface $userPasswordHasher
    )
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        /**
         * @var Symfony\Component\Console\Question\Question
         */
        $helper = $this->getHelper('question');

        $question = new Question('Username:');
        $question->setValidator(function (string $answer): string {
            if (!is_string($answer) || !preg_match('/^([a-z0-9]+)$/', $answer)) {
                throw new \RuntimeException(
                    'Username need to be a string and can only contain letters and numbers.'
                );
            }
    
            return $answer;
        });
        $username = $helper->ask($input, $output, $question);

        $question = new Question('Password:');
        $question->setHidden(true);
        $question->setValidator(function (string $value): string {
            if ('' === trim($value)) {
                throw new \Exception('The password cannot be empty');
            }
            if (strlen($value) < 6) {
                throw new \Exception('The password must be at least 6 characters long');
            }
    
            return $value;
        });
        $plainPassword = $helper->ask($input, $output, $question);

        $systemUser = new SystemUser();
        $systemUser->setUsername($username);
        $systemUser->setPassword(
            $this->userPasswordHasher->hashPassword($systemUser, $plainPassword)
        );
        $systemUser->setRoles(['ROLE_ADMIN']);
        $this->entityManager->persist($systemUser);
        $this->entityManager->flush();

        $io->success('Done! Admin user added to database. Now you can login with the username and password you just created.');

        return Command::SUCCESS;
    }
}
