<?php

namespace App\MessageHandler;

use App\Message\ChmodReset;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\{Domain, User};

use App\Service\OsFunctionsService;

#[AsMessageHandler]
class ChmodResetHandler
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private OsFunctionsService $osFunctions,
    )
    {
    }

    public function __invoke(ChmodReset $chmodReset)
    {
        /**
         * @var User $user
         */
        $user = $this->entityManager
            ->getRepository(User::class)
            ->findOneBy([
                'username' => $chmodReset->getUsername()
            ])
        ;

        $homeDir = $user->getHomeDir();
        $this->osFunctions->resetChmod($homeDir);

        // as domains can lead to different directories,
        // we need to reset permissions for each domain separately
        $domain = $user->getDomains();
        foreach ($domain as $d) {
            if (substr($d->getWebroot(), 0, 1) === '/') {
                $this->osFunctions->resetChmod($d->getWebroot());
            }
        }

        // if there is .ssh directory in user's home directory,
        // reset its permissions to 0700 and files inside to 0600
        // to protect user's private keys
        $sshDir = OsFunctionsService::prettifyDirPath($user->getHomeDir() . '/.ssh/');
        if (is_dir($sshDir)) {
            $this->osFunctions->resetChmod($sshDir, '0700', '0600');
        }
    }
}
