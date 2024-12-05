<?php

namespace App\Entity;

use App\Repository\DomainRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DomainRepository::class), ORM\HasLifecycleCallbacks]
class Domain
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $fqdn = null;

    #[ORM\Column(length: 255)]
    private ?string $webroot = null;

    /**
     * @var Collection<int, IpAddress>
     */
    #[ORM\ManyToMany(targetEntity: IpAddress::class, inversedBy: 'domains')]
    private Collection $ip_addresses;

    #[ORM\ManyToOne(inversedBy: 'domains')]
    #[ORM\JoinColumn(nullable: false)]
    private ?SslCert $ssl_cert = null;

    #[ORM\Column]
    private ?bool $redirect_to_ssl = true;

    #[ORM\ManyToOne(inversedBy: 'domains')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $owner = null;

    #[ORM\Column(length: 3)]
    private ?string $php_version = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $custom_config = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $custom_config_ssl = null;

    #[ORM\Column]
    private ?bool $generateMtaSts = true;

    //Non mapped property
    private bool $haveGenerateMtaSts = false;

    #[ORM\Column]
    private ?bool $is_active = true;

    /**
     * @var Collection<int, DomainAlias>
     */
    #[ORM\OneToMany(targetEntity: DomainAlias::class, mappedBy: 'domain_id', orphanRemoval: true)]
    private Collection $domainAliases;

    #[ORM\Column(enumType: HttpStrictTransportSecurity::class)]
    private ?HttpStrictTransportSecurity $HttpStrictTransportSecurity = HttpStrictTransportSecurity::NO;

    public function __construct()
    {
        $this->ip_addresses = new ArrayCollection();
        $this->domainAliases = new ArrayCollection();
    }

    public function __toString(): string
    {
        return $this->fqdn;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFqdn(): ?string
    {
        return $this->fqdn;
    }

    public function setFqdn(string $fqdn): static
    {
        $this->fqdn = $fqdn;

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

    /**
     * @return Collection<int, IpAddress>
     */
    public function getIpAddresses(): Collection
    {
        return $this->ip_addresses;
    }

    public function addIpAddress(IpAddress $ipAddress): static
    {
        if (!$this->ip_addresses->contains($ipAddress)) {
            $this->ip_addresses->add($ipAddress);
        }

        return $this;
    }

    public function removeIpAddress(IpAddress $ipAddress): static
    {
        $this->ip_addresses->removeElement($ipAddress);

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

    public function getCustomConfig(): ?string
    {
        return $this->custom_config;
    }

    public function setCustomConfig(?string $custom_config): static
    {
        $this->custom_config = $custom_config;

        return $this;
    }

    public function getCustomConfigSsl(): ?string
    {
        return $this->custom_config_ssl;
    }

    public function setCustomConfigSsl(?string $custom_config_ssl): static
    {
        $this->custom_config_ssl = $custom_config_ssl;

        return $this;
    }

    public function getIsActive(): ?bool
    {
        return $this->is_active;
    }

    public function setIsActive(bool $is_active): static
    {
        $this->is_active = $is_active;

        return $this;
    }

    public function getGenerateMtaSts(): bool
    {
        return $this->generateMtaSts;
    }

    public function setGenerateMtaSts(bool $generateMtaSts): static
    {
        $this->generateMtaSts = $generateMtaSts;

        return $this;
    }

    /**
     * @ORM\PostLoad()
     */
    public function getHaveGenerateMtaSts()
    {
        $this->haveGenerateMtaSts = file_exists('/etc/apache2/sites-enabled/' . $this->ip_addresses->first()->getIpAddress() . '_600_mta-sts.' . $this->fqdn . '.conf');
        return $this->haveGenerateMtaSts;
    }

    /**
     * @return Collection<int, DomainAlias>
     */
    public function getDomainAliases(): Collection
    {
        return $this->domainAliases;
    }

    public function addDomainAlias(DomainAlias $domainAlias): static
    {
        if (!$this->domainAliases->contains($domainAlias)) {
            $this->domainAliases->add($domainAlias);
            $domainAlias->setDomainId($this);
        }

        return $this;
    }

    public function removeDomainAlias(DomainAlias $domainAlias): static
    {
        if ($this->domainAliases->removeElement($domainAlias)) {
            // set the owning side to null (unless already changed)
            if ($domainAlias->getDomainId() === $this) {
                $domainAlias->setDomainId(null);
            }
        }

        return $this;
    }

    public function getHttpStrictTransportSecurity(): HttpStrictTransportSecurity
    {
        return $this->HttpStrictTransportSecurity;
    }

    public function setHttpStrictTransportSecurity(HttpStrictTransportSecurity $HttpStrictTransportSecurity): static
    {
        $this->HttpStrictTransportSecurity = $HttpStrictTransportSecurity;

        return $this;
    }
}
