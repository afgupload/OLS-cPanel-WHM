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
        $this->assertNull($cert->getSerialNumber());
        $this->assertNull($cert->getSignatureAlgorithm());
        $this->assertNull($cert->getKeySize());
        $this->assertEquals([], $cert->getSubjectAlternativeNames());
    }

    public function testGettersAndSetters(): void
    {
        $cert = new SslCertificate();
        
        $cert->setDomain('test.com');
        $this->assertEquals('test.com', $cert->getDomain());
        
        $cert->setCertificate('test-cert');
        $this->assertEquals('test-cert', $cert->getCertificate());

        $cert->setPrivateKey('test-key');
        $this->assertEquals('test-key', $cert->getPrivateKey());

        $cert->setCaBundle('test-ca');
        $this->assertEquals('test-ca', $cert->getCaBundle());

        $cert->setIssuer('Test Issuer');
        $this->assertEquals('Test Issuer', $cert->getIssuer());

        $cert->setExpiresOn('2025-12-31');
        $this->assertEquals('2025-12-31', $cert->getExpiresOn());

        $cert->setSelfSigned(true);
        $this->assertTrue($cert->isSelfSigned());

        $cert->setStatus('invalid');
        $this->assertEquals('invalid', $cert->getStatus());

        $cert->setSerialNumber('123456');
        $this->assertEquals('123456', $cert->getSerialNumber());

        $cert->setSignatureAlgorithm('sha256WithRSAEncryption');
        $this->assertEquals('sha256WithRSAEncryption', $cert->getSignatureAlgorithm());

        $cert->setKeySize('2048 bit');
        $this->assertEquals('2048 bit', $cert->getKeySize());

        $cert->setSubjectAlternativeNames(['test.com', 'www.test.com']);
        $this->assertEquals(['test.com', 'www.test.com'], $cert->getSubjectAlternativeNames());
    }

    public function testExpiredCertificate(): void
    {
        $cert = new SslCertificate(
            domain: 'example.com',
            expiresOn: '2020-01-01'
        );

        $this->assertTrue($cert->isExpired());
    }

    public function testNotExpiredCertificate(): void
    {
        $futureDate = (new \DateTime('+1 year'))->format('Y-m-d');
        $cert = new SslCertificate(
            domain: 'example.com',
            expiresOn: $futureDate
        );

        $this->assertFalse($cert->isExpired());
    }

    public function testIsExpiredWithNullDate(): void
    {
        $cert = new SslCertificate();
        $this->assertFalse($cert->isExpired());
    }

    public function testGetDaysUntilExpiration(): void
    {
        $cert = new SslCertificate();
        $this->assertNull($cert->getDaysUntilExpiration());

        $futureDate = (new \DateTime('+10 days'))->format('Y-m-d');
        $cert->setExpiresOn($futureDate);
        $this->assertEquals(10, $cert->getDaysUntilExpiration());

        $pastDate = (new \DateTime('-5 days'))->format('Y-m-d');
        $cert->setExpiresOn($pastDate);
        $this->assertEquals(-5, $cert->getDaysUntilExpiration());
    }

    public function testIsExpiringSoon(): void
    {
        $cert = new SslCertificate();
        $this->assertFalse($cert->isExpiringSoon());

        $futureDate = (new \DateTime('+15 days'))->format('Y-m-d');
        $cert->setExpiresOn($futureDate);
        $this->assertTrue($cert->isExpiringSoon(30));
        $this->assertFalse($cert->isExpiringSoon(10));

        $pastDate = (new \DateTime('-5 days'))->format('Y-m-d');
        $cert->setExpiresOn($pastDate);
        $this->assertFalse($cert->isExpiringSoon());
    }

    public function testIsValid(): void
    {
        $futureDate = (new \DateTime('+1 year'))->format('Y-m-d');
        $cert = new SslCertificate(
            certificate: 'cert',
            privateKey: 'key',
            expiresOn: $futureDate,
            isSelfSigned: false
        );
        $this->assertTrue($cert->isValid());

        $cert->setCertificate('');
        $this->assertFalse($cert->isValid());

        $cert->setCertificate('cert');
        $cert->setPrivateKey('');
        $this->assertFalse($cert->isValid());

        $cert->setPrivateKey('key');
        $cert->setSelfSigned(true);
        $this->assertFalse($cert->isValid());

        $cert->setSelfSigned(false);
        $cert->setExpiresOn('2020-01-01');
        $this->assertFalse($cert->isValid());
    }

    public function testIsLetsEncrypt(): void
    {
        $cert = new SslCertificate(issuer: 'Let\'s Encrypt Authority X3');
        $this->assertTrue($cert->isLetsEncrypt());

        $cert->setIssuer('R3');
        $this->assertTrue($cert->isLetsEncrypt());

        $cert->setIssuer('ISRG Root X1');
        $this->assertTrue($cert->isLetsEncrypt());

        $cert->setIssuer('DigiCert');
        $this->assertFalse($cert->isLetsEncrypt());
    }

    public function testIsCommercial(): void
    {
        $cert = new SslCertificate(issuer: 'DigiCert SHA2 Secure Server CA');
        $this->assertTrue($cert->isCommercial());

        $cert->setIssuer('GlobalSign');
        $this->assertTrue($cert->isCommercial());

        $cert->setIssuer('Let\'s Encrypt');
        $this->assertFalse($cert->isCommercial());
    }

    public function testGetCertificateType(): void
    {
        $cert = new SslCertificate(isSelfSigned: true);
        $this->assertEquals('self_signed', $cert->getCertificateType());

        $cert = new SslCertificate(issuer: 'Let\'s Encrypt');
        $this->assertEquals('lets_encrypt', $cert->getCertificateType());

        $cert = new SslCertificate(issuer: 'DigiCert');
        $this->assertEquals('commercial', $cert->getCertificateType());

        $cert = new SslCertificate(issuer: 'Unknown CA');
        $this->assertEquals('other', $cert->getCertificateType());
    }

    public function testGetExpirationStatusAndColor(): void
    {
        $cert = new SslCertificate();
        $this->assertEquals('unknown', $cert->getExpirationStatus());
        $this->assertEquals('gray', $cert->getExpirationStatusColor());

        $cert->setExpiresOn((new \DateTime('-1 day'))->format('Y-m-d'));
        $this->assertEquals('expired', $cert->getExpirationStatus());
        $this->assertEquals('red', $cert->getExpirationStatusColor());

        $cert->setExpiresOn((new \DateTime('+5 days'))->format('Y-m-d'));
        $this->assertEquals('critical', $cert->getExpirationStatus());
        $this->assertEquals('red', $cert->getExpirationStatusColor());

        $cert->setExpiresOn((new \DateTime('+20 days'))->format('Y-m-d'));
        $this->assertEquals('warning', $cert->getExpirationStatus());
        $this->assertEquals('orange', $cert->getExpirationStatusColor());

        $cert->setExpiresOn((new \DateTime('+60 days'))->format('Y-m-d'));
        $this->assertEquals('valid', $cert->getExpirationStatus());
        $this->assertEquals('green', $cert->getExpirationStatusColor());
    }

    public function testToArrayAndJsonSerialize(): void
    {
        $cert = new SslCertificate(
            domain: 'example.com',
            certificate: 'cert',
            privateKey: 'key',
            caBundle: 'ca',
            issuer: 'Let\'s Encrypt',
            expiresOn: '2025-01-01',
            isSelfSigned: false,
            status: 'valid',
            serialNumber: '123',
            signatureAlgorithm: 'sha256',
            keySize: '2048 bit',
            subjectAlternativeNames: ['example.com']
        );

        $expected = [
            'domain' => 'example.com',
            'certificate' => 'cert',
            'private_key' => 'key',
            'ca_bundle' => 'ca',
            'issuer' => 'Let\'s Encrypt',
            'expires_on' => '2025-01-01',
            'is_self_signed' => false,
            'status' => 'valid',
            'serial_number' => '123',
            'signature_algorithm' => 'sha256',
            'key_size' => '2048 bit',
            'subject_alternative_names' => ['example.com'],
            'is_expired' => $cert->isExpired(),
            'days_until_expiration' => $cert->getDaysUntilExpiration(),
            'is_expiring_soon' => $cert->isExpiringSoon(),
            'is_valid' => $cert->isValid(),
            'certificate_type' => 'lets_encrypt',
            'expiration_status' => $cert->getExpirationStatus(),
            'expiration_status_color' => $cert->getExpirationStatusColor()
        ];

        $this->assertEquals($expected, $cert->toArray());
        $this->assertEquals($expected, $cert->jsonSerialize());
    }

    public function testToString(): void
    {
        $cert = new SslCertificate(domain: 'example.com', issuer: 'Let\'s Encrypt');
        $this->assertEquals('example.com (lets_encrypt)', (string) $cert);
    }

    public function testGetFormattedExpirationDate(): void
    {
        $cert = new SslCertificate();
        $this->assertEquals('Unknown', $cert->getFormattedExpirationDate());

        $cert->setExpiresOn('2025-01-15');
        $this->assertEquals('Jan 15, 2025', $cert->getFormattedExpirationDate());
    }

    public function testGetRenewalRecommendation(): void
    {
        $cert = new SslCertificate();
        $this->assertEquals('Unable to determine expiration date', $cert->getRenewalRecommendation());

        $cert->setExpiresOn((new \DateTime('-1 day'))->format('Y-m-d'));
        $this->assertEquals('Certificate has expired. Immediate renewal required.', $cert->getRenewalRecommendation());

        $cert->setExpiresOn((new \DateTime('+5 days'))->format('Y-m-d'));
        $this->assertEquals('Certificate expires very soon. Renew immediately.', $cert->getRenewalRecommendation());

        $cert->setExpiresOn((new \DateTime('+20 days'))->format('Y-m-d'));
        $this->assertEquals('Certificate expires soon. Schedule renewal.', $cert->getRenewalRecommendation());

        $cert->setExpiresOn((new \DateTime('+60 days'))->format('Y-m-d'));
        $this->assertEquals('Certificate is valid. No immediate action needed.', $cert->getRenewalRecommendation());
    }
}
