# IntraDom Network Web Hosting Panel

IDN WHP is a simple administration panel build with Symfony and EasyAdmin to manage the configuration of virtual hosts in [Apache](https://httpd.apache.org/) on [Debian](https://www.debian.org/) and [Ubuntu](https://ubuntu.com/) (latest stable releases recommended).

It supports multiple:
* domains and aliases,
* [PHP](https://www.php.net/) versions,
* system users (with isolation in Apache thanks to [MPM-ITK](http://mpm-itk.sesse.net/)),
* IP addresses,
* SSL certificates (no built-in renewal support).

With console commands, it helps to configure repositories and install packages.

In addition, the database is tuned to support virtual accounts via [ProFTPd](http://www.proftpd.org/) - to be improved in the future…

## Installation
Minimal required PHP version for start is 8.2 - this can be installed from system repository.
After basic installation you can run connamd to install `ppa:ondrej/php` / `sury.org` repository and all provided PHP versions. More informations in section [Commands for `symfony console`](#appinstall-system-software)

```
git clone ...
cd whp/
composer install
```

## Update Symfony
```
git pull
composer update "symfony/*" --with-all-dependencies
```

## Basic, local usage
```
symfony server:start
```

## Deploy to PROD
```
export APP_ENV=prod
export APP_DEBUG=0
composer install --no-dev --optimize-autoloader
php bin/console doctrine:migrations:migrate --no-interaction --all-or-nothing
php bin/console asset-map:compile
php bin/console messenger:stop-workers
```

## Commands for `symfony console`:
```
  app:add-admin               Add admin user to database
  app:deploy-system-users     Add admin user to database
  app:import-certs            Import SSL Certificates into the database.
                              Existing certificates will be skipped.
  app:install-system-software Installs PHP repository, selected PHP interpreters
                              (Debian/Ubuntu ONLY!) and additional software
                              such as Apache2, ProFTPd, etc.
  app:make-config-files       Creates configuration files
  app:status                  Shows status of services and configuration files
```

### app:install-system-software
This comman will install:
* Apache2 with MPM-ITK worker
* PHP Repository (`ppa:ondrej/php` / `sury.org` depending on operating system version)
* Selected PHP-FPM versions with extensions (bcmath, bz2, common, curl, gd, gmp, gnupg, imagick, imap, intl, json, lz4, mbstring, mcrypt, mysql, opcache, pgsql, readline, soap, sqlite3, xml, zip)
* ProFTPd
* [cloudflared](https://github.com/cloudflare/cloudflared) (optional)


## ProFTPd configuration
> [!NOTE]
> Small manual configuration have to be done!

In file `/etc/proftpd/proftpd.conf` please confirm that:
```
DefaultServer off
Port 0
```
