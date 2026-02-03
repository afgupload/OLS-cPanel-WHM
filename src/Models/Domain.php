<?php

declare(strict_types=1);

namespace OLScPanel\Models;

use JsonSerializable;

class Domain implements JsonSerializable
{
    private string $domain;
    private string $user;
    private string $ip;
    private string $owner;
    private string $plan;
    private bool $suspended;
    private ?string $setupDate;
    private ?string $documentRoot;
    private ?string $phpVersion;
    private ?SslCertificate $sslCertificate;
    private array $subdomains;
    private array $addonDomains;
    private array $parkedDomains;

    public function __construct(array $data)
    {
        $this->domain = $data['domain'] ?? '';
        $this->user = $data['user'] ?? '';
        $this->ip = $data['ip'] ?? '';
        $this->owner = $data['owner'] ?? '';
        $this->plan = $data['plan'] ?? 'default';
        $this->suspended = (bool)($data['suspended'] ?? false);
        $this->setupDate = $data['setup_date'] ?? null;
        $this->documentRoot = $data['document_root'] ?? null;
        $this->phpVersion = $data['php_version'] ?? null;
        $this->sslCertificate = $data['ssl_certificate'] ?? null;
        $this->subdomains = $data['subdomains'] ?? [];
        $this->addonDomains = $data['addon_domains'] ?? [];
        $this->parkedDomains = $data['parked_domains'] ?? [];
    }

    public function getDomain(): string
    {
        return $this->domain;
    }

    public function setDomain(string $domain): void
    {
        $this->domain = $domain;
    }

    public function getUser(): string
    {
        return $this->user;
    }

    public function setUser(string $user): void
    {
        $this->user = $user;
    }

    public function getIp(): string
    {
        return $this->ip;
    }

    public function setIp(string $ip): void
    {
        $this->ip = $ip;
    }

    public function getOwner(): string
    {
        return $this->owner;
    }

    public function setOwner(string $owner): void
    {
        $this->owner = $owner;
    }

    public function getPlan(): string
    {
        return $this->plan;
    }

    public function setPlan(string $plan): void
    {
        $this->plan = $plan;
    }

    public function isSuspended(): bool
    {
        return $this->suspended;
    }

    public function setSuspended(bool $suspended): void
    {
        $this->suspended = $suspended;
    }

    public function getSetupDate(): ?string
    {
        return $this->setupDate;
    }

    public function setSetupDate(?string $setupDate): void
    {
        $this->setupDate = $setupDate;
    }

    public function getDocumentRoot(): ?string
    {
        return $this->documentRoot;
    }

    public function setDocumentRoot(?string $documentRoot): void
    {
        $this->documentRoot = $documentRoot;
    }

    public function getPhpVersion(): ?string
    {
        return $this->phpVersion;
    }

    public function setPhpVersion(?string $phpVersion): void
    {
        $this->phpVersion = $phpVersion;
    }

    public function getSslCertificate(): ?SslCertificate
    {
        return $this->sslCertificate;
    }

    public function setSslCertificate(?SslCertificate $sslCertificate): void
    {
        $this->sslCertificate = $sslCertificate;
    }

    public function getSubdomains(): array
    {
        return $this->subdomains;
    }

    public function setSubdomains(array $subdomains): void
    {
        $this->subdomains = $subdomains;
    }

    public function getAddonDomains(): array
    {
        return $this->addonDomains;
    }

    public function setAddonDomains(array $addonDomains): void
    {
        $this->addonDomains = $addonDomains;
    }

    public function getParkedDomains(): array
    {
        return $this->parkedDomains;
    }

    public function setParkedDomains(array $parkedDomains): void
    {
        $this->parkedDomains = $parkedDomains;
    }

    public function hasSsl(): bool
    {
        return $this->sslCertificate !== null && !$this->sslCertificate->isExpired();
    }

    public function isSslValid(): bool
    {
        return $this->hasSsl() && $this->sslCertificate->isValid();
    }

    public function getSslExpiresInDays(): ?int
    {
        return $this->sslCertificate ? $this->sslCertificate->getDaysUntilExpiration() : null;
    }

