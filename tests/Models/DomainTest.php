<?php

declare(strict_types=1);

namespace OLScPanel\Tests\Models;

use OLScPanel\Models\Domain;
use OLScPanel\Models\SslCertificate;
use PHPUnit\Framework\TestCase;

class DomainTest extends TestCase
{
    private Domain $domain;

    protected function setUp(): void
    {
        $this->domain = new Domain(
            domain: 'example.com',
            user: 'testuser',
            ip: '127.0.0.1',
            owner: 'root',
            plan: 'default',
            suspended: false
        );
    }

    public function testConstructorInitializesPropertiesCorrectly(): void
    {
        $this->assertEquals('example.com', $this->domain->getDomain());
        $this->assertEquals('testuser', $this->domain->getUser());
        $this->assertEquals('127.0.0.1', $this->domain->getIp());
        $this->assertEquals('root', $this->domain->getOwner());
        $this->assertEquals('default', $this->domain->getPlan());
        $this->assertFalse($this->domain->isSuspended());
    }

    public function testGettersAndSetters(): void
    {
        $this->domain->setDomain('newdomain.com');
        $this->assertEquals('newdomain.com', $this->domain->getDomain());

        $this->domain->setUser('newuser');
        $this->assertEquals('newuser', $this->domain->getUser());

        $this->domain->setIp('192.168.1.1');
        $this->assertEquals('192.168.1.1', $this->domain->getIp());

        $this->domain->setOwner('admin');
        $this->assertEquals('admin', $this->domain->getOwner());

        $this->domain->setPlan('premium');
        $this->assertEquals('premium', $this->domain->getPlan());

        $this->domain->setSuspended(true);
        $this->assertTrue($this->domain->isSuspended());
        
        $this->domain->setSetupDate('2023-01-01');
        $this->assertEquals('2023-01-01', $this->domain->getSetupDate());

        $this->domain->setDocumentRoot('/home/newuser/public_html');
        $this->assertEquals('/home/newuser/public_html', $this->domain->getDocumentRoot());

        $this->domain->setPhpVersion('8.1');
        $this->assertEquals('8.1', $this->domain->getPhpVersion());

        $subdomains = ['sub1.example.com', 'sub2.example.com'];
        $this->domain->setSubdomains($subdomains);
        $this->assertEquals($subdomains, $this->domain->getSubdomains());

        $addonDomains = ['addon1.com', 'addon2.com'];
        $this->domain->setAddonDomains($addonDomains);
        $this->assertEquals($addonDomains, $this->domain->getAddonDomains());

        $parkedDomains = ['parked1.com', 'parked2.com'];
        $this->domain->setParkedDomains($parkedDomains);
        $this->assertEquals($parkedDomains, $this->domain->getParkedDomains());
    }

    public function testSslCertificateMethods(): void
    {
        $this->assertNull($this->domain->getSslCertificate());
        $this->assertFalse($this->domain->hasSsl());
        $this->assertFalse($this->domain->isSslValid());
        $this->assertNull($this->domain->getSslExpiresInDays());
        
        $sslMock = $this->createMock(SslCertificate::class);
        $sslMock->method('isExpired')->willReturn(false);
        $sslMock->method('isValid')->willReturn(true);
        $sslMock->method('getDaysUntilExpiration')->willReturn(30);
        $sslMock->method('toArray')->willReturn(['mocked' => 'data']);
        
        $this->domain->setSslCertificate($sslMock);
        
        $this->assertSame($sslMock, $this->domain->getSslCertificate());
        $this->assertTrue($this->domain->hasSsl());
        $this->assertTrue($this->domain->isSslValid());
        $this->assertEquals(30, $this->domain->getSslExpiresInDays());
        $this->assertFalse($this->domain->isExpired());
    }

    public function testIsMainDomain(): void
    {
        $this->assertTrue($this->domain->isMainDomain());

        $this->domain->setDomain('sub.example.com');
        $this->assertFalse($this->domain->isMainDomain());
        
        $this->domain->setDomain('localhost');
        $this->assertTrue($this->domain->isMainDomain());
    }

    public function testGetSubdomainLevel(): void
    {
        $this->assertEquals(0, $this->domain->getSubdomainLevel());

        $this->domain->setDomain('sub.example.com');
        $this->assertEquals(1, $this->domain->getSubdomainLevel());

        $this->domain->setDomain('deep.sub.example.com');
        $this->assertEquals(2, $this->domain->getSubdomainLevel());
        
        $this->domain->setDomain('localhost');
        $this->assertEquals(0, $this->domain->getSubdomainLevel());
    }

    public function testToArrayAndJsonSerialize(): void
    {
        $expected = [
            'domain' => 'example.com',
            'user' => 'testuser',
            'ip' => '127.0.0.1',
            'owner' => 'root',
            'plan' => 'default',
            'suspended' => false,
            'setup_date' => null,
            'document_root' => null,
            'php_version' => null,
            'ssl_certificate' => null,
            'subdomains' => [],
            'addon_domains' => [],
            'parked_domains' => [],
            'has_ssl' => false,
            'is_ssl_valid' => false,
            'ssl_expires_in_days' => null,
            'is_main_domain' => true,
            'subdomain_level' => 0
        ];

        $this->assertEquals($expected, $this->domain->toArray());
        $this->assertEquals($expected, $this->domain->jsonSerialize());
    }

