<?php

namespace Opayo\Tests;

use Opayo\Logger\OpayoLogger;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;

/**
 * Test suite for OpayoLogger class
 */
class OpayoLoggerTest extends TestCase
{
    private string $logFile;

    protected function setUp(): void
    {
        $this->logFile = sys_get_temp_dir() . '/opayo_test_' . uniqid() . '.log';
    }

    protected function tearDown(): void
    {
        if (file_exists($this->logFile)) {
            @unlink($this->logFile);
        }
    }

    public function testEmergencyLogsMessage(): void
    {
        $logger = new OpayoLogger($this->logFile, LogLevel::DEBUG);
        $logger->emergency('Emergency message');

        $content = file_get_contents($this->logFile);
        $this->assertStringContainsString('EMERGENCY: Emergency message', $content);
    }

    public function testAlertLogsMessage(): void
    {
        $logger = new OpayoLogger($this->logFile, LogLevel::DEBUG);
        $logger->alert('Alert message');

        $content = file_get_contents($this->logFile);
        $this->assertStringContainsString('ALERT: Alert message', $content);
    }

    public function testCriticalLogsMessage(): void
    {
        $logger = new OpayoLogger($this->logFile, LogLevel::DEBUG);
        $logger->critical('Critical message');

        $content = file_get_contents($this->logFile);
        $this->assertStringContainsString('CRITICAL: Critical message', $content);
    }

    public function testErrorLogsMessage(): void
    {
        $logger = new OpayoLogger($this->logFile, LogLevel::DEBUG);
        $logger->error('Error message');

        $content = file_get_contents($this->logFile);
        $this->assertStringContainsString('ERROR: Error message', $content);
    }

    public function testWarningLogsMessage(): void
    {
        $logger = new OpayoLogger($this->logFile, LogLevel::DEBUG);
        $logger->warning('Warning message');

        $content = file_get_contents($this->logFile);
        $this->assertStringContainsString('WARNING: Warning message', $content);
    }

    public function testNoticeLogsMessage(): void
    {
        $logger = new OpayoLogger($this->logFile, LogLevel::DEBUG);
        $logger->notice('Notice message');

        $content = file_get_contents($this->logFile);
        $this->assertStringContainsString('NOTICE: Notice message', $content);
    }

    public function testInfoLogsMessage(): void
    {
        $logger = new OpayoLogger($this->logFile, LogLevel::DEBUG);
        $logger->info('Info message');

        $content = file_get_contents($this->logFile);
        $this->assertStringContainsString('INFO: Info message', $content);
    }

    public function testDebugLogsMessage(): void
    {
        $logger = new OpayoLogger($this->logFile, LogLevel::DEBUG);
        $logger->debug('Debug message');

        $content = file_get_contents($this->logFile);
        $this->assertStringContainsString('DEBUG: Debug message', $content);
    }

    public function testLogCreatesFile(): void
    {
        $this->assertFileDoesNotExist($this->logFile);

        $logger = new OpayoLogger($this->logFile);
        $logger->info('Test message');

        $this->assertFileExists($this->logFile);
    }

    public function testLogCreatesDirectory(): void
    {
        $nestedLogFile = sys_get_temp_dir() . '/opayo_test_' . uniqid() . '/nested/test.log';

        $logger = new OpayoLogger($nestedLogFile);
        $logger->info('Test message');

        $this->assertFileExists($nestedLogFile);
        $this->assertDirectoryExists(dirname($nestedLogFile));

        // Cleanup
        @unlink($nestedLogFile);
        @rmdir(dirname($nestedLogFile));
        @rmdir(dirname(dirname($nestedLogFile)));
    }

    public function testMessageInterpolation(): void
    {
        $logger = new OpayoLogger($this->logFile, LogLevel::DEBUG);
        $logger->info('User {username} logged in from {ip}', [
            'username' => 'john',
            'ip' => '192.168.1.1',
        ]);

        $content = file_get_contents($this->logFile);
        $this->assertStringContainsString('User john logged in from 192.168.1.1', $content);
    }

    public function testContextLogging(): void
    {
        $logger = new OpayoLogger($this->logFile, LogLevel::DEBUG);
        $logger->error('Database error', [
            'table' => 'users',
            'query' => 'SELECT * FROM users',
        ]);

        $content = file_get_contents($this->logFile);
        $this->assertStringContainsString('Database error', $content);
        $this->assertStringContainsString('"table":"users"', $content);
        $this->assertStringContainsString('"query":"SELECT * FROM users"', $content);
    }

