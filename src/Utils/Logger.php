<?php

declare(strict_types=1);

namespace OLScPanel\Utils;

use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\ErrorLogHandler;

class OLSLogger
{
    private Logger $logger;
    private string $logDir;
    private string $logFile;

    public function __construct(string $name = 'ols-cpanel', string $logDir = '/var/log/ols-cpanel')
    {
        $this->logDir = $logDir;
        $this->logFile = $logDir . '/ols-cpanel.log';
        
        if (!is_dir($this->logDir)) {
            mkdir($this->logDir, 0755, true);
        }

        $this->logger = new Logger($name);
        $this->setupHandlers();
    }

    private function setupHandlers(): void
    {
        $dateFormat = 'Y-m-d H:i:s';
        $output = "[%datetime%] [%level_name%] %message% %context% %extra%\n";
        $formatter = new LineFormatter($output, $dateFormat);

        $rotatingHandler = new RotatingFileHandler(
            $this->logFile,
            30,
            Logger::DEBUG,
            true,
            0644
        );
        $rotatingHandler->setFormatter($formatter);
        $this->logger->pushHandler($rotatingHandler);

        $errorHandler = new ErrorLogHandler(ErrorLogHandler::OPERATING_SYSTEM, Logger::WARNING);
        $errorHandler->setFormatter($formatter);
        $this->logger->pushHandler($errorHandler);

        $accessLogFile = $this->logDir . '/access.log';
        $accessHandler = new RotatingFileHandler(
            $accessLogFile,
            30,
            Logger::INFO,
            true,
            0644
        );
        $accessHandler->setFormatter($formatter);
        $this->logger->pushHandler($accessHandler);
    }

    public function debug(string $message, array $context = []): void
    {
        $this->logger->debug($message, $context);
    }

    public function info(string $message, array $context = []): void
    {
        $this->logger->info($message, $context);
    }

    public function notice(string $message, array $context = []): void
    {
        $this->logger->notice($message, $context);
    }

