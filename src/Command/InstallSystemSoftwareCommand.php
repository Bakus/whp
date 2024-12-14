<?php

namespace App\Command;

use App\Service\OsFunctionsService;
use RuntimeException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\Process\Process;

#[AsCommand(
    name: 'app:install-system-software',
    description: 'Installs PHP repository, selected PHP interpreters (Debian/Ubuntu ONLY!) and additional software such as Apache2, ProFTPd, etc.',
)]
class InstallSystemSoftwareCommand extends Command
{
    protected SymfonyStyle $io;

    public function __construct(
        protected OsFunctionsService $osFunctions
    ) {
        parent::__construct();

        // If not Debian or Ubuntu - throw an exception
        $this->osFunctions->getOs();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);
        $filesystem = new Filesystem();

        $this->io->title('Installing PHP Repository');
        switch ($this->osFunctions->getOs()) {
            case 'debian':
                $this->io->section('Installing prerequisites...');
                $packages = ['lsb-release', 'ca-certificates', 'curl'];
                foreach ($packages as $package) {
                    $this->io->writeln('Installing ' . $package . '...');
                    $this->osFunctions->installPackage($package);
                }

                if (!file_exists('/usr/share/keyrings/deb.sury.org-php.gpg')) {
                    $this->io->section('Adding sury.org repository...');
                    $this->io->writeln('Downloading debsuryorg-archive-keyring.deb...');
                    $package = $this->wget('https://packages.sury.org/debsuryorg-archive-keyring.deb');
                    $filesystem->dumpFile('/tmp/debsuryorg-archive-keyring.deb', $package);

                    $this->io->writeln('Installing debsuryorg-archive-keyring.deb...');
                    $this->runProcess('dpkg', '-i', '/tmp/debsuryorg-archive-keyring.deb');

                    $filesystem->remove('/tmp/debsuryorg-archive-keyring.deb');

                    $this->io->writeln('Adding repository...');
                    $process = new Process(['lsb_release', '-sc']);
                    $lsbRelease = $this->runProcess('lsb_release', '-sc');
                    $osVersion = trim($lsbRelease->getOutput());
                    if (empty($osVersion)) {
                        $this->io->error('Failed to get OS version');
                        return Command::FAILURE;
                    }
                    $this->osFunctions->writeConfig('/etc/apt/sources.list.d/php.list', "deb [signed-by=/usr/share/keyrings/deb.sury.org-php.gpg] https://packages.sury.org/php/ $osVersion main");
                }
                break;
            case 'ubuntu':
                $this->io->section('Installing prerequisites...');
                $this->io->writeln('Installing software-properties-common...');
                $this->osFunctions->installPackage('software-properties-common');
                $process = $this->runProcess('add-apt-repository', '-L');
                $output = $process->getOutput();
                $this->io->section('Adding ppa:ondrej/php repository...');
                if (strpos($output, 'https://ppa.launchpadcontent.net/ondrej/php/ubuntu/') === false) {
                    $this->runProcess('add-apt-repository', 'ppa:ondrej/php');
                    $this->io->writeln('Repository added');
                } else {
                    $this->io->writeln('Repository already added');
                }
                break;
        }

        $this->io->title('Updating package list...');
        $this->runProcess('apt-get', 'update');

        $this->io->writeln("Currently installed PHP Versions:");
        $installedPhpVersions = $this->osFunctions->getPhpVersionsInstalled();
        $this->io->listing($installedPhpVersions);

        $availablePhpVersions = $this->osFunctions->getPhpVersionsAvailable();
        $toInstall = $this->io->choice('Select PHP versions to install (separate with commas if multiple)', $availablePhpVersions, multiSelect: true);
        $this->io->writeln('Selected versions: ' . implode(', ', $toInstall));

        foreach ($toInstall as $version) {
            $this->installPhp($version);
        }

        $this->io->section('Fixing broken packages... if any ;)');

        $this->runProcess('apt', '--fix-broken', 'install');
        $this->runProcess('dpkg', '--configure', '-a');

        $this->io->writeln("Currently installed PHP Versions:");
        $installedPhpVersions = $this->osFunctions->getPhpVersionsInstalled();
        $this->io->listing($installedPhpVersions);

        $this->io->success('All PHP packages installed successfully!');

