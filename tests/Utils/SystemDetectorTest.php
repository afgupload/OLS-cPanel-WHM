<?php

declare(strict_types=1);

namespace OLScPanel\Tests\Utils;

use OLScPanel\Utils\Logger;
use OLScPanel\Utils\SystemDetector;
use PHPUnit\Framework\TestCase;

class SystemDetectorTest extends TestCase
{
    private Logger $loggerMock;
    private SystemDetector $detector;

    protected function setUp(): void
    {
        $this->loggerMock = $this->createMock(Logger::class);
        $this->detector = $this->getMockBuilder(SystemDetector::class)
            ->setConstructorArgs([$this->loggerMock])
            ->onlyMethods(['getOperatingSystem', 'getOsVersion', 'commandExists'])
            ->getMock();
    }

    public function testGetOperatingSystemAndVersion(): void
    {
        $detector = new SystemDetector($this->loggerMock);
        $this->assertIsString($detector->getOperatingSystem());
        $this->assertIsString($detector->getOsVersion());
        $this->assertIsString($detector->getArchitecture());
        $this->assertIsString($detector->getPackageManager());
        $this->assertIsBool($detector->isSupported());
    }

    public function testOsDetection(): void
    {
        $this->detector->method('getOperatingSystem')->willReturn('AlmaLinux');
        $this->assertTrue($this->detector->isRhelBased());
        $this->assertFalse($this->detector->isDebianBased());

        $debianDetector = $this->getMockBuilder(SystemDetector::class)
            ->setConstructorArgs([$this->loggerMock])
            ->onlyMethods(['getOperatingSystem'])
            ->getMock();
            
        $debianDetector->method('getOperatingSystem')->willReturn('Ubuntu');
        $this->assertFalse($debianDetector->isRhelBased());
        $this->assertTrue($debianDetector->isDebianBased());
    }

    public function testGetApacheServiceNameAndConfig(): void
    {
        $this->detector->method('getOperatingSystem')->willReturn('AlmaLinux');
        $this->assertEquals('httpd', $this->detector->getApacheServiceName());
        $this->assertEquals('/etc/httpd', $this->detector->getApacheConfigPath());

        $debianDetector = $this->getMockBuilder(SystemDetector::class)
            ->setConstructorArgs([$this->loggerMock])
            ->onlyMethods(['getOperatingSystem'])
            ->getMock();
            
        $debianDetector->method('getOperatingSystem')->willReturn('Ubuntu');
        $this->assertEquals('apache2', $debianDetector->getApacheServiceName());
        $this->assertEquals('/etc/apache2', $debianDetector->getApacheConfigPath());
    }

    public function testPaths(): void
    {
        $detector = new SystemDetector($this->loggerMock);
        $this->assertEquals('/etc/systemd/system', $detector->getSystemdPath());
        $this->assertEquals('/var/log', $detector->getLogPath());
        $this->assertEquals('/tmp', $detector->getTempPath());
        $this->assertEquals('/etc', $detector->getEtcPath());
        $this->assertEquals('/usr/local', $detector->getUsrLocalPath());
        $this->assertEquals('/home', $detector->getHomePath());
    }

    public function testGetServiceCommand(): void
    {
        $detector = new SystemDetector($this->loggerMock);
        $this->assertEquals('systemctl restart httpd', $detector->getServiceCommand('httpd', 'restart'));
        $this->assertEquals('systemctl enable lsws', $detector->getServiceCommand('lsws', 'enable'));
    }
}
