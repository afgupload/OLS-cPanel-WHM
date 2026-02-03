<?php

declare(strict_types=1);

namespace OLScPanel\Services;

use OLScPanel\Utils\Logger;
use OLScPanel\Models\Domain;
use OLScPanel\Models\SslCertificate;
use OLScPanel\Config\ConfigManager;

class DomainService
{
    private Logger $logger;
    private ConfigManager $config;

    public function __construct(Logger $logger, ConfigManager $config)
    {
        $this->logger = $logger;
        $this->config = $config;
    }

    public function getAllDomains(): array
    {
        try {
            $result = $this->executeWhmApi('listaccts', [
                'api.version' => 1,
                'want' => 'domain'
            ]);

            $domains = [];
            if (isset($result['data']['acct'])) {
                foreach ($result['data']['acct'] as $account) {
                    $domain = new Domain([
                        'domain' => $account['domain'],
                        'user' => $account['user'],
                        'ip' => $account['ip'],
                        'owner' => $account['owner'],
                        'plan' => $account['plan'] ?? 'default',
                        'suspended' => $account['suspended'] === 1,
                        'setup_date' => $account['startdate'] ?? null
                    ]);
                    $domains[] = $domain;
                }
            }

            $this->logger->info('Retrieved domains from WHM', [
                'count' => count($domains)
            ]);

            return $domains;

        } catch (\Exception $e) {
            $this->logger->error('Failed to retrieve domains', [
                'error' => $e->getMessage()
            ]);
            throw new \RuntimeException("Failed to retrieve domains: {$e->getMessage()}");
        }
    }

    public function getDomain(string $domain): ?Domain
    {
        $domains = $this->getAllDomains();
        foreach ($domains as $d) {
            if ($d->getDomain() === $domain) {
                return $d;
            }
        }
        return null;
    }

