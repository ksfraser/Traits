<?php

namespace Ksfraser\Traits\Tests;

use PHPUnit\Framework\TestCase;
use Ksfraser\Traits\FileLogger;
use Psr\Log\LogLevel;
use Psr\Log\LoggerInterface;
use Psr\Log\AbstractLogger;

class FileLoggerTest extends TestCase
{
    private string $tmpDir;
    private FileLogger $logger;
    private string $logPath;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/ksf_traits_test_' . uniqid();
        mkdir($this->tmpDir, 0755, true);
        $this->logger = new FileLogger('test_module', $this->tmpDir);
        $this->logPath = $this->tmpDir . '/test_module.log';
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tmpDir)) {
            array_map('unlink', glob($this->tmpDir . '/*'));
            rmdir($this->tmpDir);
        }
    }

    public function testImplementsPsr3LoggerInterface(): void
    {
        $this->assertInstanceOf(LoggerInterface::class, $this->logger);
        $this->assertInstanceOf(AbstractLogger::class, $this->logger);
    }

    public function testLogCreatesFile(): void
    {
        $this->logger->info('Hello world');
        $this->assertFileExists($this->logPath);
    }

    public function testLogAppendsMultipleEntries(): void
    {
        $this->logger->info('First');
        $this->logger->warning('Second');
        $lines = file($this->logPath);
        $this->assertCount(2, $lines);
    }

    public function testLogContainsTimestampLevelAndMessage(): void
    {
        $this->logger->error('Something broke');
        $content = file_get_contents($this->logPath);
        $this->assertStringMatchesFormat('[%d-%s-%d %d:%d:%d %s] ERROR Something broke', trim($content));
    }

    public function testAllLevelsWriteCorrectly(): void
    {
        $levels = [
            LogLevel::EMERGENCY => 'EMERGENCY',
            LogLevel::ALERT     => 'ALERT',
            LogLevel::CRITICAL  => 'CRITICAL',
            LogLevel::ERROR     => 'ERROR',
            LogLevel::WARNING   => 'WARNING',
            LogLevel::NOTICE    => 'NOTICE',
            LogLevel::INFO      => 'INFO',
            LogLevel::DEBUG     => 'DEBUG',
        ];

        foreach ($levels as $psrLevel => $label) {
            $this->logger->log($psrLevel, ucfirst($psrLevel) . ' test');
        }

        $lines = file($this->logPath);
        $this->assertCount(count($levels), $lines);

        foreach ($lines as $i => $line) {
            $expectedLabel = array_values($levels)[$i];
            $this->assertStringContainsString($expectedLabel, $line);
        }
    }

    public function testPlaceholderInterpolation(): void
    {
        $this->logger->info('User {user} created {count} items', [
            'user'  => 'alice',
            'count' => 3,
        ]);
        $content = file_get_contents($this->logPath);
        $this->assertStringContainsString('User alice created 3 items', $content);
    }

    public function testContextWithNullValue(): void
    {
        $this->logger->info('Value is {value}', ['value' => null]);
        $content = file_get_contents($this->logPath);
        $this->assertStringContainsString('Value is null', $content);
    }

    public function testContextWithBoolValue(): void
    {
        $this->logger->info('Flags: {a} {b}', ['a' => true, 'b' => false]);
        $content = file_get_contents($this->logPath);
        $this->assertStringContainsString('Flags: true false', $content);
    }

    public function testContextWithArrayValue(): void
    {
        $this->logger->info('Data {payload}', ['payload' => ['key' => 'val']]);
        $content = file_get_contents($this->logPath);
        $this->assertStringContainsString('{"key":"val"}', $content);
    }

    public function testCreatesLogDirectoryIfMissing(): void
    {
        $newDir = sys_get_temp_dir() . '/ksf_traits_create_' . uniqid();
        $this->assertDirectoryDoesNotExist($newDir);

        $logger = new FileLogger('create_test', $newDir);
        $logger->info('Created dir');

        $this->assertDirectoryExists($newDir);
        $this->assertFileExists($newDir . '/create_test.log');

        array_map('unlink', glob($newDir . '/*'));
        rmdir($newDir);
    }

    public function testSanitizedFilename(): void
    {
        $logger = new FileLogger('My Module!@#', $this->tmpDir);
        $logger->info('test');
        $files = glob($this->tmpDir . '/My_Module*.log');
        $this->assertCount(1, $files, 'Should create one sanitised log file');
    }

    public function testDefaultLogDirWithoutFaContext(): void
    {
        $logger = new class('no_fa') extends FileLogger {
            public function __construct(string $moduleName)
            {
                parent::__construct($moduleName);
            }
        };
        // When $GLOBALS['path_to_root'] is not set, falls back to sys_get_temp_dir()
        $logger->info('fallback test');
        $expectedDir = sys_get_temp_dir() . '/ksf_logs';
        $this->assertDirectoryExists($expectedDir);
        $this->assertFileExists($expectedDir . '/no_fa.log');

        array_map('unlink', glob($expectedDir . '/*'));
        rmdir($expectedDir);
    }
}