    public function isMainDomain(): bool
    {
        return !str_contains($this->domain, '.') || 
               count(explode('.', $this->domain)) === 2;
    }

    public function getSubdomainLevel(): int
    {
        $parts = explode('.', $this->domain);
        return max(0, count($parts) - 2);
    }

    public function toArray(): array
    {
        return [
            'domain' => $this->domain,
            'user' => $this->user,
            'ip' => $this->ip,
            'owner' => $this->owner,
            'plan' => $this->plan,
            'suspended' => $this->suspended,
            'setup_date' => $this->setupDate,
            'document_root' => $this->documentRoot,
            'php_version' => $this->phpVersion,
            'ssl_certificate' => $this->sslCertificate ? $this->sslCertificate->toArray() : null,
            'subdomains' => $this->subdomains,
            'addon_domains' => $this->addonDomains,
            'parked_domains' => $this->parkedDomains,
            'has_ssl' => $this->hasSsl(),
            'is_ssl_valid' => $this->isSslValid(),
            'ssl_expires_in_days' => $this->getSslExpiresInDays(),
            'is_main_domain' => $this->isMainDomain(),
            'subdomain_level' => $this->getSubdomainLevel()
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public function __toString(): string
    {
        return $this->domain;
    }

    public function equals(Domain $other): bool
    {
        return $this->domain === $other->getDomain() && 
               $this->user === $other->getUser();
    }

    public function validate(): array
    {
        $errors = [];

        if (empty($this->domain)) {
            $errors[] = 'Domain name is required';
        } elseif (!filter_var($this->domain, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
            $errors[] = 'Invalid domain format';
        }

        if (empty($this->user)) {
            $errors[] = 'User is required';
        }

        if (empty($this->ip)) {
            $errors[] = 'IP address is required';
        } elseif (!filter_var($this->ip, FILTER_VALIDATE_IP)) {
            $errors[] = 'Invalid IP address format';
        }

        return $errors;
    }

    public function getHomeDirectory(): string
    {
        return "/home/{$this->user}";
    }

    public function getPublicHtmlPath(): string
    {
        return $this->getHomeDirectory() . '/public_html';
    }

    public function getFullDocumentRoot(): string
    {
        return $this->documentRoot ?? $this->getPublicHtmlPath();
    }

    public function hasSubdomains(): bool
    {
        return !empty($this->subdomains);
    }

    public function hasAddonDomains(): bool
    {
        return !empty($this->addonDomains);
    }

    public function hasParkedDomains(): bool
    {
        return !empty($this->parkedDomains);
    }

    public function getTotalDomains(): int
    {
        return 1 + count($this->subdomains) + count($this->addonDomains) + count($this->parkedDomains);
    }

    public function isExpired(): bool
    {
        return $this->sslCertificate && $this->sslCertificate->isExpired();
    }

    public function getDomainType(): string
    {
        if ($this->isMainDomain()) {
            return 'main';
        } elseif ($this->getSubdomainLevel() > 0) {
            return 'subdomain';
        } else {
            return 'addon';
        }
    }

    public function getDisplayName(): string
    {
        $type = $this->getDomainType();
        $prefix = '';
        
        switch ($type) {
            case 'main':
                $prefix = '🏠 ';
                break;
            case 'subdomain':
                $prefix = '🔗 ';
                break;
            case 'addon':
                $prefix = '➕ ';
                break;
        }

        return $prefix . $this->domain;
    }

    public function getStatus(): string
    {
        if ($this->suspended) {
            return 'suspended';
        } elseif ($this->isExpired()) {
            return 'ssl_expired';
        } elseif ($this->hasSsl()) {
            return 'ssl_active';
        } else {
            return 'active';
        }
    }

    public function getStatusColor(): string
    {
        switch ($this->getStatus()) {
            case 'suspended':
                return 'red';
            case 'ssl_expired':
                return 'orange';
            case 'ssl_active':
                return 'green';
            default:
                return 'blue';
        }
    }
}
