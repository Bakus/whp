# IntraDom Network Web Hosting Panel

IDN WHP is a simple administration panel build with Symfony and EasyAdmin to manage the configuration of virtual hosts in [Apache](https://httpd.apache.org/) on [Debian](https://www.debian.org/) and [Ubuntu](https://ubuntu.com/) (latest stable releases recommended).

It supports multiple:
* domains and aliases,
* [PHP](https://www.php.net/) versions,
* system users (with isolation in Apache thanks to [MPM-ITK](http://mpm-itk.sesse.net/)),
* IP addresses,
* SSL certificates (no built-in renewal support).

With console commands, it helps to configure repositories and install packages.

In addition, the database is tuned to support virtual accounts via [ProFTPd](http://www.proftpd.org/) (but manual configuration of ProFTPd is required) - to be improved in the future…

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
> It is likely that ProFTPd may be replaced by another server in the future.

In addition, to make ProFTPd working - create file `/etc/proftpd/conf.d/whp.conf` with content:
```
LoadModule mod_tls.c
LoadModule mod_sql.c
LoadModule mod_sql_mysql.c
LoadModule mod_sql_passwd.c
LoadModule mod_sftp.c
LoadModule mod_sftp_sql.c

DefaultRoot ~
Umask 027 027

UseLastlog                      on

<IfModule mod_delay.c>
    DelayEngine                 on
</IfModule>

<IfModule mod_tls.c>
    TLSEngine                   on
    TLSLog                      /var/log/proftpd/tls.log
    TLSProtocol                 TLSv1.2 TLSv1.3
    TLSRequired                 off
    TLSRSACertificateFile       /etc/ssl/example.com.crt
    TLSRSACertificateKeyFile    /etc/ssl/example.com.key
    TLSCACertificateFile        /etc/ssl/example.com.ca
    TLSVerifyClient             off
    TLSRenegotiate              none
    TLSOptions                  NoSessionReuseRequired
</IfModule>

<IfModule mod_sql.c>
    SQLBackend                  mysql
    SQLEngine                   on
    SQLAuthenticate             users
    SQLAuthTypes                OpenSSL
    SQLConnectInfo              database@host dbuser dbpassword
    SQLUserInfo                 user username password uid uid home_dir NULL
    SQLUserWhereClause          "is_active = 1"

    SQLMinID                    1000
    CreateHome                  on
    RequireValidShell           off

    # Update count every time user logs in
    SQLLog                      PASS updatecount
    SQLNamedQuery               updatecount UPDATE "ftp_login_count=ftp_login_count+1, ftp_last_login=now() WHERE username='%u' LIMIT 1" user

    # Update modified everytime user uploads or deletes a file
    SQLLog                      STOR,DELE modified
    SQLNamedQuery               modified UPDATE "ftp_last_modified=now() WHERE username='%u' LIMIT 1" user
</IfModule>
```