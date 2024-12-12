<?php

namespace App\Service;

use App\Entity\{Domain, HttpStrictTransportSecurity, IpAddress, Php, SslCert, User};
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Twig\Environment;

class ConfigGeneratorService
{
    protected array $files = [];
    /**
     * By default, MTA-STS file:
     * /home/panel/mta-sts/mta-sts.' . $fqdn . '/.well-known/mta-sts.txt
     * will not be created. If you want to create it, set this to true.
     */
    protected bool $createMtastsIfNeeded = false;

    public function __construct(
        private EntityManagerInterface $em,
        private Environment $twig,
        private OsFunctionsService $osFunctions,
        private DnsService $dnsService,
    ) {
    }

    public function setCreateMtastsIfNeeded(bool $createMtastsIfNeeded): void
    {
        $this->createMtastsIfNeeded = $createMtastsIfNeeded;
    }

    public function getCreateMtastsIfNeeded(): bool
    {
        return $this->createMtastsIfNeeded;
    }

    public function renderConfigFiles(): array
    {
        $this->files = [];
        $webroots = [
            '/home/panel/mta-sts/' => '/home/panel/mta-sts/',
        ];

        /**
         * @todo Check if options are disabled in /etc/proftpd/proftpd.conf:
         * Port 21D
         * efaultServer on
         */
        $this->files['/etc/proftpd/conf.d/00_whp.conf'] = $this->twig->render('configs/proftpd_main.twig', [
            'dbname' => $_ENV['PROFTPD_DBNAME'],
            'dbhost' => $_ENV['PROFTPD_DBHOST'],
            'dbuser' => $_ENV['PROFTPD_DBUSER'],
            'dbpass' => $_ENV['PROFTPD_DBPASS'],
        ]);

        $repository = $this->em->getRepository(IpAddress::class);
        $ipAddresses = $repository->findBy(
            ['is_active' => true],
        );
        foreach ($ipAddresses as $ip) {
            if ($ip->getOwner()->getIsActive() === false) {
                continue;
            }
            $webroot = OsFunctionsService::prettifyDirPath($ip->getWebroot() . '/');
            $webroots[$webroot] = $webroot;

            $this->files['/etc/proftpd/conf.d/50_' . $ip->getSafeIpAddress() . '.conf'] = $this->twig->render('configs/proftpd_ip.twig', [
                'ip' => $ip,
            ]);
            $this->files['/etc/apache2/sites-enabled/' . $ip->getSafeIpAddress() . '.conf'] = $this->twig->render('configs/apache_virtualhost_ip.twig', [
                'ip' => $ip,
                'webroot' => $webroot,
            ]);
            $this->files['/etc/php/' . $ip->getPhpVersion() . '/fpm/pool.d/' . $ip->getOwner()->getUsername() . '.conf'] = $this->twig->render('configs/php_fpm_pool.twig', [
                'data' => $ip,
                'config' => $this->getPhpConfig($ip->getPhpVersion(), $ip->getOwner()->getUsername()),
            ]);
        }

        $repository = $this->em->getRepository(Domain::class);
        $domains = $repository->findBy(
            ['is_active' => true],
        );
        foreach ($domains as $domain) {
            foreach ($domain->getIpAddresses() as $ip) {
                if ($ip->getIsActive() === false) {
                    continue;
                }
                if ($domain->getOwner()->getIsActive() === false) {
                    continue;
                }
                $domainWebroot = $domain->getWebroot();
                if (substr($domainWebroot, 0, 1) === '/') {
                    $webroot = $domainWebroot;
                } else {
                    // check parent user
                    if ($domain->getOwner()->getParentUser() !== null) {
                        $webroot = $domain->getOwner()->getParentUser()->getHomedir() . '/' . $domain->getWebroot();
                    } else {
                        $webroot = $domain->getOwner()->getHomedir() . '/' . $domain->getWebroot();
                    }
                }
                /**
                 * Priorities:
                 * 300 - default, domain without asterisk alias
                 * 600 - mta-sts
                 * 700 - domain with asterisk alias
                 */
                $priority = 300;
                foreach ($domain->getDomainAliases() as $alias) {
                    if (strpos($alias->getDomainName(), '*') !== false) {
                        $priority = 700;
                        break;
                    }
                }
                $webroot = OsFunctionsService::prettifyDirPath($webroot . '/');
                $hsts = '';
                if ($domain->getHttpStrictTransportSecurity() != HttpStrictTransportSecurity::NO) {
                    $hsts = 'Header always set Strict-Transport-Security "' . $domain->getHttpStrictTransportSecurity()->value . '"';
                }
                $this->files['/etc/apache2/sites-enabled/' . $ip->getSafeIpAddress() . '_' . $priority . '_' . $domain->getFqdn() . '.conf'] = $this->twig->render('configs/apache_virtualhost_domain.twig', [
                    'ip' => $ip,
                    'domain' => $domain,
                    'webroot' => $webroot,
                    'hsts' => $hsts,
                ]);

                if ($domain->getGenerateMtaSts()) {
                    // check MTA-STS - if found, then add virtualhost to serve https://mta-sts.DOMAIN/.well-known/mta-sts.txt
                    $mtaStsAliases = [];
                    foreach ($domain->getDomainAliases() as $alias) {
                        if (strpos($alias->getDomainName(), '*') === false) {
                            $mtaStsAliases[] = 'mta-sts.' . $alias->getDomainName();
                        }
                    }
                    $this->generateMtaSts($domain->getFqdn(), $ip, $domain->getSslCert(), $hsts, $mtaStsAliases);
                }

                $webroots[$webroot] = $webroot;
                $this->files['/etc/php/' . $domain->getPhpVersion() . '/fpm/pool.d/' . $domain->getOwner()->getUsername() . '.conf'] = $this->twig->render('configs/php_fpm_pool.twig', [
                    'data' => $domain,
                    'config' => $this->getPhpConfig($domain->getPhpVersion(), $domain->getOwner()->getUsername()),
                ]);
            }
        }

        $this->files['/etc/apache2/conf-enabled/000_dirs.conf'] = '';
        foreach ($webroots as $webroot) {
            $this->files['/etc/apache2/conf-enabled/000_dirs.conf'] .= $this->twig->render('configs/apache_directory.twig', [
                'webroot' => $webroot,
            ]);
        }

        $phpFpmConfigExtra = '';
        $user = $this->em->getRepository(User::class)
            ->findOneBy([
                'username' => 'www-data',
                'is_active' => true
            ]);
        if ($user !== null) {
            $phpFpmConfigExtra = $user->getPhpFpmConfigExtra();
        }
        $phpVersions = $this->osFunctions->getPhpVersionsInstalled();
        foreach ($phpVersions as $version) {
            $this->files['/etc/php/' . $version . '/fpm/pool.d/www-data.conf'] = $this->twig->render('configs/php_fpm_pool_www-data.twig', [
                'version' => $version,
                'config' => $this->getPhpConfig($version),
                'phpFpmConfigExtra' => $phpFpmConfigExtra,
            ]);
        }

        ksort($this->files);
        return $this->files;
    }

