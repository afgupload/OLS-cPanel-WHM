<?php

declare(strict_types=1);

namespace OLScPanel\Services;

use OLScPanel\Utils\Logger;
use OLScPanel\Utils\SystemDetector;

class SystemService
{
    private Logger $logger;
    private SystemDetector $systemDetector;

    public function __construct(Logger $logger, SystemDetector $systemDetector)
    {
        $this->logger = $logger;
        $this->systemDetector = $systemDetector;
    }

    public function installPackage(string $package): bool
    {
        $command = $this->systemDetector->getInstallCommand($package);
        
        $this->logger->info("Installing package: {$package}");
        
        $output = shell_exec($command . ' 2>&1');
        $exitCode = shell_exec('echo $?');
        
        if (trim($exitCode) === '0') {
            $this->logger->info("Package {$package} installed successfully");
            return true;
        } else {
            $this->logger->error("Failed to install package {$package}", [
                'command' => $command,
                'output' => $output,
                'exit_code' => $exitCode
            ]);
            return false;
        }
    }

    public function installPackages(array $packages): array
    {
        $results = [];
        
        foreach ($packages as $package) {
            $results[$package] = $this->installPackage($package);
        }
        
        return $results;
    }

    public function updateSystem(): bool
    {
        $this->logger->info('Updating system packages');
        
        if ($this->systemDetector->isRhelBased()) {
            $command = 'dnf update -y';
        } elseif ($this->systemDetector->isDebianBased()) {
            $command = 'apt update && apt upgrade -y';
        } else {
            $this->logger->error('Unsupported operating system for package update');
            return false;
        }
        
        $output = shell_exec($command . ' 2>&1');
        $exitCode = shell_exec('echo $?');
        
        if (trim($exitCode) === '0') {
            $this->logger->info('System updated successfully');
            return true;
        } else {
            $this->logger->error('Failed to update system', [
                'command' => $command,
                'output' => $output,
                'exit_code' => $exitCode
            ]);
            return false;
        }
    }

    public function manageService(string $service, string $action): bool
    {
        $command = $this->systemDetector->getServiceCommand($service, $action);
        
        $this->logger->info("Managing service: {$action} {$service}");
        
        $output = shell_exec($command . ' 2>&1');
        $exitCode = shell_exec('echo $?');
        
        if (trim($exitCode) === '0') {
            $this->logger->info("Service {$service} {$action} successful");
            return true;
        } else {
            $this->logger->error("Failed to {$action} service {$service}", [
                'command' => $command,
                'output' => $output,
                'exit_code' => $exitCode
            ]);
            return false;
        }
    }

    public function stopApache(): bool
    {
        $serviceName = $this->systemDetector->getApacheServiceName();
        
        // Try to stop both possible service names
        $services = ['httpd', 'apache2', $serviceName];
        
        foreach ($services as $service) {
            if ($this->isServiceRunning($service)) {
                $this->manageService($service, 'stop');
                $this->manageService($service, 'disable');
            }
        }
        
        return true;
    }

    public function isServiceRunning(string $service): bool
    {
        $output = shell_exec("systemctl is-active {$service} 2>/dev/null");
        return trim($output) === 'active';
    }

    public function getServiceStatus(string $service): array
    {
        $output = shell_exec("systemctl status {$service} --no-pager 2>/dev/null");
        $activeOutput = shell_exec("systemctl is-active {$service} 2>/dev/null");
        $enabledOutput = shell_exec("systemctl is-enabled {$service} 2>/dev/null");
        
        return [
            'service' => $service,
            'active' => trim($activeOutput),
            'enabled' => trim($enabledOutput),
            'status' => $output,
            'running' => trim($activeOutput) === 'active'
        ];
    }

    public function createDirectory(string $path, int $permissions = 0755): bool
    {
        if (!is_dir($path)) {
            $this->logger->info("Creating directory: {$path}");
            
            if (!mkdir($path, $permissions, true)) {
                $this->logger->error("Failed to create directory: {$path}");
                return false;
            }
            
            chmod($path, $permissions);
        }
        
        return true;
    }

    public function copyFile(string $source, string $destination): bool
    {
        $this->logger->info("Copying file: {$source} -> {$destination}");
        
        $dir = dirname($destination);
        if (!is_dir($dir)) {
            $this->createDirectory($dir);
        }
        
        if (!copy($source, $destination)) {
            $this->logger->error("Failed to copy file: {$source} -> {$destination}");
            return false;
        }
        
        return true;
    }

    public function copyDirectory(string $source, string $destination): bool
    {
        $this->logger->info("Copying directory: {$source} -> {$destination}");
        
        if (!is_dir($source)) {
            $this->logger->error("Source directory does not exist: {$source}");
            return false;
        }
        
        $command = "cp -r {$source} {$destination}";
        $output = shell_exec($command . ' 2>&1');
        $exitCode = shell_exec('echo $?');
        
        if (trim($exitCode) === '0') {
            return true;
        } else {
            $this->logger->error("Failed to copy directory", [
                'command' => $command,
                'output' => $output,
                'exit_code' => $exitCode
            ]);
            return false;
        }
    }

    public function removeFile(string $path): bool
    {
        if (file_exists($path)) {
            $this->logger->info("Removing file: {$path}");
            return unlink($path);
        }
        return true;
    }

