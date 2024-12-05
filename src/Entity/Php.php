<?php

namespace App\Entity;

use App\Repository\PhpRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PhpRepository::class)]
#[ORM\UniqueConstraint(
    name: 'version_user_idx',
    columns: ['version', 'user_id']
  )]
class Php
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 3)]
    private ?string $version = null;

    #[ORM\ManyToOne(inversedBy: 'phps')]
    private ?User $user = null;

    #[ORM\Column(length: 2)]
    private ?int $startServers = 2;

    #[ORM\Column(length: 2)]
    private ?int $maxChildren = 10;

    #[ORM\Column(length: 2)]
    private ?int $minSpare = 1;

    #[ORM\Column(length: 2)]
    private ?int $maxSpare = 3;

    #[ORM\Column(length: 4)]
    private ?int $uploadSize = 64;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $errorLog = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $slowLog = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $additionalConfig = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getVersion(): ?string
    {
        return $this->version;
    }

    public function setVersion(string $version): static
    {
        $this->version = $version;

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getStartServers(): ?int
    {
        return $this->startServers;
    }

    public function setStartServers(int $startServers): static
    {
        $this->startServers = $startServers;

        return $this;
    }

    public function getMaxChildren(): ?int
    {
        return $this->maxChildren;
    }

    public function setMaxChildren(int $maxChildren): static
    {
        $this->maxChildren = $maxChildren;

        return $this;
    }

    public function getMinSpare(): ?int
    {
        return $this->minSpare;
    }

    public function setMinSpare(int $minSpare): static
    {
        $this->minSpare = $minSpare;

        return $this;
    }

    public function getMaxSpare(): ?int
    {
        return $this->maxSpare;
    }

    public function setMaxSpare(int $maxSpare): static
    {
        $this->maxSpare = $maxSpare;

        return $this;
    }

    public function getUploadSize(): ?string
    {
        return $this->uploadSize;
    }

    public function setUploadSize(string $uploadSize): static
    {
        $this->uploadSize = $uploadSize;

        return $this;
    }

    public function getErrorLog(): ?string
    {
        return $this->errorLog;
    }

    public function setErrorLog(string $errorLog): static
    {
        $this->errorLog = $errorLog;

        return $this;
    }

    public function getSlowLog(): ?string
    {
        return $this->slowLog;
    }

    public function setSlowLog(?string $slowLog): static
    {
        $this->slowLog = $slowLog;

        return $this;
    }

    public function getAdditionalConfig(): ?string
    {
        return $this->additionalConfig;
    }

    public function setAdditionalConfig(?string $additionalConfig): static
    {
        $this->additionalConfig = $additionalConfig;

        return $this;
    }
}
