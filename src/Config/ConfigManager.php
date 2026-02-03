<?php

declare(strict_types=1);

namespace OLScPanel\Config;

use OLScPanel\Utils\Logger;
use Symfony\Component\Yaml\Yaml;
use InvalidArgumentException;

class ConfigManager
{
    private Logger $logger;
    private array $config = [];
    private string $configFile;
    private string $configDir;

    public function __construct(Logger $logger, string $configDir = '/etc/ols-cpanel')
    {
        $this->logger = $logger;
        $this->configDir = $configDir;
        $this->configFile = $configDir . '/config.yaml';
        $this->loadConfig();
    }

    public function loadConfig(): void
    {
        try {
            if (!file_exists($this->configFile)) {
                $this->createDefaultConfig();
            }

            $content = file_get_contents($this->configFile);
            if ($content === false) {
                throw new \RuntimeException("Failed to read config file: {$this->configFile}");
            }

            $this->config = Yaml::parse($content);
            $this->logger->info('Configuration loaded successfully', [
                'file' => $this->configFile
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to load configuration', [
                'error' => $e->getMessage(),
                'file' => $this->configFile
            ]);
            throw new \RuntimeException("Failed to load configuration: {$e->getMessage()}");
        }
    }

    public function saveConfig(): bool
    {
        try {
            $yaml = Yaml::dump($this->config, 4, 2);
            
            if (!file_put_contents($this->configFile, $yaml)) {
                throw new \RuntimeException("Failed to write config file: {$this->configFile}");
            }

            chmod($this->configFile, 0640);
            $this->logger->info('Configuration saved successfully', [
                'file' => $this->configFile
            ]);

            return true;

        } catch (\Exception $e) {
            $this->logger->error('Failed to save configuration', [
                'error' => $e->getMessage(),
                'file' => $this->configFile
            ]);
            return false;
        }
    }

    public function get(string $key, $default = null)
    {
        $keys = explode('.', $key);
        $value = $this->config;

        foreach ($keys as $k) {
            if (!is_array($value) || !array_key_exists($k, $value)) {
                return $default;
            }
            $value = $value[$k];
        }

        return $value;
    }

    public function set(string $key, $value): void
    {
        $keys = explode('.', $key);
        $config = &$this->config;

        foreach ($keys as $k) {
            if (!is_array($config)) {
                $config = [];
            }
            if (!array_key_exists($k, $config)) {
                $config[$k] = [];
            }
            $config = &$config[$k];
        }

        $config = $value;
    }

    public function has(string $key): bool
    {
        return $this->get($key) !== null;
    }

    public function getAll(): array
    {
        return $this->config;
    }

    public function getServerConfig(): array
    {
        return $this->get('server', []);
    }

    public function getPerformanceConfig(): array
    {
        return $this->get('performance', []);
    }

    public function getSecurityConfig(): array
    {
        return $this->get('security', []);
    }

    public function getLoggingConfig(): array
    {
        return $this->get('logging', []);
    }

    public function updateServerConfig(array $config): bool
    {
        $this->set('server', array_merge($this->getServerConfig(), $config));
        return $this->saveConfig();
    }

    public function updatePerformanceConfig(array $config): bool
    {
        $this->set('performance', array_merge($this->getPerformanceConfig(), $config));
        return $this->saveConfig();
    }

    public function updateSecurityConfig(array $config): bool
    {
        $this->set('security', array_merge($this->getSecurityConfig(), $config));
        return $this->saveConfig();
    }

    public function validateConfig(): array
    {
        $errors = [];

        if (empty($this->get('server.name'))) {
            $errors[] = 'Server name is required';
        }

        if (empty($this->get('server.version'))) {
            $errors[] = 'Server version is required';
        }

        $maxConnections = $this->get('performance.max_connections', 0);
        if ($maxConnections <= 0 || $maxConnections > 100000) {
            $errors[] = 'Max connections must be between 1 and 100000';
        }

        $cacheSize = $this->get('performance.cache_size_mb', 0);
        if ($cacheSize < 0 || $cacheSize > 32768) {
            $errors[] = 'Cache size must be between 0 and 32GB';
        }

        return $errors;
    }

    private function createDefaultConfig(): void
    {
        $defaultConfig = [
            'server' => [
                'name' => 'OpenLiteSpeed',
                'version' => '1.7.17',
                'admin_email' => 'root@localhost',
                'user' => 'nobody',
                'group' => 'nobody'
            ],
            'performance' => [
                'max_connections' => 10000,
                'max_ssl_connections' => 10000,
                'cache_enabled' => true,
                'cache_size_mb' => 1024,
                'gzip_compression' => true,
                'gzip_level' => 6,
                'keep_alive_timeout' => 5,
                'max_keep_alive_requests' => 10000
            ],
            'security' => [
                'ssl_auto_renewal' => true,
                'ssl_renewal_days_before' => 30,
                'firewall_rules' => true,
                'rate_limiting' => true,
                'max_requests_per_minute' => 60,
                'block_failed_logins' => true,
                'max_failed_attempts' => 5,
                'block_duration_minutes' => 15
            ],
            'logging' => [
                'level' => 'INFO',
                'access_log_enabled' => true,
                'error_log_enabled' => true,
                'log_rotation' => true,
                'max_log_size_mb' => 100,
                'retention_days' => 30
            ],
            'monitoring' => [
                'enabled' => true,
                'metrics_interval' => 60,
                'alert_thresholds' => [
                    'cpu_usage' => 80,
                    'memory_usage' => 85,
                    'disk_usage' => 90,
                    'response_time' => 5000
                ]
            ],
            'php' => [
                'default_version' => '8.1',
                'allowed_versions' => ['7.4', '8.0', '8.1', '8.2'],
                'handler' => 'lsapi',
                'memory_limit' => '256M',
                'max_execution_time' => 300
            ]
        ];

        $this->config = $defaultConfig;
        $this->saveConfig();

        $this->logger->info('Default configuration created', [
            'file' => $this->configFile
        ]);
    }

    public function reloadConfig(): void
    {
        $this->loadConfig();
        $this->logger->info('Configuration reloaded');
    }

    public function backupConfig(): string
    {
        $backupDir = $this->configDir . '/backups';
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }

        $timestamp = date('Y-m-d_H-i-s');
        $backupFile = $backupDir . '/config_' . $timestamp . '.yaml';

        if (!copy($this->configFile, $backupFile)) {
            throw new \RuntimeException("Failed to create backup: {$backupFile}");
        }

        $this->logger->info('Configuration backed up', [
            'backup_file' => $backupFile
        ]);

        return $backupFile;
    }

    public function restoreConfig(string $backupFile): bool
    {
        if (!file_exists($backupFile)) {
            throw new InvalidArgumentException("Backup file not found: {$backupFile}");
        }

        if (!copy($backupFile, $this->configFile)) {
            throw new \RuntimeException("Failed to restore backup: {$backupFile}");
        }

        $this->loadConfig();
        $this->logger->info('Configuration restored', [
            'backup_file' => $backupFile
        ]);

        return true;
    }

    public function getConfigTemplate(string $template): array
    {
        $templateFile = $this->configDir . '/templates/' . $template . '.yaml';
        
        if (!file_exists($templateFile)) {
            throw new InvalidArgumentException("Template not found: {$template}");
        }

        return Yaml::parseFile($templateFile);
    }

    public function applyTemplate(string $template, array $overrides = []): bool
    {
        $templateConfig = $this->getConfigTemplate($template);
        $this->config = array_merge_recursive($this->config, $templateConfig, $overrides);
        
        return $this->saveConfig();
    }
}
