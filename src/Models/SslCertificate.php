<?php

declare(strict_types=1);

namespace OLScPanel\Models;

use JsonSerializable;

class SslCertificate implements JsonSerializable
{
    private string $domain;
    private string $certificate;
    private string $privateKey;
    private string $caBundle;
    private string $issuer;
    private ?string $expiresOn;
    private bool $isSelfSigned;
    private string $status;
    private ?string $serialNumber;
    private ?string $signatureAlgorithm;
    private ?string $keySize;
    private array $subjectAlternativeNames;

    public function __construct(array $data)
    {
        $this->domain = $data['domain'] ?? '';
        $this->certificate = $data['certificate'] ?? '';
        $this->privateKey = $data['private_key'] ?? '';
        $this->caBundle = $data['ca_bundle'] ?? '';
        $this->issuer = $data['issuer'] ?? '';
        $this->expiresOn = $data['expires_on'] ?? null;
        $this->isSelfSigned = (bool)($data['is_self_signed'] ?? false);
        $this->status = $data['status'] ?? 'unknown';
        $this->serialNumber = $data['serial_number'] ?? null;
        $this->signatureAlgorithm = $data['signature_algorithm'] ?? null;
        $this->keySize = $data['key_size'] ?? null;
        $this->subjectAlternativeNames = $data['subject_alternative_names'] ?? [];
    }

    public function getDomain(): string
    {
        return $this->domain;
    }

    public function setDomain(string $domain): void
    {
        $this->domain = $domain;
    }

    public function getCertificate(): string
    {
        return $this->certificate;
    }

    public function setCertificate(string $certificate): void
    {
        $this->certificate = $certificate;
    }

    public function getPrivateKey(): string
    {
        return $this->privateKey;
    }

    public function setPrivateKey(string $privateKey): void
    {
        $this->privateKey = $privateKey;
    }

    public function getCaBundle(): string
    {
        return $this->caBundle;
    }

    public function setCaBundle(string $caBundle): void
    {
        $this->caBundle = $caBundle;
    }

    public function getIssuer(): string
    {
        return $this->issuer;
    }

    public function setIssuer(string $issuer): void
    {
        $this->issuer = $issuer;
    }

    public function getExpiresOn(): ?string
    {
        return $this->expiresOn;
    }

    public function setExpiresOn(?string $expiresOn): void
    {
        $this->expiresOn = $expiresOn;
    }

    public function isSelfSigned(): bool
    {
        return $this->isSelfSigned;
    }

    public function setSelfSigned(bool $isSelfSigned): void
    {
        $this->isSelfSigned = $isSelfSigned;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): void
    {
        $this->status = $status;
    }

    public function getSerialNumber(): ?string
    {
        return $this->serialNumber;
    }

    public function setSerialNumber(?string $serialNumber): void
    {
        $this->serialNumber = $serialNumber;
    }

    public function getSignatureAlgorithm(): ?string
    {
        return $this->signatureAlgorithm;
    }

    public function setSignatureAlgorithm(?string $signatureAlgorithm): void
    {
        $this->signatureAlgorithm = $signatureAlgorithm;
    }

    public function getKeySize(): ?string
    {
        return $this->keySize;
    }

    public function setKeySize(?string $keySize): void
    {
        $this->keySize = $keySize;
    }

    public function getSubjectAlternativeNames(): array
    {
        return $this->subjectAlternativeNames;
    }

    public function setSubjectAlternativeNames(array $subjectAlternativeNames): void
    {
        $this->subjectAlternativeNames = $subjectAlternativeNames;
    }

    public function isExpired(): bool
    {
        if (!$this->expiresOn) {
            return false;
        }

        $expiryDate = new \DateTime($this->expiresOn);
        $now = new \DateTime();
        
        return $expiryDate < $now;
    }

    public function getDaysUntilExpiration(): ?int
    {
        if (!$this->expiresOn) {
            return null;
        }

        $expiryDate = new \DateTime($this->expiresOn);
        $now = new \DateTime();
        
        $interval = $now->diff($expiryDate);
        return (int)$interval->format('%r%a');
    }

    public function isExpiringSoon(int $days = 30): bool
    {
        $daysUntil = $this->getDaysUntilExpiration();
        return $daysUntil !== null && $daysUntil <= $days && $daysUntil > 0;
    }

    public function isValid(): bool
    {
        return !empty($this->certificate) && 
               !empty($this->privateKey) && 
               !$this->isExpired() && 
               !$this->isSelfSigned;
    }

    public function isLetsEncrypt(): bool
    {
        return str_contains($this->issuer, 'Let\'s Encrypt') || 
               str_contains($this->issuer, 'R3') || 
               str_contains($this->issuer, 'ISRG');
    }

    public function isCommercial(): bool
    {
        $commercialIssuers = [
            'DigiCert', 'Comodo', 'GlobalSign', 'Sectigo', 
            'GeoTrust', 'Thawte', 'RapidSSL', 'Symantec'
        ];

        foreach ($commercialIssuers as $issuer) {
            if (str_contains($this->issuer, $issuer)) {
                return true;
            }
        }

        return false;
    }

    public function getCertificateType(): string
    {
        if ($this->isSelfSigned()) {
            return 'self_signed';
        } elseif ($this->isLetsEncrypt()) {
            return 'lets_encrypt';
        } elseif ($this->isCommercial()) {
            return 'commercial';
        } else {
            return 'other';
        }
    }

