<?php

namespace App\Message;

class ServiceRestart
{
    public function __construct(
        private string $serviceName,
    )
    {
    }

    public function getServiceName(): string
    {
        return $this->serviceName;
    }
}
