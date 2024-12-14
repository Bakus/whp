<?php

namespace App\Service;

use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class OsFunctionsService
{
    public const OS_DEBIAN = 'debian';
    public const OS_UBUNTU = 'ubuntu';
    protected array $configPlaces = [
        '/etc/apache2/sites-enabled/*',
        '/etc/apache2/conf-enabled/000_dirs.conf',
        '/etc/proftpd/proftpd.conf',
        '/etc/proftpd/conf.d/*',
    ];

    protected array $sensitiveFiles = [
        '/etc/proftpd/conf.d/00_whp.conf'
    ];

    public function __construct()
    {
        $phpInstalled = $this->getPhpVersionsInstalled();
        foreach ($phpInstalled as $version) {
            $this->configPlaces[] = '/etc/php/' . $version . '/fpm/pool.d/*';
        }
    }

    public static function prettifyDirPath(string $path): string
    {
        return preg_replace('#/+#', '/', $path . '/');
    }

    public function getOs(): string
    {
        $os = PHP_OS_FAMILY;
        if ($os != 'Linux') {
            throw new RuntimeException('Only Linux is supported!');
        }

        if (!file_exists('/etc/os-release')) {
            throw new RuntimeException('Cannot determine OS: /etc/os-release does not exist');
        }

        $osRelease = file_get_contents('/etc/os-release');
        preg_match('/^ID=(?<os>\w+)$/m', $osRelease, $matches);
        switch ($matches['os']) {
            case 'debian':
                return self::OS_DEBIAN;
            case 'ubuntu':
                return self::OS_UBUNTU;
        }
        throw new RuntimeException('Unsupported operating system found: ' . $matches['os'] . '. Only Debian and Ubuntu are supported.');
    }

    public function getOsCodename(): string
    {
        if (!file_exists('/etc/os-release')) {
            throw new RuntimeException('Cannot determine OS: /etc/os-release does not exist');
        }

        $osRelease = file_get_contents('/etc/os-release');
        preg_match('/^VERSION_CODENAME=(?<codename>\w+)$/m', $osRelease, $matches);
        return $matches['codename'];
    }

    public function getPhpVersionsAvailable(): array
    {
        $process = new Process(['apt-cache', 'search', '^php([0-9\.]+)-fpm$']);
        $process->run();
        $output = $process->getOutput();
        preg_match_all('/^php([0-9\.]+)-fpm - /m', $output, $matches);
        $versions = $matches[1];
        sort($versions);
        return $versions;
    }

    public function getPhpVersionsInstalled(): array
    {
        $process = new Process(['bash', '-c', "dpkg --list | grep 'php.*fpm' | awk '{print \$2}' | sed 's/php\\([0-9]\\+\\.[0-9]\\+\\)-fpm/\\1/'"]);
        $process->run();
        $versions = explode("\n", trim($process->getOutput()));
        sort($versions);
        return $versions;
    }

    public function isSudoNeeded(): string
    {
        if (posix_getuid() === 0) {
            return '';
        }
        return 'sudo';
    }

    // to be run only in cli mode
    public function installPackage(string $packageName): bool
    {
        switch ($this->getOs()) {
            case 'debian':
                return $this->installDebianPackage($packageName);
            case 'ubuntu':
                return $this->installUbuntuPackage($packageName);
        }
    }

    public function writeConfig(string $filename, string $content): bool
    {
        // ensure directory exists
        $dir = dirname($filename);
        if (!is_dir($dir)) {
            $process = new Process([$this->isSudoNeeded(), 'mkdir', '-p', $dir]);
            $process->run();
            if (!$process->isSuccessful()) {
                throw new ProcessFailedException($process);
            }
        }

        // save file
        $process = new Process([$this->isSudoNeeded(), 'tee', $filename]);
        $process->setInput($content);
        $process->run();
        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }
        return true;
    }

    public function readConfig(string $filename): string
    {
        $content = file_get_contents($filename);
        if ($content === false) {
            throw new RuntimeException('Cannot read file ' . $filename);
        }
        return $content;
    }

    public function getConfigs(bool $getContents = false): array
    {
        $configs = [];
        foreach ($this->configPlaces as $place) {
            $files = glob($place);
            foreach ($files as $file) {
                $configs[$file] = [
                    'size' => filesize($file),
                    'mtime' => filemtime($file),
                    'status' => 'unknown',
                    'content' => $getContents ? $this->readConfig($file) : null,
                    'sensitive' => in_array($file, $this->sensitiveFiles),
                ];
            }
        }

        return $configs;
    }

    public function clearConfigs(): bool
    {
        foreach ($this->configPlaces as $place) {
            $files = glob($place);
            if (count($files) > 0) {
                $process = new Process([$this->isSudoNeeded(), 'rm', '-f', ...$files]);
                $process->run();
                if (!$process->isSuccessful()) {
                    throw new ProcessFailedException($process);
                }
            }
        }
        return true;
    }

    public function isConfigSensitive(string $filename): bool
    {
        return in_array($filename, $this->sensitiveFiles);
    }

    public function deployMessengerWorkerService(): bool
    {
        if (posix_getuid() === 0) {
            throw new RuntimeException('This method should not be run as root');
        }

        $username = posix_getpwuid(posix_getuid())['name'];
        $workingDir = realpath(dirname(__DIR__) . '/../');

        $serviceFile = <<<EOF
[Unit]
Description=Symfony messenger-consume

[Service]
ExecStart=php $workingDir/bin/console messenger:consume async --time-limit=3600 -vv
WorkingDirectory=$workingDir
Restart=always
RestartSec=30
User=$username
Group=$username

[Install]
WantedBy=default.target
EOF;
        $this->writeConfig('/etc/systemd/system/messenger-worker.service', $serviceFile);

        $process = new Process([$this->isSudoNeeded(), 'systemctl', 'daemon-reload']);
        $process->run();
        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        $process = new Process([$this->isSudoNeeded(), 'systemctl', 'enable', 'messenger-worker.service']);
        $process->run();
        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        $process = new Process([$this->isSudoNeeded(), 'systemctl', 'start', 'messenger-worker.service']);
        $process->run();
        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        return true;
    }

    public function isServiceEnabled(string $serviceName): string
    {
        $process = new Process([$this->isSudoNeeded(), 'systemctl', 'is-enabled', $serviceName]);
        $process->run();
        if ($process->isSuccessful()) {
            // enabled, disabled, masked
            return trim($process->getOutput());
        }
        return false;
    }

    public function isServiceActive(string $serviceName): string
    {
        $process = new Process([$this->isSudoNeeded(), 'systemctl', 'is-active', $serviceName]);
        $process->run();
        // inactive, active, failed
        return trim($process->getOutput());
    }

    public function getServicesStatus(): array
    {
        $services = $this->getManagedServices();
        $status = [];
        foreach ($services as $service) {
            $status[$service] = [
                'enabled' => $this->isServiceEnabled($service),
                'active' => $this->isServiceActive($service),
            ];
        }
        return $status;
    }

    public function restartService(string $serviceName): bool
    {
        $process = new Process([$this->isSudoNeeded(), 'systemctl', 'restart', $serviceName]);
        $process->run();
        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }
        return true;
    }

    public function getManagedServices(): array
    {
        $services = [
            'apache2.service',
            'proftpd.service',
            'messenger-worker.service', // symfony messenger worker
        ];
        $phpVersions = $this->getPhpVersionsInstalled();
        foreach ($phpVersions as $version) {
            $services[] = 'php' . $version . '-fpm.service';
        }
        return $services;
    }

    /**
     * Checks if system user exists and returns user id or false
     *
     * @param string $username
     * @return bool|int
     */
    public function checkSystemUserExists(string $username): bool|int
    {
        $process = new Process([$this->isSudoNeeded(), 'id', '-u', $username]);
        $process->run();
        if ($process->isSuccessful()) {
            return trim($process->getOutput());
        }
        return false;
    }

    /**
     * Checks if UID is free using getent command
     * If exists - returns username, if not - returns false
     */
    public function getUserFromUid(int $uid): bool|string
    {
        $data = posix_getpwuid($uid);
        if ($data === false) {
            return false;
        }
        return $data['name'];
    }

    /**
     * Creates system user
     *
     * @param string $username
     * @param int $uid UID and GID of the user
     * @param string $homedir
     * @return bool
     */
    public function createSystemUser(string $username, int $uid, string $homedir): bool
    {
        // create group
        $process = new Process([$this->isSudoNeeded(), 'addgroup', '--quiet', '--system', '--gid', $uid, $username]);
        $process->run();
        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        $process = new Process([$this->isSudoNeeded(), 'adduser', '--quiet', '--system', '--home', $homedir, '--uid', $uid, '--gid', $uid, '--gecos', '', '--disabled-password', $username]);
        $process->run();
        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }
        return true;
    }

    public function resetChmod(string $path, string $dirChmod = '0750', string $fileChmod = '0640'): bool
    {
        if (!preg_match('/^([0-7]{4})$/', $fileChmod)) {
            throw new InvalidArgumentException('Invalid file chmod');
        }
        if (!preg_match('/^([0-7]{4})$/', $dirChmod)) {
            throw new InvalidArgumentException('Invalid directory chmod');
        }

        $process = new Process([$this->isSudoNeeded(), 'find', $path, '-type', 'f', '-exec', 'chmod', $fileChmod, '{}', ';']);
        $process->run();
        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        $process = new Process([$this->isSudoNeeded(), 'find', $path, '-type', 'd', '-exec', 'chmod', $dirChmod, '{}', ';']);
        $process->run();
        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        return true;
    }

    public function resetChown(string $path, string $username): bool
    {
        $process = new Process([$this->isSudoNeeded(), 'chown', '-R', $username . ':', $path]);
        $process->run();
        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        return true;
    }

    public function checkPackageIsInstalled(string $packageName): bool
    {
        $process = new Process([$this->isSudoNeeded(), 'dpkg-query', '-W', $packageName, '2>/dev/null']);
        $process->run();
        $output = $process->getOutput();
        // if package is installed, dpkg-query returns package name and version or just package name otherwise it returns nothing; exit code is unreliable
        return trim($output) !== '';
    }

    protected function installDebianPackage(string $packageName): bool
    {
        if (!$this->checkPackageIsInstalled($packageName)) {
            $process = new Process([$this->isSudoNeeded(), 'apt-get', 'install', '-y', $packageName]);
            $process->run();
            if (!$process->isSuccessful()) {
                throw new ProcessFailedException($process);
            }
        }
        return true;
    }

    protected function installUbuntuPackage(string $packageName): bool
    {
        if (!$this->checkPackageIsInstalled($packageName)) {
            $process = new Process([$this->isSudoNeeded(), 'apt-get', 'install', '-y', $packageName]);
            $process->run();
            if (!$process->isSuccessful()) {
                throw new ProcessFailedException($process);
            }
        }
        return true;
    }

    public function getValueFromConfig(string $file, string $key, null|string $content = null): array|bool
    {
        if ($content === null) {
            $content = $this->readConfig($file);
        }
        $lines = explode("\n", $content);
        $pattern = '/^\s*(?!#)\s*' . preg_quote($key, '/') . '\b\s+(.+)$/';
        foreach ($lines as $line) {
            if (preg_match($pattern, $line)) {
                $values = $this->parseConfigValues($line);
                array_shift($values); // remove key
                return $values;
            }
        }
        return false;
    }

    /**
     * Sets value in config file
     * If key is not found, it will be added at the end of the file
     * If key is found, it will be replaced
     * Function assumes that key is unique in the file
     * If $content is not provided, file will be read from disk
     * If $saveFile is true, file will be saved after setting the value, otherwise content will be returned
     *
     * @param string $file
     * @param string $key
     * @param array $values
     * @param string|null $content
     * @param bool $saveFile
     * @return bool|string
     * @throws RuntimeException
     * @throws ProcessFailedException
     */
    public function setValueInConfig(string $file, string $key, array $values, null|string $content = null, bool $saveFile = false): bool|string
    {
        if ($content === null) {
            $content = $this->readConfig($file);
        }

        $newLine = $key . ' ' . implode(' ', $values);
        $lines = explode("\n", $content);

        foreach ($lines as &$line) {
            $pattern = '/^\s*(?!#)\s*' . preg_quote($key, '/') . '\b\s+.*/';
            if (preg_match($pattern, $line)) {
                $line = $newLine;
                break; // We assume that the uncommented key occurs only once
            }
        }

        $content = implode("\n", $lines);

        if ($saveFile) {
            $this->writeConfig($file, $content);
            return true;
        }
        return $content;
    }

    protected function parseConfigValues(string $values): array
    {
        $parsedValues = [];
        $length = strlen($values);
        $current = '';
        $inQuotes = false;
        $quoteChar = null;
        for ($i = 0; $i < $length; $i++) {
            $char = $values[$i];
            if ($inQuotes) {
                if ($char === $quoteChar) {
                    $inQuotes = false;
                    $quoteChar = null;
                    $parsedValues[] = $current;
                    $current = '';
                } else {
                    $current .= $char;
                }
            } elseif ($char === '"' || $char === "'") {
                $inQuotes = true;
                $quoteChar = $char;
            } elseif ($char === ' ') {
                if ($current !== '') {
                    $parsedValues[] = $current;
                    $current = '';
                }
            } else {
                $current .= $char;
            }
        }
        if ($current !== '') {
            $parsedValues[] = $current;
        }
        return $parsedValues;
    }
}