        $additionalPackages = [
            'apache2',
            'libapache2-mpm-itk',
            'proftpd-core',
            'proftpd-mod-crypto',
            'proftpd-mod-mysql',
            'proftpd-mod-vroot',
            'php-pear',
            // fail2ban, python3-pyinotify
        ];
        $this->io->section('You may need to install additional packages... checking...');
        foreach ($additionalPackages as $package) {
            $this->io->writeln('Checking ' . $package . '...');
            if ($this->osFunctions->checkPackageIsInstalled($package)) {
                $this->io->writeln($package . ' is already installed');
            } else {
                $this->io->writeln($package . ' is not installed');
                $toInstall = $this->io->choice('Install it?', ['y', 'n'], multiSelect: false);
                if ($toInstall === 'y') {
                    $this->osFunctions->installPackage($package);
                }
            }
        }
        if ($this->osFunctions->checkPackageIsInstalled('apache2') && $this->osFunctions->checkPackageIsInstalled('libapache2-mpm-itk')) {
            $this->io->section('Enabling Apache2 modules...');
            $this->runProcess('a2enmod', 'rewrite');
            $this->runProcess('a2enmod', 'mpm_itk');
            $this->runProcess('a2enmod', 'proxy_fcgi');
            $this->runProcess('a2enmod', 'ssl');
            $this->runProcess('a2enmod', 'headers');
        }

        if (!$this->osFunctions->checkPackageIsInstalled('cloudflared')) {
            $toInstall = $this->io->choice('Install cloudflared?', ['y', 'n'], multiSelect: false);
            if ($toInstall === 'y') {
                $this->installCloudflared();
            }
        } else {
            $this->io->writeln('cloudflared is already installed');
        }

        $toInstall = $this->io->choice('Deploy and enable messenger-worker.service?', ['y', 'n'], multiSelect: false);
        if ($toInstall === 'y') {
            $this->osFunctions->deployMessengerWorkerService();
        }

        $this->io->success('That\'s all folks!');
        $this->io->info('Now you can run `app:make-config-files` command to generate configuration files');

        return Command::SUCCESS;
    }

    protected function runProcess(string ...$command): Process
    {
        $toRun = [$this->osFunctions->isSudoNeeded(), ...$command];
        $process = new Process($toRun);
        $process->run();
        if (!$process->isSuccessful()) {
            throw new RuntimeException('Failed to run command: ' . implode(' ', $toRun));
        }
        return $process;
    }

    protected function installPhp(string $version): bool
    {
        $packages = [
            "php$version-bcmath",
            "php$version-bz2",
            "php$version-common",
            "php$version-curl",
            "php$version-gd",
            "php$version-gmp",
            "php$version-gnupg",
            "php$version-imagick",
            "php$version-imap",
            "php$version-intl",
            "php$version-json",
            "php$version-lz4",
            "php$version-mbstring",
            "php$version-mcrypt",
            "php$version-mysql",
            "php$version-opcache",
            "php$version-pgsql",
            "php$version-readline",
            "php$version-soap",
            "php$version-sqlite3",
            "php$version-xml",
            "php$version-zip",
            "php$version-cli",
            "php$version-fpm",
        ];
        $this->io->section('Installing PHP ' . $version . '...');
        foreach ($packages as $package) {
            $this->io->write('Installing ' . $package . '...');
            $this->osFunctions->installPackage($package);
            $this->io->writeln(' done');
        }
        return true;
    }

    protected function installCloudflared(): void
    {
        $this->io->section('Installing cloudflared...');
        $this->io->writeln('Downloading cloudflare-main.gpg...');
        $gpg = $this->wget('https://pkg.cloudflare.com/cloudflare-main.gpg');
        $this->osFunctions->writeConfig('/usr/share/keyrings/cloudflare-main.gpg', $gpg);

        $this->io->writeln('Adding repository...');
        $osCodename = $this->osFunctions->getOsCodename();
        $this->osFunctions->writeConfig(
            '/etc/apt/sources.list.d/cloudflared.list',
            'deb [signed-by=/usr/share/keyrings/cloudflare-main.gpg] https://pkg.cloudflare.com/cloudflared ' . $osCodename . ' main'
        );

        $this->io->writeln('Updating package list...');
        $this->runProcess('apt-get', 'update');

        $this->osFunctions->installPackage('cloudflared');
        $this->io->writeln('cloudflared installed successfully!');
    }

    protected function wget(string $url): string
    {
        $client = HttpClient::create();
        $response = $client->request('GET', $url);

        $statusCode = $response->getStatusCode();
        if ($statusCode != 200) {
            throw new RuntimeException('Failed to download file');
        }
        $content = $response->getContent();

        return $content;
    }
}
