<?php

namespace App\MessageHandler;

use App\Message\ChownReset;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\User;

use App\Service\OsFunctionsService;

#[AsMessageHandler]
class ChownResetHandler
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private OsFunctionsService $osFunctions,
    )
    {
    }

    public function __invoke(ChownReset $chownReset)
    {
        /**
         * @var User $user
         */
        $user = $this->entityManager
            ->getRepository(User::class)
            ->findOneBy([
                'username' => $chownReset->getUsername()
            ])
        ;
        $this->osFunctions->resetChown($user->getHomeDir(), $user->getUsername());

        $domain = $user->getDomains();
        foreach ($domain as $d) {
            if (substr($d->getWebroot(), 0, 1) === '/') {
                $this->osFunctions->resetChown($d->getWebroot(), $user->getUsername());
            }
        }
    }
}
