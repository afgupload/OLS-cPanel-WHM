<?php

declare(strict_types=1);

namespace OLScPanel\Utils;

use OLScPanel\Utils\Logger;

class SystemDetector
{
    private Logger $logger;
    private array $systemInfo;

    public function __construct(Logger $logger)
    {
        $this->logger = $logger;
        $this->systemInfo = $this->detectSystem();
    }

    public function getSystemInfo(): array
    {
        return $this->systemInfo;
    }

    public function getOperatingSystem(): string
    {
        return $this->systemInfo['os'] ?? 'Unknown';
    }

    public function getOsVersion(): string
    {
        return $this->systemInfo['version'] ?? 'Unknown';
    }

    public function getArchitecture(): string
    {
        return $this->systemInfo['architecture'] ?? 'Unknown';
    }

    public function getPackageManager(): string
    {
        return $this->systemInfo['package_manager'] ?? 'Unknown';
    }

    public function isSupported(): bool
    {
        return $this->systemInfo['supported'] ?? false;
    }

    public function isRhelBased(): bool
    {
        return in_array($this->getOperatingSystem(), ['AlmaLinux', 'Rocky Linux', 'CloudLinux']);
    }

    public function isDebianBased(): bool
    {
        return in_array($this->getOperatingSystem(), ['Ubuntu', 'Debian']);
    }

    public function getPhpBinaryPath(string $version): string
    {
        $os = $this->getOperatingSystem();
        
        if ($this->isRhelBased()) {
            $paths = [
                "/opt/cpanel/ea-php{$version}/bin/php",
                "/usr/bin/php{$version}",
                "/usr/local/bin/php{$version}"
            ];
        } else {
            $paths = [
                "/usr/bin/php{$version}",
                "/usr/local/bin/php{$version}",
                "/opt/cpanel/ea-php{$version}/bin/php"
            ];
        }

        foreach ($paths as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }

        return '/usr/bin/php';
    }

    public function getApacheServiceName(): string
    {
        if ($this->isDebianBased()) {
            return 'apache2';
        } else {
            return 'httpd';
        }
    }

    public function getApacheConfigPath(): string
    {
        if ($this->isDebianBased()) {
            return '/etc/apache2';
        } else {
            return '/etc/httpd';
        }
    }

    public function getSystemdPath(): string
    {
        return '/etc/systemd/system';
    }

    public function getLogPath(): string
    {
        if ($this->isDebianBased()) {
            return '/var/log';
        } else {
            return '/var/log';
        }
    }

    public function getTempPath(): string
    {
        return '/tmp';
    }

    public function getEtcPath(): string
    {
        return '/etc';
    }

    public function getUsrLocalPath(): string
    {
        return '/usr/local';
    }

    public function getHomePath(): string
    {
        return '/home';
    }

    public function validateSystem(): array
    {
        $errors = [];
        $warnings = [];

        // Check OS support
        if (!$this->isSupported()) {
            $errors[] = "Unsupported operating system: {$this->getOperatingSystem()} {$this->getOsVersion()}";
        }

        // Check architecture
        if ($this->getArchitecture() !== 'x86_64') {
            $warnings[] = "Architecture {$this->getArchitecture()} detected. x86_64 is recommended for best performance.";
        }

        // Check required commands
        $requiredCommands = ['systemctl', 'curl', 'wget', 'tar', 'unzip'];
        foreach ($requiredCommands as $command) {
            if (!$this->commandExists($command)) {
                $errors[] = "Required command not found: {$command}";
            }
        }

        // Check cPanel
        if (!$this->commandExists('whmapi1')) {
            $errors[] = "cPanel/WHM not found. whmapi1 command is required.";
        }

        // Check PHP
        if (!$this->commandExists('php')) {
            $errors[] = "PHP is not installed or not in PATH.";
        } else {
            $phpVersion = $this->getPhpVersion();
            if (version_compare($phpVersion, '8.1.0', '<')) {
                $errors[] = "PHP 8.1+ required, found version {$phpVersion}";
            }
        }

        // Check memory
        $memory = $this->getMemoryInfo();
        $memoryGb = $memory['total'] / 1024 / 1024 / 1024;
        if ($memoryGb < 4) {
            $warnings[] = "System has less than 4GB RAM ({$memoryGb}GB). Performance may be affected.";
        }

        // Check disk space
        $disk = $this->getDiskInfo();
        if ($disk['available'] < 20 * 1024 * 1024 * 1024) { // 20GB
            $errors[] = "Insufficient disk space. At least 20GB required.";
        }

        return [
            'errors' => $errors,
            'warnings' => $warnings,
            'system_info' => $this->systemInfo
        ];
    }

