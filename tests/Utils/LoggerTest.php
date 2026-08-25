<?php

declare(strict_types=1);

namespace OLScPanel\Tests\Utils;

use OLScPanel\Utils\Logger;
use PHPUnit\Framework\TestCase;
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;
use Monolog\Logger as MonologLogger;

class LoggerTest extends TestCase
{
    private vfsStreamDirectory $root;
    private Logger $logger;

    protected function setUp(): void
    {
        $this->root = vfsStream::setup('var');
        $logDir = vfsStream::url('var/log/ols-cpanel');
        $this->logger = new Logger('test-ols-cpanel', $logDir);
    }

    public function testConstructorCreatesLogDirectory(): void
    {
        $this->assertTrue($this->root->hasChild('log/ols-cpanel'));
        $this->assertTrue(is_dir(vfsStream::url('var/log/ols-cpanel')));
    }

    public function testGetLogDirAndFile(): void
    {
        $this->assertEquals('vfs://var/log/ols-cpanel', $this->logger->getLogDir());
        $this->assertEquals('vfs://var/log/ols-cpanel/ols-cpanel.log', $this->logger->getLogFile());
    }

    public function testGetLoggerReturnsMonologInstance(): void
    {
        $this->assertInstanceOf(MonologLogger::class, $this->logger->getLogger());
        $this->assertEquals('test-ols-cpanel', $this->logger->getLogger()->getName());
    }

    public function testLoggingMethods(): void
    {
        $this->logger->info('Test info message', ['foo' => 'bar']);
        $this->logger->error('Test error message');
        $this->logger->debug('Test debug message');
        
        $logContent = file_get_contents($this->logger->getLogFile());
        
        $this->assertStringContainsString('Test info message', $logContent);
        $this->assertStringContainsString('"foo":"bar"', $logContent);
        $this->assertStringContainsString('Test error message', $logContent);
        $this->assertStringContainsString('Test debug message', $logContent);
        $this->assertStringContainsString('INFO', $logContent);
        $this->assertStringContainsString('ERROR', $logContent);
        $this->assertStringContainsString('DEBUG', $logContent);
    }
    
    public function testSpecificLoggers(): void
    {
        $this->logger->logAccess('User logged in', ['user' => 'admin']);
        $this->logger->logDomain('example.com', 'created');
        $this->logger->logException(new \Exception('Test exception message'));
        
        $logContent = file_get_contents($this->logger->getLogFile());
        
        $this->assertStringContainsString('User logged in', $logContent);
        $this->assertStringContainsString('"type":"access"', $logContent);
        
        $this->assertStringContainsString('Domain created: example.com', $logContent);
        $this->assertStringContainsString('"type":"domain"', $logContent);
        $this->assertStringContainsString('"domain":"example.com"', $logContent);
        $this->assertStringContainsString('"action":"created"', $logContent);
        
        $this->assertStringContainsString('Test exception message', $logContent);
        $this->assertStringContainsString('"type":"exception"', $logContent);
        $this->assertStringContainsString('Exception', $logContent);
    }
    
    public function testGetLogSize(): void
    {
        $this->assertEquals(0, $this->logger->getLogSize());
        $this->logger->info('Message');
        $this->assertGreaterThan(0, $this->logger->getLogSize());
    }
    
    public function testClearLogs(): void
    {
        $this->logger->info('Message to clear');
        $this->assertGreaterThan(0, $this->logger->getLogSize());
        
        $this->assertTrue($this->logger->clearLogs());
        
        $this->assertEquals(0, $this->logger->getLogSize());
        $this->assertEquals('', file_get_contents($this->logger->getLogFile()));
    }
}
