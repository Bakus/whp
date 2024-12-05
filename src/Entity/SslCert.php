<?php

namespace App\Entity;

use App\Repository\SslCertRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SslCertRepository::class)]
class SslCert
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(length: 255)]
    private ?string $crt_file = null;

    #[ORM\Column(length: 255)]
    private ?string $key_file = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $ca_file = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $sni = null;

    #[ORM\Column]
    private ?bool $is_active = true;

    /**
     * @var Collection<int, Domain>
     */
    #[ORM\OneToMany(targetEntity: Domain::class, mappedBy: 'ssl_cert')]
    private Collection $domains;

    /**
     * @var Collection<int, IpAddress>
     */
    #[ORM\OneToMany(targetEntity: IpAddress::class, mappedBy: 'ssl_cert')]
    private Collection $ipAddresses;

    public function __construct()
    {
        $this->domains = new ArrayCollection();
        $this->ipAddresses = new ArrayCollection();
    }

    public function __toString(): string
    {
        return $this->name;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getCrtFile(): ?string
    {
        return $this->crt_file;
    }

    public function setCrtFile(string $crt_file): static
    {
        $this->crt_file = $crt_file;

        return $this;
    }

    public function getKeyFile(): ?string
    {
        return $this->key_file;
    }

    public function setKeyFile(string $key_file): static
    {
        $this->key_file = $key_file;

        return $this;
    }

    public function getCaFile(): ?string
    {
        return $this->ca_file;
    }

    public function setCaFile(?string $ca_file): static
    {
        $this->ca_file = $ca_file;

        return $this;
    }

    public function getSni(): ?string
    {
        return $this->sni;
    }

    public function setSni(?string $sni): static
    {
        $this->sni = $sni;

        return $this;
    }

    public function setIsActive(bool $is_active): static
    {
        $this->is_active = $is_active;

        return $this;
    }

    public function getIsActive(): ?bool
    {
        return $this->is_active;
    }

    /**
     * @return Collection<int, Domain>
     */
    public function getDomains(): Collection
    {
        return $this->domains;
    }

    public function addDomain(Domain $domain): static
    {
        if (!$this->domains->contains($domain)) {
            $this->domains->add($domain);
            $domain->setSslCert($this);
        }

        return $this;
    }

    public function removeDomain(Domain $domain): static
    {
        if ($this->domains->removeElement($domain)) {
            // set the owning side to null (unless already changed)
            if ($domain->getSslCert() === $this) {
                $domain->setSslCert(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, IpAddress>
     */
    public function getIpAddresses(): Collection
    {
        return $this->ipAddresses;
    }

    public function addIpAddress(IpAddress $ipAddress): static
    {
        if (!$this->ipAddresses->contains($ipAddress)) {
            $this->ipAddresses->add($ipAddress);
            $ipAddress->setSslCert($this);
        }

        return $this;
    }

    public function removeIpAddress(IpAddress $ipAddress): static
    {
        if ($this->ipAddresses->removeElement($ipAddress)) {
            // set the owning side to null (unless already changed)
            if ($ipAddress->getSslCert() === $this) {
                $ipAddress->setSslCert(null);
            }
        }

        return $this;
    }
}