    public function testMinLevelFilteringBlocksDebug(): void
    {
        $logger = new OpayoLogger($this->logFile, LogLevel::INFO);
        $logger->debug('Debug message');
        $logger->info('Info message');

        $content = file_get_contents($this->logFile);
        $this->assertStringNotContainsString('Debug message', $content);
        $this->assertStringContainsString('Info message', $content);
    }

    public function testMinLevelFilteringBlocksInfo(): void
    {
        $logger = new OpayoLogger($this->logFile, LogLevel::WARNING);
        $logger->info('Info message');
        $logger->warning('Warning message');

        $content = file_get_contents($this->logFile);
        $this->assertStringNotContainsString('Info message', $content);
        $this->assertStringContainsString('Warning message', $content);
    }

    public function testMinLevelFilteringAllowsHigherLevels(): void
    {
        $logger = new OpayoLogger($this->logFile, LogLevel::ERROR);
        $logger->warning('Warning message');
        $logger->error('Error message');
        $logger->critical('Critical message');

        $content = file_get_contents($this->logFile);
        $this->assertStringNotContainsString('Warning message', $content);
        $this->assertStringContainsString('Error message', $content);
        $this->assertStringContainsString('Critical message', $content);
    }

    public function testLogIncludesTimestamp(): void
    {
        $logger = new OpayoLogger($this->logFile);
        $logger->info('Test message');

        $content = file_get_contents($this->logFile);
        $this->assertMatchesRegularExpression('/\[\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\]/', $content);
    }

    public function testGetLogFile(): void
    {
        $logger = new OpayoLogger($this->logFile);

        $this->assertSame($this->logFile, $logger->getLogFile());
    }

    public function testMultipleLogEntries(): void
    {
        $logger = new OpayoLogger($this->logFile, LogLevel::DEBUG);
        $logger->info('First message');
        $logger->warning('Second message');
        $logger->error('Third message');

        $content = file_get_contents($this->logFile);
        $this->assertStringContainsString('First message', $content);
        $this->assertStringContainsString('Second message', $content);
        $this->assertStringContainsString('Third message', $content);
    }

    public function testLogFormatting(): void
    {
        $logger = new OpayoLogger($this->logFile, LogLevel::DEBUG);
        $logger->info('Test message', ['key' => 'value']);

        $content = file_get_contents($this->logFile);
        $lines = explode("\n", trim($content));

        $this->assertCount(1, $lines);
        $this->assertStringContainsString('[', $lines[0]); // Timestamp bracket
        $this->assertStringContainsString('INFO:', $lines[0]);
        $this->assertStringContainsString('Test message', $lines[0]);
        $this->assertStringContainsString('{"key":"value"}', $lines[0]);
    }

    public function testInterpolationWithArrayContext(): void
    {
        $logger = new OpayoLogger($this->logFile, LogLevel::DEBUG);
        $logger->info('Message with {placeholder}', [
            'placeholder' => 'value',
            'extra' => ['nested' => 'data'],
        ]);

        $content = file_get_contents($this->logFile);
        $this->assertStringContainsString('Message with value', $content);
    }

    public function testInterpolationIgnoresObjects(): void
    {
        $logger = new OpayoLogger($this->logFile, LogLevel::DEBUG);
        $logger->info('Message with {obj}', [
            'obj' => new \stdClass(),
            'string' => 'text',
        ]);

        $content = file_get_contents($this->logFile);
        $this->assertStringContainsString('Message with {obj}', $content); // Should not interpolate object
    }

    public function testDefaultLogFile(): void
    {
        $logger = new OpayoLogger();

        $this->assertSame('/tmp/opayo.log', $logger->getLogFile());
    }

    public function testDefaultMinLevel(): void
    {
        $logger = new OpayoLogger($this->logFile);
        $logger->debug('Debug message');
        $logger->info('Info message');

        $content = file_get_contents($this->logFile);
        $this->assertStringNotContainsString('Debug message', $content);
        $this->assertStringContainsString('Info message', $content);
    }

    public function testPsr3Compliance(): void
    {
        $logger = new OpayoLogger($this->logFile, LogLevel::DEBUG);

        // PSR-3 requires these methods
        $this->assertTrue(method_exists($logger, 'emergency'));
        $this->assertTrue(method_exists($logger, 'alert'));
        $this->assertTrue(method_exists($logger, 'critical'));
        $this->assertTrue(method_exists($logger, 'error'));
        $this->assertTrue(method_exists($logger, 'warning'));
        $this->assertTrue(method_exists($logger, 'notice'));
        $this->assertTrue(method_exists($logger, 'info'));
        $this->assertTrue(method_exists($logger, 'debug'));
        $this->assertTrue(method_exists($logger, 'log'));
    }
}
