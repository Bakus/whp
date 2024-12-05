<?php

namespace App\Entity;

use App\Repository\IpAddressRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: IpAddressRepository::class)]
class IpAddress
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 40)]
    private ?string $ip_address = null;

    #[ORM\Column(length: 255)]
    private ?string $webroot = null;

    #[ORM\ManyToOne(inversedBy: 'ipAddresses')]
    private ?SslCert $ssl_cert = null;

    #[ORM\Column]
    private ?bool $redirect_to_ssl = true;

    #[ORM\ManyToOne(inversedBy: 'ipAddresses')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $owner = null;

    #[ORM\Column(length: 3)]
    private ?string $php_version = null;

    #[ORM\Column]
    private ?bool $is_active = true;

    /**
     * @var Collection<int, Domain>
     */
    #[ORM\ManyToMany(targetEntity: Domain::class, mappedBy: 'ip_addresses')]
    private Collection $domains;

    public function __construct()
    {
        $this->domains = new ArrayCollection();
    }

    public function __toString(): string
    {
        return $this->ip_address;
    }

    public function getSafeIpAddress(): string
    {
        return str_replace(':', '-', $this->ip_address);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getIpAddress(): ?string
    {
        return $this->ip_address;
    }

    public function setIpAddress(string $ip_address): static
    {
        $this->ip_address = $ip_address;

        return $this;
    }

    public function getWebroot(): ?string
    {
        return $this->webroot;
    }

    public function setWebroot(string $webroot): static
    {
        $this->webroot = $webroot;

        return $this;
    }

    public function getSslCert(): ?SslCert
    {
        return $this->ssl_cert;
    }

    public function setSslCert(?SslCert $ssl_cert): static
    {
        $this->ssl_cert = $ssl_cert;

        return $this;
    }

    public function getRedirectToSsl(): ?bool
    {
        return $this->redirect_to_ssl;
    }

    public function setRedirectToSsl(bool $redirect_to_ssl): static
    {
        $this->redirect_to_ssl = $redirect_to_ssl;

        return $this;
    }

    public function getOwner(): ?User
    {
        return $this->owner;
    }

    public function setOwner(?User $owner): static
    {
        $this->owner = $owner;

        return $this;
    }

    public function getPhpVersion(): ?string
    {
        return $this->php_version;
    }

    public function setPhpVersion(string $php_version): static
    {
        $this->php_version = $php_version;

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
            $domain->addIpAddress($this);
        }

        return $this;
    }

    public function removeDomain(Domain $domain): static
    {
        if ($this->domains->removeElement($domain)) {
            $domain->removeIpAddress($this);
        }

        return $this;
    }
}
