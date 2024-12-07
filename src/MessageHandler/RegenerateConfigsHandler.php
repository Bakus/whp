<?php

namespace App\MessageHandler;

use App\Message\RegenerateConfigs;
use App\Service\{ConfigGeneratorService, OsFunctionsService};
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Notifier\Message\SmsMessage;
use Symfony\Component\Notifier\TexterInterface;

#[AsMessageHandler]
class RegenerateConfigsHandler
{
    public function __construct(
        private ConfigGeneratorService $configGenerator,
        private EntityManagerInterface $entityManager,
        private OsFunctionsService $osFunctions,
        private TexterInterface $texter,
    ) {
    }

    public function __invoke(RegenerateConfigs $regenerateConfigs): void
    {
        $this->osFunctions->clearConfigs();

        $this->configGenerator->setCreateMtastsIfNeeded(true);
        $files = $this->configGenerator->renderConfigFiles();
        foreach ($files as $file => $content) {
            $this->osFunctions->writeConfig($file, $content);
        }

        // if restart will fail, we neeed to catch exception and send info to admin asap!
        try {
            $this->osFunctions->restartService('apache2');
            $phps = $this->osFunctions->getPhpVersionsInstalled();
            foreach ($phps as $version) {
                $this->osFunctions->restartService('php' . $version . '-fpm');
            }
        } catch (Exception $e) {
            $sms = new SmsMessage('+48693843399', 'Error while restarting services: ' . $e->getMessage());
            $this->texter->send($sms);
        }
    }
}