    public function testToString(): void
    {
        $this->assertEquals('example.com', (string) $this->domain);
    }

    public function testEquals(): void
    {
        $otherDomain = new Domain('example.com', 'testuser');
        $this->assertTrue($this->domain->equals($otherDomain));

        $differentDomain = new Domain('different.com', 'testuser');
        $this->assertFalse($this->domain->equals($differentDomain));

        $differentUserDomain = new Domain('example.com', 'differentuser');
        $this->assertFalse($this->domain->equals($differentUserDomain));
    }

    public function testValidate(): void
    {
        $this->assertEmpty($this->domain->validate());

        $invalidDomain = new Domain('', '', 'invalid-ip');
        $errors = $invalidDomain->validate();

        $this->assertContains('Domain name is required', $errors);
        $this->assertContains('User is required', $errors);
        $this->assertContains('Invalid IP address format', $errors);
        
        $invalidFormatDomain = new Domain('invalid domain', 'user', '127.0.0.1');
        $errors = $invalidFormatDomain->validate();
        $this->assertContains('Invalid domain format', $errors);
        
        $missingIpDomain = new Domain('example.com', 'user', '');
        $errors = $missingIpDomain->validate();
        $this->assertContains('IP address is required', $errors);
    }

    public function testDirectoryPaths(): void
    {
        $this->assertEquals('/home/testuser', $this->domain->getHomeDirectory());
        $this->assertEquals('/home/testuser/public_html', $this->domain->getPublicHtmlPath());
        
        $this->assertEquals('/home/testuser/public_html', $this->domain->getFullDocumentRoot());
        
        $this->domain->setDocumentRoot('/custom/path');
        $this->assertEquals('/custom/path', $this->domain->getFullDocumentRoot());
    }

    public function testHasDomainsMethods(): void
    {
        $this->assertFalse($this->domain->hasSubdomains());
        $this->assertFalse($this->domain->hasAddonDomains());
        $this->assertFalse($this->domain->hasParkedDomains());

        $this->domain->setSubdomains(['sub.com']);
        $this->assertTrue($this->domain->hasSubdomains());

        $this->domain->setAddonDomains(['addon.com']);
        $this->assertTrue($this->domain->hasAddonDomains());

        $this->domain->setParkedDomains(['parked.com']);
        $this->assertTrue($this->domain->hasParkedDomains());
    }

    public function testGetTotalDomains(): void
    {
        $this->assertEquals(1, $this->domain->getTotalDomains());

        $this->domain->setSubdomains(['sub1.com', 'sub2.com']);
        $this->domain->setAddonDomains(['addon1.com']);
        $this->domain->setParkedDomains(['parked1.com', 'parked2.com', 'parked3.com']);

        $this->assertEquals(7, $this->domain->getTotalDomains()); // 1 main + 2 sub + 1 addon + 3 parked
    }

    public function testGetDomainType(): void
    {
        $this->assertEquals('main', $this->domain->getDomainType());

        $this->domain->setDomain('sub.example.com');
        $this->assertEquals('subdomain', $this->domain->getDomainType());

        // This behavior is weird, if it's not a subdomain and not main domain, it's an addon? 
        // Based on the code `isMainDomain()` logic: `!str_contains($this->domain, '.') || count(explode('.', $this->domain)) === 2`
        // It always falls into main or subdomain
        // Wait, if it's count === 2, it's main. If count > 2, it's subdomain. 
        // How can it be 'addon'? Maybe it can't.
        // I will write test based on current code logic. 
        // Actually, the `getDomainType()` relies on logic that doesn't seem to reach 'addon' if it's just based on string parts unless domain doesn't contain '.' ? No, if it doesn't contain '.', it's main.
        // So addon is unreachable in Domain::getDomainType() using Domain's string logic, but let's just cover main and subdomain.
    }

    public function testGetDisplayName(): void
    {
        $this->assertEquals('🏠 example.com', $this->domain->getDisplayName());

        $this->domain->setDomain('sub.example.com');
        $this->assertEquals('🔗 sub.example.com', $this->domain->getDisplayName());
    }

    public function testGetStatusAndColor(): void
    {
        $this->assertEquals('active', $this->domain->getStatus());
        $this->assertEquals('blue', $this->domain->getStatusColor());

        $this->domain->setSuspended(true);
        $this->assertEquals('suspended', $this->domain->getStatus());
        $this->assertEquals('red', $this->domain->getStatusColor());

        $this->domain->setSuspended(false);
        $sslMock = $this->createMock(SslCertificate::class);
        $sslMock->method('isExpired')->willReturn(true);
        $this->domain->setSslCertificate($sslMock);
        
        $this->assertEquals('ssl_expired', $this->domain->getStatus());
        $this->assertEquals('orange', $this->domain->getStatusColor());
        
        $sslMock = $this->createMock(SslCertificate::class);
        $sslMock->method('isExpired')->willReturn(false);
        $sslMock->method('isValid')->willReturn(true);
        $this->domain->setSslCertificate($sslMock);
        
        $this->assertEquals('ssl_active', $this->domain->getStatus());
        $this->assertEquals('green', $this->domain->getStatusColor());
    }
}
