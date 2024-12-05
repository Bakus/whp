<?php

namespace App\Service;

use Doctrine\ORM\EntityManagerInterface;
use App\Entity\{Domain, IpAddress};

use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;

class ConfigEnablerService
{
    public function enableActiveConfigs(EntityManagerInterface $entityManager): void
    {
        $filesystem = new Filesystem();

        $repository = $entityManager->getRepository(IpAddress::class);
        $ipAddresses = $repository->findBy(
            ['is_active' => true],
        );
        foreach ($ipAddresses as $ip) {
            if ($ip->getIsActive() === false) {
                $filesystem->remove(
                    '/etc/apache2/sites-enabled/' . $ip->getSafeIpAddress() . '.conf',
                );
            }else{
                try {
                    $filesystem->symlink(
                        '/etc/apache2/sites-available/' . $ip->getSafeIpAddress() . '.conf',
                        '/etc/apache2/sites-enabled/' . $ip->getSafeIpAddress() . '.conf',
                    );
                } catch (IOException $exception) {
                    // Do nothing by design - symlink already exists
                }
            }
        }

        $php_pools_managed = [];
        $php_pools_active = [];
        $repository = $entityManager->getRepository(Domain::class);
        $domains = $repository->findBy(
            ['is_active' => true],
        );
        foreach ($domains as $domain) {
            foreach ($domain->getIpAddresses() as $ip) {
                $php_pools_managed[$domain->getPhpVersion() . $domain->getOwner()->getUsername()] =
                    '/etc/php/' . $domain->getPhpVersion() . '/fpm/pool.d/' . $domain->getOwner()->getUsername() . '.conf';

                if ($ip->getIsActive() === false) {
                    $filesystem->remove([
                        '/etc/apache2/sites-enabled/' . $ip->getSafeIpAddress() . '_' . $domain->getFqdn() . '.conf',
                    ]);
                    continue;
                }

                try {
                    $filesystem->symlink(
                        '/etc/apache2/sites-available/' . $ip->getSafeIpAddress() . '_' . $domain->getFqdn() . '.conf',
                        '/etc/apache2/sites-enabled/' . $ip->getSafeIpAddress() . '_' . $domain->getFqdn() . '.conf',
                    );
                } catch (IOException $exception) {
                    // Do nothing by design - symlink already exists
                }

                $php_pools_active[$domain->getPhpVersion() . $domain->getOwner()->getUsername()] =
                    '/etc/php/' . $domain->getPhpVersion() . '/fpm/pool.d/' . $domain->getOwner()->getUsername() . '.conf';
            }
        }

        $php_pools_todel = array_diff($php_pools_managed, $php_pools_active);
        foreach ($php_pools_todel as $pool) {
            $filesystem->remove($pool);
        }

        try {
            $filesystem->symlink(
                '/etc/apache2/conf-available/000_dirs.conf',
                '/etc/apache2/conf-enabled/000_dirs.conf',
            );
        } catch (IOException $exception) {
            // Do nothing by design - symlink already exists
        }
    }
}
