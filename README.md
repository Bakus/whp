# IntraDom Network Web Hosting Panel

## Installation
Minimal required PHP version is 8.2

```
git clone ...
cd whp/
composer install
```

## Update
```
git pull
composer update "symfony/*" --with-all-dependencies
```

## Basic usage
```
symfony server:start
```

### Deploy to PROD
```
APP_ENV=prod APP_DEBUG=0 composer install --no-dev --optimize-autoloader"
```

### Apply all database migrations
```
APP_ENV=prod APP_DEBUG=0 php bin/console doctrine:migrations:migrate --no-interaction --all-or-nothing"
```

## Commands for `symfony console`:
```
  app:add-admin               Add admin user to database
  app:deploy-system-users     Add admin user to database
  app:import-certs            Import SSL Certificates into the database.
                              Existing certificates will be skipped.
  app:install-system-software Installs PHP repository, selected PHP interpreters
                              and additional software such as Apache2, ProFTPd, etc.
                              (Debian/Ubuntu ONLY!)
  app:make-config-files       Creates configuration files
  app:status                  Shows status of services and configuration files
```
