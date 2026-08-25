<?php

declare(strict_types=1);

namespace OLScPanel\Tests\Models;

use OLScPanel\Models\SslCertificate;
use PHPUnit\Framework\TestCase;

class SslCertificateTest extends TestCase
{
    public function testCertificateCreation(): void
    {
        $cert = new SslCertificate(
            domain: 'example.com',
            certificate: 'cert-content',
            privateKey: 'key-content',
            caBundle: 'ca-content',
            issuer: 'Let\'s Encrypt',
            expiresOn: '2025-01-01',
            isSelfSigned: false,
            status: 'valid'
        );

        $this->assertEquals('example.com', $cert->getDomain());
        $this->assertEquals('cert-content', $cert->getCertificate());
        $this->assertEquals('key-content', $cert->getPrivateKey());
        $this->assertEquals('ca-content', $cert->getCaBundle());
        $this->assertEquals('Let\'s Encrypt', $cert->getIssuer());
        $this->assertEquals('2025-01-01', $cert->getExpiresOn());
        $this->assertFalse($cert->isSelfSigned());
        $this->assertEquals('valid', $cert->getStatus());
    }

    public function testExpiredCertificate(): void
    {
        $cert = new SslCertificate(
            domain: 'example.com',
            expiresOn: '2020-01-01'
        );

        $this->assertTrue($cert->isExpired());
    }
}