    protected function generateMtaSts(string $fqdn, IpAddress $ip, SslCert $sslCert, string $hsts = '', array $mtaStsAliases = []): void
    {
        try {
            $mx = $this->dnsService->queryDnsMx($fqdn);
            $d = $this->em->getRepository(Domain::class)
                ->findOneBy([
                    'fqdn' => 'mta-sts.' . $fqdn,
                ]);
            if ($d !== null) {
                // domain mta-sts declared - do not create mta-sts virtualhost
                $mx = false;
            }
        } catch (Exception $e) {
            $mx = false;
        }
        if ($mx) {
            $mtaSts = $this->dnsService->getMtaSts($fqdn);
            if ($mtaSts) {
                $mtaStsWebroot = '/home/panel/mta-sts/mta-sts.' . $fqdn;
                if ($this->createMtastsIfNeeded) {
                    $this->files[$mtaStsWebroot . '/.well-known/mta-sts.txt'] = $mtaSts;
                }

                // 600 priority - after all domains and before aliases
                $this->files['/etc/apache2/sites-enabled/' . $ip->getSafeIpAddress() . '_600_mta-sts.' . $fqdn . '.conf'] = $this->twig->render('configs/apache_virtualhost_mta_sts.twig', [
                    'ip' => $ip,
                    'domain' => 'mta-sts.' . $fqdn,
                    'aliases' => $mtaStsAliases,
                    'webroot' => $mtaStsWebroot,
                    'SslCert' => $sslCert,
                    'hsts' => $hsts,
                ]);
            }
        }
    }

    protected function getPhpConfig(string $version, string|null $user = null): Php
    {
        $repository = $this->em->getRepository(Php::class);
        $phpConfig = $repository->findOneBy([
            'version' => $version,
            'user' => $user,
        ]);
        if ($phpConfig === null) {
            $phpConfig = new Php();
            $phpConfig->setVersion($version);
            $phpConfig->setUser(null);
            $phpConfig->setStartServers(2);
            $phpConfig->setMaxChildren(5);
            $phpConfig->setMinSpare(1);
            $phpConfig->setMaxSpare(3);
            $phpConfig->setUploadSize(64);
        }
        return $phpConfig;
    }
}