    public function getExpirationStatus(): string
    {
        $daysUntil = $this->getDaysUntilExpiration();
        
        if ($daysUntil === null) {
            return 'unknown';
        } elseif ($daysUntil < 0) {
            return 'expired';
        } elseif ($daysUntil <= 7) {
            return 'critical';
        } elseif ($daysUntil <= 30) {
            return 'warning';
        } else {
            return 'valid';
        }
    }

    public function getExpirationStatusColor(): string
    {
        switch ($this->getExpirationStatus()) {
            case 'expired':
            case 'critical':
                return 'red';
            case 'warning':
                return 'orange';
            case 'valid':
                return 'green';
            default:
                return 'gray';
        }
    }

    public function parseCertificate(): array
    {
        if (empty($this->certificate)) {
            return [];
        }

        $certData = [];
        
        $tempCertFile = tempnam(sys_get_temp_dir(), 'cert_');
        file_put_contents($tempCertFile, $this->certificate);

        $output = shell_exec("openssl x509 -in {$tempCertFile} -noout -text 2>/dev/null");
        unlink($tempCertFile);

        if ($output) {
            if (preg_match('/Issuer:\s*(.+)/', $output, $matches)) {
                $this->issuer = trim($matches[1]);
            }

            if (preg_match('/Not After\s*:\s*(.+)/', $output, $matches)) {
                $this->expiresOn = trim($matches[1]);
            }

            if (preg_match('/Serial Number:\s*(.+)/', $output, $matches)) {
                $this->serialNumber = trim($matches[1]);
            }

            if (preg_match('/Signature Algorithm:\s*(.+)/', $output, $matches)) {
                $this->signatureAlgorithm = trim($matches[1]);
            }

            if (preg_match('/Public-Key:\s*\((\d+) bit\)/', $output, $matches)) {
                $this->keySize = $matches[1] . ' bit';
            }

            if (preg_match_all('/DNS:([^\s,]+)/', $output, $matches)) {
                $this->subjectAlternativeNames = $matches[1];
            }
        }

        return $certData;
    }

    public function validateCertificate(): array
    {
        $errors = [];

        if (empty($this->certificate)) {
            $errors[] = 'Certificate is empty';
        }

        if (empty($this->privateKey)) {
            $errors[] = 'Private key is empty';
        }

        if (!empty($this->certificate) && !empty($this->privateKey)) {
            $tempCertFile = tempnam(sys_get_temp_dir(), 'cert_');
            $tempKeyFile = tempnam(sys_get_temp_dir(), 'key_');
            
            file_put_contents($tempCertFile, $this->certificate);
            file_put_contents($tempKeyFile, $this->privateKey);

            $output = shell_exec("openssl x509 -noout -modulus -in {$tempCertFile} 2>/dev/null");
            $keyOutput = shell_exec("openssl rsa -noout -modulus -in {$tempKeyFile} 2>/dev/null");

            unlink($tempCertFile);
            unlink($tempKeyFile);

            if ($output !== $keyOutput) {
                $errors[] = 'Certificate and private key do not match';
            }
        }

        if ($this->isExpired()) {
            $errors[] = 'Certificate has expired';
        }

        if ($this->isSelfSigned()) {
            $errors[] = 'Certificate is self-signed';
        }

        return $errors;
    }

    public function toArray(): array
    {
        return [
            'domain' => $this->domain,
            'certificate' => $this->certificate,
            'private_key' => $this->privateKey,
            'ca_bundle' => $this->caBundle,
            'issuer' => $this->issuer,
            'expires_on' => $this->expiresOn,
            'is_self_signed' => $this->isSelfSigned,
            'status' => $this->status,
            'serial_number' => $this->serialNumber,
            'signature_algorithm' => $this->signatureAlgorithm,
            'key_size' => $this->keySize,
            'subject_alternative_names' => $this->subjectAlternativeNames,
            'is_expired' => $this->isExpired(),
            'days_until_expiration' => $this->getDaysUntilExpiration(),
            'is_expiring_soon' => $this->isExpiringSoon(),
            'is_valid' => $this->isValid(),
            'certificate_type' => $this->getCertificateType(),
            'expiration_status' => $this->getExpirationStatus(),
            'expiration_status_color' => $this->getExpirationStatusColor()
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public function __toString(): string
    {
        return $this->domain . ' (' . $this->getCertificateType() . ')';
    }

    public function getFormattedExpirationDate(): string
    {
        if (!$this->expiresOn) {
            return 'Unknown';
        }

        $date = new \DateTime($this->expiresOn);
        return $date->format('M j, Y');
    }

    public function getRenewalRecommendation(): string
    {
        $daysUntil = $this->getDaysUntilExpiration();
        
        if ($daysUntil === null) {
            return 'Unable to determine expiration date';
        }

        if ($daysUntil < 0) {
            return 'Certificate has expired. Immediate renewal required.';
        } elseif ($daysUntil <= 7) {
            return 'Certificate expires very soon. Renew immediately.';
        } elseif ($daysUntil <= 30) {
            return 'Certificate expires soon. Schedule renewal.';
        } else {
            return 'Certificate is valid. No immediate action needed.';
        }
    }
}
