<?php

namespace Opayo\Logger;

use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Stringable;

/**
 * PSR-3 compliant file logger for Opayo
 */
class OpayoLogger implements LoggerInterface
{
    private string $logFile;
    private string $minLevel;

    /** @var array<string, int> */
    private const LEVELS = [
        LogLevel::DEBUG => 0,
        LogLevel::INFO => 1,
        LogLevel::NOTICE => 2,
        LogLevel::WARNING => 3,
        LogLevel::ERROR => 4,
        LogLevel::CRITICAL => 5,
        LogLevel::ALERT => 6,
        LogLevel::EMERGENCY => 7,
    ];

    /**
     * @param string $logFile Path to log file
     * @param string $minLevel Minimum log level (default: info)
     */
    public function __construct(string $logFile = '/tmp/opayo.log', string $minLevel = LogLevel::INFO)
    {
        $this->logFile = $logFile;
        $this->minLevel = $minLevel;
    }

    /**
     * @param string|Stringable $message
     * @param array<string, mixed> $context
     * @return void
     */
    public function emergency(string|Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::EMERGENCY, $message, $context);
    }

    /**
     * @param string|Stringable $message
     * @param array<string, mixed> $context
     * @return void
     */
    public function alert(string|Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::ALERT, $message, $context);
    }

    /**
     * @param string|Stringable $message
     * @param array<string, mixed> $context
     * @return void
     */
    public function critical(string|Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::CRITICAL, $message, $context);
    }

    /**
     * @param string|Stringable $message
     * @param array<string, mixed> $context
     * @return void
     */
    public function error(string|Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::ERROR, $message, $context);
    }

    /**
     * @param string|Stringable $message
     * @param array<string, mixed> $context
     * @return void
     */
    public function warning(string|Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::WARNING, $message, $context);
    }

    /**
     * @param string|Stringable $message
     * @param array<string, mixed> $context
     * @return void
     */
    public function notice(string|Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::NOTICE, $message, $context);
    }

    /**
     * @param string|Stringable $message
     * @param array<string, mixed> $context
     * @return void
     */
    public function info(string|Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::INFO, $message, $context);
    }

    /**
     * @param string|Stringable $message
     * @param array<string, mixed> $context
     * @return void
     */
    public function debug(string|Stringable $message, array $context = []): void
    {
        $this->log(LogLevel::DEBUG, $message, $context);
    }

    /**
     * @param mixed $level
     * @param string|Stringable $message
     * @param array<string, mixed> $context
     * @return void
     */
    public function log($level, string|Stringable $message, array $context = []): void
    {
        if (!$this->shouldLog($level)) {
            return;
        }

        $timestamp = date('Y-m-d H:i:s');
        $levelStr = strtoupper((string)$level);
        $interpolatedMessage = $this->interpolate((string)$message, $context);

        $contextStr = !empty($context) ? ' ' . json_encode($context, JSON_UNESCAPED_SLASHES) : '';
        $logLine = "[{$timestamp}] {$levelStr}: {$interpolatedMessage}{$contextStr}\n";

        $this->write($logLine);
    }

    /**
     * Check if log level should be logged
     *
     * @param mixed $level
     * @return bool
     */
    private function shouldLog($level): bool
    {
        if (!isset(self::LEVELS[$level]) || !isset(self::LEVELS[$this->minLevel])) {
            return true;
        }

        return self::LEVELS[$level] >= self::LEVELS[$this->minLevel];
    }

    /**
     * Interpolate context values into message placeholders
     *
     * @param string $message
     * @param array<string, mixed> $context
     * @return string
     */
    private function interpolate(string $message, array $context = []): string
    {
        $replace = [];
        foreach ($context as $key => $val) {
            if (!is_array($val) && (!is_object($val) || method_exists($val, '__toString'))) {
                $replace['{' . $key . '}'] = $val;
            }
        }

        return strtr($message, $replace);
    }

    /**
     * Write log line to file
     *
     * @param string $line
     * @return void
     */
    private function write(string $line): void
    {
        $dir = dirname($this->logFile);
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        @file_put_contents($this->logFile, $line, FILE_APPEND | LOCK_EX);
    }

    /**
     * Get the log file path
     *
     * @return string
     */
    public function getLogFile(): string
    {
        return $this->logFile;
    }
}
