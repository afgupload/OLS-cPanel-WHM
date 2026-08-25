<?php

declare(strict_types=1);

namespace OLScPanel\Tests\Config;

use OLScPanel\Config\ConfigManager;
use OLScPanel\Utils\Logger;
use PHPUnit\Framework\TestCase;
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;

class ConfigManagerTest extends TestCase
{
    private vfsStreamDirectory $root;
    private Logger $loggerMock;
    private string $configDir;

    protected function setUp(): void
    {
        $this->root = vfsStream::setup('etc');
        $this->configDir = vfsStream::url('etc/ols-cpanel');
        mkdir($this->configDir, 0755, true);

        $this->loggerMock = $this->createMock(Logger::class);
    }

    public function testConstructorCreatesDefaultConfig(): void
    {
        $manager = new ConfigManager($this->loggerMock, $this->configDir);
        
        $this->assertTrue(file_exists($this->configDir . '/config.yaml'));
        $this->assertNotEmpty($manager->getAll());
        $this->assertEquals('OpenLiteSpeed', $manager->get('server.name'));
    }

    public function testGetAndSet(): void
    {
        $manager = new ConfigManager($this->loggerMock, $this->configDir);
        
        $this->assertEquals('1.7.17', $manager->get('server.version'));
        $this->assertNull($manager->get('non.existent.key'));
        $this->assertEquals('default', $manager->get('non.existent.key', 'default'));
        
        $manager->set('server.new_key', 'new_value');
        $this->assertEquals('new_value', $manager->get('server.new_key'));
        
        $manager->set('new.nested.key', 123);
        $this->assertEquals(123, $manager->get('new.nested.key'));
    }

    public function testHas(): void
    {
        $manager = new ConfigManager($this->loggerMock, $this->configDir);
        
        $this->assertTrue($manager->has('server.name'));
        $this->assertFalse($manager->has('server.non_existent'));
        $this->assertFalse($manager->has('non_existent'));
    }

    public function testConfigSections(): void
    {
        $manager = new ConfigManager($this->loggerMock, $this->configDir);
        
        $serverConfig = $manager->getServerConfig();
        $this->assertIsArray($serverConfig);
        $this->assertArrayHasKey('name', $serverConfig);
        
        $performanceConfig = $manager->getPerformanceConfig();
        $this->assertIsArray($performanceConfig);
        $this->assertArrayHasKey('max_connections', $performanceConfig);
        
        $securityConfig = $manager->getSecurityConfig();
        $this->assertIsArray($securityConfig);
        
        $loggingConfig = $manager->getLoggingConfig();
        $this->assertIsArray($loggingConfig);
    }

    public function testUpdateConfig(): void
    {
        $manager = new ConfigManager($this->loggerMock, $this->configDir);
        
        $manager->updateServerConfig(['name' => 'NewName']);
        $this->assertEquals('NewName', $manager->get('server.name'));
        
        $manager->updatePerformanceConfig(['max_connections' => 5000]);
        $this->assertEquals(5000, $manager->get('performance.max_connections'));
        
        $manager->updateSecurityConfig(['rate_limiting' => false]);
        $this->assertFalse($manager->get('security.rate_limiting'));
    }

    public function testValidateConfig(): void
    {
        $manager = new ConfigManager($this->loggerMock, $this->configDir);
        $this->assertEmpty($manager->validateConfig());
        
        $manager->set('server.name', '');
        $manager->set('performance.max_connections', 0);
        $manager->set('performance.cache_size_mb', -1);
        
        $errors = $manager->validateConfig();
        $this->assertCount(3, $errors);
        $this->assertContains('Server name is required', $errors);
        $this->assertContains('Max connections must be between 1 and 100000', $errors);
        $this->assertContains('Cache size must be between 0 and 32GB', $errors);
    }

    public function testBackupAndRestore(): void
    {
        $manager = new ConfigManager($this->loggerMock, $this->configDir);
        $manager->set('server.name', 'OriginalName');
        $manager->saveConfig();
        
        $backupFile = $manager->backupConfig();
        $this->assertTrue(file_exists($backupFile));
        
        $manager->set('server.name', 'ModifiedName');
        $manager->saveConfig();
        $this->assertEquals('ModifiedName', $manager->get('server.name'));
        
        $this->assertTrue($manager->restoreConfig($backupFile));
        $this->assertEquals('OriginalName', $manager->get('server.name'));
    }
}
