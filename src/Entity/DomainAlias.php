<?php

namespace App\Entity;

use App\Repository\DomainAliasRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DomainAliasRepository::class)]
class DomainAlias
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'domainAliases')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Domain $domain_id = null;

    #[ORM\Column(length: 255)]
    private ?string $domain_name = null;

    #[ORM\Column]
    private ?bool $is_active = true;

    public function __toString(): string
    {
        return $this->domain_name;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDomainId(): ?Domain
    {
        return $this->domain_id;
    }

    public function setDomainId(?Domain $domain_id): static
    {
        $this->domain_id = $domain_id;

        return $this;
    }

    public function getDomainName(): ?string
    {
        return $this->domain_name;
    }

    public function setDomainName(string $domain_name): static
    {
        $this->domain_name = $domain_name;

        return $this;
    }

    public function getIsActive(): bool
    {
        return $this->is_active;
    }

    public function setActive(bool $is_active): static
    {
        $this->is_active = $is_active;

        return $this;
    }
}
