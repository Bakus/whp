<?php

namespace App\MessageHandler;

use App\Message\ServiceRestart;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

use App\Service\OsFunctionsService;

#[AsMessageHandler]
class ServiceRestartHandler
{
    public function __invoke(ServiceRestart $serviceRestart)
    {
        $osFunctions = new OsFunctionsService();
        $osFunctions->restartService($serviceRestart->getServiceName());
    }
}
