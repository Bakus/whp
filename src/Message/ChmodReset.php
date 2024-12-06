<?php

namespace App\Message;

class ChmodReset
{
    public function __construct(
        private string $username,
    )
    {
    }

    public function getUsername(): string
    {
        return $this->username;
    }
}
