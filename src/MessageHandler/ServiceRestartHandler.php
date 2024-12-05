<?php

namespace App\MessageHandler;

use App\Message\ServiceRestart;
use App\Service\OsFunctionsService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class ServiceRestartHandler
{
    public function __invoke(ServiceRestart $serviceRestart)
    {
        $osFunctions = new OsFunctionsService();
        $osFunctions->restartService($serviceRestart->getServiceName());
    }
}
