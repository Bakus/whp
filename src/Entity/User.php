<?php

namespace App\Entity;

use App\Repository\UserRepository;
use DateTimeInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UserRepository::class)]
class User
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $username = null;

    #[ORM\Column(length: 255)]
    private ?string $password = null;

    #[ORM\Column]
    private ?int $uid = null;

    #[ORM\Column(length: 255)]
    private ?string $home_dir = null;

    #[ORM\Column]
    private ?bool $is_active = true;

    #[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'subUsers')]
    private ?self $parent_user = null;

    /**
     * @var Collection<int, self>
     */
    #[ORM\OneToMany(targetEntity: self::class, mappedBy: 'parent_user')]
    private Collection $subUsers;

    /**
     * @var Collection<int, Domain>
     */
    #[ORM\OneToMany(targetEntity: Domain::class, mappedBy: 'owner')]
    private Collection $domains;

    /**
     * @var Collection<int, IpAddress>
     */
    #[ORM\OneToMany(targetEntity: IpAddress::class, mappedBy: 'owner')]
    private Collection $ipAddresses;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $phpFpmConfigExtra = null;

    #[ORM\Column(type: Types::INTEGER, options: ['default' => 0])]
    private int $FtpLoginCount = 0;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?DateTimeInterface $FtpLastLogin = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?DateTimeInterface $FtpLastModified = null;

    /**
     * @var Collection<int, Php>
     */
    #[ORM\OneToMany(targetEntity: Php::class, mappedBy: 'user')]
    private Collection $phps;

    public function __construct()
    {
        $this->subUsers = new ArrayCollection();
        $this->domains = new ArrayCollection();
        $this->ipAddresses = new ArrayCollection();
        $this->phps = new ArrayCollection();
    }

    public function __toString(): string
    {
        return $this->username . ' [UID: ' . $this->uid . ']';
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function setUsername(string $username): static
    {
        $this->username = $username;

        return $this;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    public function getUid(): ?int
    {
        return $this->uid;
    }

    public function setUid(int $uid): static
    {
        $this->uid = $uid;

        return $this;
    }

    public function getHomeDir(): ?string
    {
        return $this->home_dir;
    }

    public function setHomeDir(string $home_dir): static
    {
        /**
         * @todo: Jeżeli jest parent_user to home_dir powinien być w formie /parent_user_home_dir/sub_user_home_dir
         */
        $this->home_dir = $home_dir;

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

    public function getParentUser(): ?self
    {
        return $this->parent_user;
    }

    public function setParentUser(?self $parent_user): static
    {
        $this->parent_user = $parent_user;

        return $this;
    }

    /**
     * @return Collection<int, self>
     */
    public function getSubUsers(): Collection
    {
        return $this->subUsers;
    }

    public function addSubUser(self $subUser): static
    {
        if (!$this->subUsers->contains($subUser)) {
            $this->subUsers->add($subUser);
            $subUser->setParentUser($this);
        }

        return $this;
    }

    public function removeSubUser(self $subUser): static
    {
        if ($this->subUsers->removeElement($subUser)) {
            // set the owning side to null (unless already changed)
            if ($subUser->getParentUser() === $this) {
                $subUser->setParentUser(null);
            }
        }

        return $this;
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
            $domain->setOwner($this);
        }

        return $this;
    }

    public function removeDomain(Domain $domain): static
    {
        if ($this->domains->removeElement($domain)) {
            // set the owning side to null (unless already changed)
            if ($domain->getOwner() === $this) {
                $domain->setOwner(null);
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
            $ipAddress->setOwner($this);
        }

        return $this;
    }

    public function removeIpAddress(IpAddress $ipAddress): static
    {
        if ($this->ipAddresses->removeElement($ipAddress)) {
            // set the owning side to null (unless already changed)
            if ($ipAddress->getOwner() === $this) {
                $ipAddress->setOwner(null);
            }
        }

        return $this;
    }

    public function getPhpFpmConfigExtra(): ?string
    {
        return $this->phpFpmConfigExtra;
    }

    public function setPhpFpmConfigExtra(?string $phpFpmConfigExtra): static
    {
        // trim and add new line at the end
        $phpFpmConfigExtra = trim($phpFpmConfigExtra) . PHP_EOL;
        $phpFpmConfigExtra = str_replace("\r\n", "\n", $phpFpmConfigExtra);

        // if only new line then set to null
        if ($phpFpmConfigExtra === PHP_EOL) {
            $phpFpmConfigExtra = null;
        }

        $this->phpFpmConfigExtra = $phpFpmConfigExtra;

        return $this;
    }

    public function getFtpLoginCount(): ?int
    {
        return $this->FtpLoginCount;
    }

    public function setFtpLoginCount(int $FtpLoginCount): static
    {
        $this->FtpLoginCount = $FtpLoginCount;

        return $this;
    }

    public function getFtpLastLogin(): ?DateTimeInterface
    {
        return $this->FtpLastLogin;
    }

    public function setFtpLastLogin(?DateTimeInterface $FtpLastLogin): static
    {
        $this->FtpLastLogin = $FtpLastLogin;

        return $this;
    }

    public function getFtpLastModified(): ?DateTimeInterface
    {
        return $this->FtpLastModified;
    }

    public function setFtpLastModified(?DateTimeInterface $FtpLastModified): static
    {
        $this->FtpLastModified = $FtpLastModified;

        return $this;
    }

    /**
     * @return Collection<int, Php>
     */
    public function getPhps(): Collection
    {
        return $this->phps;
    }

    public function addPhp(Php $php): static
    {
        if (!$this->phps->contains($php)) {
            $this->phps->add($php);
            $php->setUser($this);
        }

        return $this;
    }

    public function removePhp(Php $php): static
    {
        if ($this->phps->removeElement($php)) {
            // set the owning side to null (unless already changed)
            if ($php->getUser() === $this) {
                $php->setUser(null);
            }
        }

        return $this;
    }
}