    public function getDomainSslInfo(string $domain): ?SslCertificate
    {
        try {
            $result = $this->executeWhmApi('fetchsslinfo', [
                'api.version' => 1,
                'domain' => $domain
            ]);

            if (isset($result['data']['cert'])) {
                $certData = $result['data']['cert'];
                
                return new SslCertificate([
                    'domain' => $domain,
                    'certificate' => $certData['certificate'] ?? '',
                    'private_key' => $certData['key'] ?? '',
                    'ca_bundle' => $certData['cabundle'] ?? '',
                    'issuer' => $certData['issuer'] ?? '',
                    'expires_on' => $certData['expires_on'] ?? null,
                    'is_self_signed' => ($certData['is_self_signed'] ?? false) === 1,
                    'status' => $certData['status'] ?? 'unknown'
                ]);
            }

            return null;

        } catch (\Exception $e) {
            $this->logger->warning('Failed to get SSL info for domain', [
                'domain' => $domain,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    public function getDomainPhpVersion(string $domain): string
    {
        try {
            $result = $this->executeWhmApi('php_get_vhost_versions', [
                'api.version' => 1,
                'vhost' => $domain
            ]);

            if (isset($result['data']['version'])) {
                return $result['data']['version'];
            }

            return $this->config->get('php.default_version', '8.1');

        } catch (\Exception $e) {
            $this->logger->warning('Failed to get PHP version for domain', [
                'domain' => $domain,
                'error' => $e->getMessage()
            ]);
            return $this->config->get('php.default_version', '8.1');
        }
    }

    public function getSubdomains(string $mainDomain): array
    {
        try {
            $result = $this->executeWhmApi('listsubdomains', [
                'api.version' => 1,
                'domain' => $mainDomain
            ]);

            $subdomains = [];
            if (isset($result['data']['subdomain'])) {
                foreach ($result['data']['subdomain'] as $subdomain) {
                    $subdomains[] = [
                        'domain' => $subdomain['domain'],
                        'rootdomain' => $subdomain['rootdomain'],
                        'basedir' => $subdomain['basedir'],
                        'status' => $subdomain['status'] ?? 0
                    ];
                }
            }

            return $subdomains;

        } catch (\Exception $e) {
            $this->logger->error('Failed to get subdomains', [
                'main_domain' => $mainDomain,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    public function getAddonDomains(string $username): array
    {
        try {
            $result = $this->executeWhmApi('listaddondomains', [
                'api.version' => 1,
                'user' => $username
            ]);

            $addonDomains = [];
            if (isset($result['data']['addon'])) {
                foreach ($result['data']['addon'] as $addon) {
                    $addonDomains[] = [
                        'domain' => $addon['domain'],
                        'basedir' => $addon['basedir'],
                        'status' => $addon['status'] ?? 0
                    ];
                }
            }

            return $addonDomains;

        } catch (\Exception $e) {
            $this->logger->error('Failed to get addon domains', [
                'username' => $username,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    public function getParkedDomains(string $username): array
    {
        try {
            $result = $this->executeWhmApi('listparkeddomains', [
                'api.version' => 1,
                'user' => $username
            ]);

            $parkedDomains = [];
            if (isset($result['data']['parked'])) {
                foreach ($result['data']['parked'] as $parked) {
                    $parkedDomains[] = [
                        'domain' => $parked['domain'],
                        'basedir' => $parked['basedir'],
                        'status' => $parked['status'] ?? 0
                    ];
                }
            }

            return $parkedDomains;

        } catch (\Exception $e) {
            $this->logger->error('Failed to get parked domains', [
                'username' => $username,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    public function getDomainDocumentRoot(string $domain): string
    {
        try {
            $result = $this->executeWhmApi('domainuserdata', [
                'api.version' => 1,
                'domain' => $domain
            ]);

            if (isset($result['data']['documentroot'])) {
                return $result['data']['documentroot'];
            }

            return "/home/{$this->getDomainUser($domain)}/public_html";

        } catch (\Exception $e) {
            $this->logger->warning('Failed to get document root for domain', [
                'domain' => $domain,
                'error' => $e->getMessage()
            ]);
            return "/home/{$this->getDomainUser($domain)}/public_html";
        }
    }

    public function getDomainUser(string $domain): string
    {
        $domainObj = $this->getDomain($domain);
        return $domainObj ? $domainObj->getUser() : 'nobody';
    }

    public function isDomainSuspended(string $domain): bool
    {
        $domainObj = $this->getDomain($domain);
        return $domainObj ? $domainObj->isSuspended() : false;
    }

    public function getDomainIp(string $domain): string
    {
        $domainObj = $this->getDomain($domain);
        return $domainObj ? $domainObj->getIp() : '127.0.0.1';
    }

    public function getDomainPort(bool $ssl = false): int
    {
        return $ssl ? 443 : 80;
    }

    public function getAllDomainData(): array
    {
        $domains = $this->getAllDomains();
        $allData = [];

        foreach ($domains as $domain) {
            $domainName = $domain->getDomain();
            $username = $domain->getUser();

            $domainData = [
                'domain' => $domainName,
                'user' => $username,
                'ip' => $domain->getIp(),
                'document_root' => $this->getDomainDocumentRoot($domainName),
                'php_version' => $this->getDomainPhpVersion($domainName),
                'ssl_certificate' => $this->getDomainSslInfo($domainName),
                'subdomains' => $this->getSubdomains($domainName),
                'addon_domains' => $this->getAddonDomains($username),
                'parked_domains' => $this->getParkedDomains($username),
                'suspended' => $domain->isSuspended(),
                'ports' => [
                    'http' => $this->getDomainPort(false),
                    'https' => $this->getDomainPort(true)
                ]
            ];

            $allData[] = $domainData;
        }

        $this->logger->info('Retrieved complete domain data', [
            'count' => count($allData)
        ]);

        return $allData;
    }

    public function validateDomain(string $domain): array
    {
        $errors = [];

        if (empty($domain)) {
            $errors[] = 'Domain name cannot be empty';
            return $errors;
        }

        if (!filter_var($domain, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
            $errors[] = 'Invalid domain format';
        }

        if (strlen($domain) > 253) {
            $errors[] = 'Domain name too long';
        }

        if (!preg_match('/^[a-zA-Z0-9.-]+$/', $domain)) {
            $errors[] = 'Domain contains invalid characters';
        }

        if (strpos($domain, '..') !== false) {
            $errors[] = 'Domain cannot contain consecutive dots';
        }

        if (substr($domain, -1) === '.' || substr($domain, 0, 1) === '.') {
            $errors[] = 'Domain cannot start or end with a dot';
        }

        return $errors;
    }

    private function executeWhmApi(string $function, array $params = []): array
    {
        $command = 'whmapi1 ' . $function;
        
        foreach ($params as $key => $value) {
            if (is_bool($value)) {
                $value = $value ? '1' : '0';
            }
            $command .= " --output=json {$key}='{$value}'";
        }

        $output = shell_exec($command);
        if ($output === null) {
            throw new \RuntimeException("Failed to execute WHM API command: {$command}");
        }

        $result = json_decode($output, true);
        if ($result === null) {
            throw new \RuntimeException("Failed to parse WHM API response: {$output}");
        }

        if (isset($result['metadata']['result']) && $result['metadata']['result'] === 0) {
            throw new \RuntimeException("WHM API error: " . ($result['metadata']['reason'] ?? 'Unknown error'));
        }

        return $result;
    }

    public function refreshDomainCache(): void
    {
        $this->logger->info('Refreshing domain cache');
        
        try {
            $this->executeWhmApi('setup_user_session', [
                'api.version' => 1,
                'user' => 'root'
            ]);
            
            $this->logger->info('Domain cache refreshed successfully');
        } catch (\Exception $e) {
            $this->logger->error('Failed to refresh domain cache', [
                'error' => $e->getMessage()
            ]);
        }
    }
}