    public function warning(string $message, array $context = []): void
    {
        $this->logger->warning($message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->logger->error($message, $context);
    }

    public function critical(string $message, array $context = []): void
    {
        $this->logger->critical($message, $context);
    }

    public function alert(string $message, array $context = []): void
    {
        $this->logger->alert($message, $context);
    }

    public function emergency(string $message, array $context = []): void
    {
        $this->logger->emergency($message, $context);
    }

    public function logAccess(string $message, array $context = []): void
    {
        $this->logger->info($message, array_merge($context, ['type' => 'access']));
    }

    public function logConfiguration(string $message, array $context = []): void
    {
        $this->logger->info($message, array_merge($context, ['type' => 'configuration']));
    }

    public function logPerformance(string $message, array $context = []): void
    {
        $this->logger->info($message, array_merge($context, ['type' => 'performance']));
    }

    public function logSecurity(string $message, array $context = []): void
    {
        $this->logger->warning($message, array_merge($context, ['type' => 'security']));
    }

    public function logApi(string $message, array $context = []): void
    {
        $this->logger->info($message, array_merge($context, ['type' => 'api']));
    }

    public function logDomain(string $domain, string $action, array $context = []): void
    {
        $this->logger->info("Domain {$action}: {$domain}", array_merge($context, [
            'domain' => $domain,
            'action' => $action,
            'type' => 'domain'
        ]));
    }

    public function logSsl(string $domain, string $action, array $context = []): void
    {
        $this->logger->info("SSL {$action}: {$domain}", array_merge($context, [
            'domain' => $domain,
            'action' => $action,
            'type' => 'ssl'
        ]));
    }

    public function logService(string $service, string $action, array $context = []): void
    {
        $this->logger->info("Service {$action}: {$service}", array_merge($context, [
            'service' => $service,
            'action' => $action,
            'type' => 'service'
        ]));
    }

    public function logSystem(string $message, array $context = []): void
    {
        $this->logger->info($message, array_merge($context, ['type' => 'system']));
    }

    public function logException(\Throwable $exception, array $context = []): void
    {
        $this->logger->error($exception->getMessage(), array_merge($context, [
            'exception' => get_class($exception),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString(),
            'type' => 'exception'
        ]));
    }

    public function logCommand(string $command, string $output, int $exitCode, array $context = []): void
    {
        $level = $exitCode === 0 ? 'info' : 'error';
        $this->logger->log($level, "Command executed: {$command}", array_merge($context, [
            'command' => $command,
            'output' => $output,
            'exit_code' => $exitCode,
            'type' => 'command'
        ]));
    }

    public function logWhmApi(string $function, array $params, $result, array $context = []): void
    {
        $this->logger->info("WHM API call: {$function}", array_merge($context, [
            'function' => $function,
            'params' => $params,
            'result_type' => gettype($result),
            'type' => 'whm_api'
        ]));
    }

    public function logMigration(string $from, string $to, array $context = []): void
    {
        $this->logger->info("Migration: {$from} -> {$to}", array_merge($context, [
            'from' => $from,
            'to' => $to,
            'type' => 'migration'
        ]));
    }

    public function logBackup(string $path, string $type, array $context = []): void
    {
        $this->logger->info("Backup created: {$path}", array_merge($context, [
            'backup_path' => $path,
            'backup_type' => $type,
            'type' => 'backup'
        ]));
    }

    public function logRestore(string $path, array $context = []): void
    {
        $this->logger->info("Restore from: {$path}", array_merge($context, [
            'restore_path' => $path,
            'type' => 'restore'
        ]));
    }

    public function getLogs(int $limit = 100, string $level = null, string $type = null): array
    {
        $logFile = $this->logFile;
        if (!file_exists($logFile)) {
            return [];
        }

        $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $logs = [];
        $count = 0;

        for ($i = count($lines) - 1; $i >= 0 && $count < $limit; $i--) {
            $line = $lines[$i];
            
            if ($this->matchesFilter($line, $level, $type)) {
                $logs[] = $this->parseLogLine($line);
                $count++;
            }
        }

        return array_reverse($logs);
    }

    private function matchesFilter(string $line, ?string $level, ?string $type): bool
    {
        if ($level && !str_contains($line, strtoupper($level))) {
            return false;
        }

        if ($type && !str_contains($line, "\"type\":\"{$type}\"")) {
            return false;
        }

        return true;
    }

    private function parseLogLine(string $line): array
    {
        $pattern = '/^\[([^\]]+)\] \[([^\]]+)\] (.+)$/';
        if (preg_match($pattern, $line, $matches)) {
            $parsed = [
                'timestamp' => $matches[1],
                'level' => $matches[2],
                'message' => $matches[3]
            ];

            if (str_contains($matches[3], '{')) {
                $jsonStart = strpos($matches[3], '{');
                $jsonPart = substr($matches[3], $jsonStart);
                $context = json_decode($jsonPart, true);
                
                if ($context !== null) {
                    $parsed['message'] = substr($matches[3], 0, $jsonStart);
                    $parsed['context'] = $context;
                }
            }

            return $parsed;
        }

        return [
            'timestamp' => '',
            'level' => 'UNKNOWN',
            'message' => $line,
            'context' => []
        ];
    }

    public function clearLogs(): bool
    {
        $logFile = $this->logFile;
        if (file_exists($logFile)) {
            return file_put_contents($logFile, '') !== false;
        }
        return true;
    }

    public function getLogSize(): int
    {
        $logFile = $this->logFile;
        return file_exists($logFile) ? filesize($logFile) : 0;
    }

    public function getLogStats(): array
    {
        $logs = $this->getLogs(1000);
        $stats = [
            'total' => count($logs),
            'by_level' => [],
            'by_type' => [],
            'latest' => null
        ];

        foreach ($logs as $log) {
            $level = $log['level'];
            $stats['by_level'][$level] = ($stats['by_level'][$level] ?? 0) + 1;

            if (isset($log['context']['type'])) {
                $type = $log['context']['type'];
                $stats['by_type'][$type] = ($stats['by_type'][$type] ?? 0) + 1;
            }
        }

        if (!empty($logs)) {
            $stats['latest'] = $logs[0];
        }

        return $stats;
    }

    public function rotateLogs(): bool
    {
        $rotatingHandler = $this->logger->getHandlers()[0] ?? null;
        if ($rotatingHandler instanceof RotatingFileHandler) {
            return $rotatingHandler->close();
        }
        return false;
    }

    public function setLogLevel(string $level): void
    {
        $logLevel = constant(Logger::class . '::' . strtoupper($level));
        
        foreach ($this->logger->getHandlers() as $handler) {
            $handler->setLevel($logLevel);
        }
    }

    public function getLogger(): Logger
    {
        return $this->logger;
    }

    public function getLogDir(): string
    {
        return $this->logDir;
    }

    public function getLogFile(): string
    {
        return $this->logFile;
    }
}