    public function removeDirectory(string $path): bool
    {
        if (is_dir($path)) {
            $this->logger->info("Removing directory: {$path}");
            
            $command = "rm -rf {$path}";
            $output = shell_exec($command . ' 2>&1');
            $exitCode = shell_exec('echo $?');
            
            return trim($exitCode) === '0';
        }
        return true;
    }

    public function fileExists(string $path): bool
    {
        return file_exists($path);
    }

    public function isDirectory(string $path): bool
    {
        return is_dir($path);
    }

    public function getFilePermissions(string $path): ?int
    {
        if (!file_exists($path)) {
            return null;
        }
        
        return fileperms($path) & 0777;
    }

    public function setFilePermissions(string $path, int $permissions): bool
    {
        if (!file_exists($path)) {
            return false;
        }
        
        return chmod($path, $permissions);
    }

    public function getFileOwner(string $path): ?string
    {
        if (!file_exists($path)) {
            return null;
        }
        
        $owner = fileowner($path);
        return $owner ? posix_getpwuid($owner)['name'] : null;
    }

    public function setFileOwner(string $path, string $user, ?string $group = null): bool
    {
        if (!file_exists($path)) {
            return false;
        }
        
        $command = "chown {$user}";
        if ($group) {
            $command .= ":{$group}";
        }
        $command .= " {$path}";
        
        $output = shell_exec($command . ' 2>&1');
        $exitCode = shell_exec('echo $?');
        
        return trim($exitCode) === '0';
    }

    public function executeCommand(string $command, array $environment = []): array
    {
        $this->logger->info("Executing command: {$command}");
        
        // Set environment variables if provided
        $envString = '';
        foreach ($environment as $key => $value) {
            $envString .= "{$key}='{$value}' ";
        }
        
        $fullCommand = $envString . $command;
        $output = shell_exec($fullCommand . ' 2>&1');
        $exitCode = shell_exec('echo $?');
        
        $result = [
            'command' => $command,
            'output' => $output,
            'exit_code' => (int)trim($exitCode),
            'success' => trim($exitCode) === '0'
        ];
        
        if ($result['success']) {
            $this->logger->info("Command executed successfully", $result);
        } else {
            $this->logger->error("Command execution failed", $result);
        }
        
        return $result;
    }

    public function downloadFile(string $url, string $destination): bool
    {
        $this->logger->info("Downloading file: {$url} -> {$destination}");
        
        $dir = dirname($destination);
        if (!is_dir($dir)) {
            $this->createDirectory($dir);
        }
        
        $command = "curl -fsSL '{$url}' -o '{$destination}'";
        $output = shell_exec($command . ' 2>&1');
        $exitCode = shell_exec('echo $?');
        
        if (trim($exitCode) === '0') {
            $this->logger->info("File downloaded successfully: {$destination}");
            return true;
        } else {
            $this->logger->error("Failed to download file", [
                'url' => $url,
                'destination' => $destination,
                'output' => $output,
                'exit_code' => $exitCode
            ]);
            return false;
        }
    }

    public function extractArchive(string $archive, string $destination): bool
    {
        $this->logger->info("Extracting archive: {$archive} -> {$destination}");
        
        if (!is_dir($destination)) {
            $this->createDirectory($destination);
        }
        
        $extension = pathinfo($archive, PATHINFO_EXTENSION);
        
        switch ($extension) {
            case 'tgz':
            case 'gz':
                $command = "tar -xzf '{$archive}' -C '{$destination}'";
                break;
            case 'bz2':
                $command = "tar -xjf '{$archive}' -C '{$destination}'";
                break;
            case 'zip':
                $command = "unzip -q '{$archive}' -d '{$destination}'";
                break;
            default:
                $this->logger->error("Unsupported archive format: {$extension}");
                return false;
        }
        
        $output = shell_exec($command . ' 2>&1');
        $exitCode = shell_exec('echo $?');
        
        if (trim($exitCode) === '0') {
            $this->logger->info("Archive extracted successfully");
            return true;
        } else {
            $this->logger->error("Failed to extract archive", [
                'command' => $command,
                'output' => $output,
                'exit_code' => $exitCode
            ]);
            return false;
        }
    }

    public function getSystemLoad(): array
    {
        $loadAvg = sys_getloadavg();
        
        return [
            '1min' => $loadAvg[0] ?? 0,
            '5min' => $loadAvg[1] ?? 0,
            '15min' => $loadAvg[2] ?? 0,
            'formatted' => sprintf("%.2f, %.2f, %.2f", $loadAvg[0] ?? 0, $loadAvg[1] ?? 0, $loadAvg[2] ?? 0)
        ];
    }

    public function getUptime(): string
    {
        $uptime = shell_exec('uptime -p 2>/dev/null');
        if ($uptime) {
            return trim($uptime);
        }
        
        // Fallback method
        $uptime = shell_exec('cat /proc/uptime | cut -d" " -f1');
        if ($uptime) {
            $seconds = (int)$uptime;
            $days = floor($seconds / 86400);
            $hours = floor(($seconds % 86400) / 3600);
            $minutes = floor(($seconds % 3600) / 60);
            
            return sprintf("%d days, %d hours, %d minutes", $days, $hours, $minutes);
        }
        
        return 'Unknown';
    }

    public function getKernelVersion(): string
    {
        return php_uname('r');
    }

    public function getHostname(): string
    {
        return php_uname('n');
    }
}