    private function detectSystem(): array
    {
        $systemInfo = [
            'os' => 'Unknown',
            'version' => 'Unknown',
            'architecture' => php_uname('m'),
            'package_manager' => 'Unknown',
            'supported' => false,
            'family' => 'Unknown'
        ];

        // Try to read /etc/os-release
        if (file_exists('/etc/os-release')) {
            $osRelease = parse_ini_file('/etc/os-release');
            
            if (isset($osRelease['NAME'])) {
                $systemInfo['os'] = $osRelease['NAME'];
            }
            
            if (isset($osRelease['VERSION_ID'])) {
                $systemInfo['version'] = $osRelease['VERSION_ID'];
            }

            if (isset($osRelease['ID'])) {
                $systemInfo['family'] = $osRelease['ID'];
            }
        }

        // Fallback detection methods
        if ($systemInfo['os'] === 'Unknown') {
            if (file_exists('/etc/redhat-release')) {
                $release = file_get_contents('/etc/redhat-release');
                if (strpos($release, 'AlmaLinux') !== false) {
                    $systemInfo['os'] = 'AlmaLinux';
                    $systemInfo['family'] = 'almalinux';
                } elseif (strpos($release, 'Rocky') !== false) {
                    $systemInfo['os'] = 'Rocky Linux';
                    $systemInfo['family'] = 'rocky';
                } elseif (strpos($release, 'CloudLinux') !== false) {
                    $systemInfo['os'] = 'CloudLinux';
                    $systemInfo['family'] = 'cloudlinux';
                }
            } elseif (file_exists('/etc/debian_version')) {
                $systemInfo['os'] = 'Debian';
                $systemInfo['family'] = 'debian';
            } elseif (file_exists('/etc/lsb-release')) {
                $lsbRelease = parse_ini_file('/etc/lsb-release');
                if (isset($lsbRelease['DISTRIB_ID'])) {
                    $systemInfo['os'] = $lsbRelease['DISTRIB_ID'];
                    $systemInfo['family'] = strtolower($lsbRelease['DISTRIB_ID']);
                }
            }
        }

        // Detect package manager
        if ($this->commandExists('dnf')) {
            $systemInfo['package_manager'] = 'dnf';
        } elseif ($this->commandExists('yum')) {
            $systemInfo['package_manager'] = 'yum';
        } elseif ($this->commandExists('apt')) {
            $systemInfo['package_manager'] = 'apt';
        } elseif ($this->commandExists('apt-get')) {
            $systemInfo['package_manager'] = 'apt-get';
        }

        // Check if OS is supported
        $systemInfo['supported'] = $this->isOsSupported($systemInfo['os'], $systemInfo['version']);

        $this->logger->info('System detected', $systemInfo);

        return $systemInfo;
    }

    private function isOsSupported(string $os, string $version): bool
    {
        $majorVersion = (int)explode('.', $version)[0];

        switch ($os) {
            case 'AlmaLinux':
            case 'Rocky Linux':
            case 'CloudLinux':
                return $majorVersion >= 9;
            
            case 'Ubuntu':
                return $majorVersion >= 22;
            
            case 'Debian':
                return $majorVersion >= 12;
            
            default:
                return false;
        }
    }

    private function commandExists(string $command): bool
    {
        return shell_exec("which {$command} 2>/dev/null") !== null;
    }

    private function getPhpVersion(): string
    {
        $output = shell_exec('php -r "echo PHP_VERSION;" 2>/dev/null');
        return $output ?: '0.0.0';
    }

    private function getMemoryInfo(): array
    {
        $meminfo = file_get_contents('/proc/meminfo');
        preg_match('/MemTotal:\s+(\d+)\s+kB/', $meminfo, $matches);
        
        return [
            'total' => isset($matches[1]) ? (int)$matches[1] * 1024 : 0,
            'formatted' => isset($matches[1]) ? round($matches[1] / 1024 / 1024, 2) . ' GB' : 'Unknown'
        ];
    }

    private function getDiskInfo(): array
    {
        $output = shell_exec('df -B1 / 2>/dev/null');
        if ($output && preg_match('/\d+\s+\d+\s+(\d+)\s+/', $output, $matches)) {
            $available = (int)$matches[1];
            return [
                'available' => $available,
                'formatted' => round($available / 1024 / 1024 / 1024, 2) . ' GB'
            ];
        }

        return [
            'available' => 0,
            'formatted' => 'Unknown'
        ];
    }

    public function getRecommendedPackages(): array
    {
        if ($this->isRhelBased()) {
            return [
                'curl', 'wget', 'unzip', 'tar', 'systemd', 'which', 'git', 
                'epel-release', 'php', 'php-cli', 'php-json', 'php-curl', 
                'php-mbstring', 'php-xml', 'php-zip'
            ];
        } elseif ($this->isDebianBased()) {
            return [
                'curl', 'wget', 'unzip', 'tar', 'systemd', 'which', 'git', 
                'software-properties-common', 'php', 'php-cli', 'php-json', 
                'php-curl', 'php-mbstring', 'php-xml', 'php-zip'
            ];
        }

        return [];
    }

    public function getInstallCommand(string $package): string
    {
        if ($this->isRhelBased()) {
            return "dnf install -y {$package}";
        } elseif ($this->isDebianBased()) {
            return "apt install -y {$package}";
        }

        return "# Unknown package manager for {$package}";
    }

    public function getServiceCommand(string $service, string $action): string
    {
        return "systemctl {$action} {$service}";
    }

    public function getPhpPaths(): array
    {
        $paths = [];
        
        if ($this->isRhelBased()) {
            $paths = [
                '/opt/cpanel/ea-php81/bin/php',
                '/opt/cpanel/ea-php82/bin/php',
                '/opt/cpanel/ea-php83/bin/php',
                '/usr/bin/php8.1',
                '/usr/bin/php8.2',
                '/usr/bin/php8.3',
                '/usr/local/bin/php',
                '/usr/bin/php'
            ];
        } else {
            $paths = [
                '/usr/bin/php8.1',
                '/usr/bin/php8.2', 
                '/usr/bin/php8.3',
                '/usr/local/bin/php8.1',
                '/usr/local/bin/php8.2',
                '/usr/local/bin/php8.3',
                '/usr/local/bin/php',
                '/usr/bin/php'
            ];
        }

        return $paths;
    }
}
