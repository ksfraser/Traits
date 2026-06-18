<?php

namespace Ksfraser\Traits\Tests;

use PHPUnit\Framework\TestCase;
use Ksfraser\Traits\LoggerAwareTrait;
use Ksfraser\Traits\FileLogger;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Psr\Log\LogLevel;

class LoggerAwareTraitTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/ksf_logger_aware_' . uniqid();
        mkdir($this->tmpDir, 0755, true);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tmpDir)) {
            array_map('unlink', glob($this->tmpDir . '/*'));
            rmdir($this->tmpDir);
        }
    }

    private function createLoggerAware(?LoggerInterface $injectLogger = null)
    {
        return new class($this->tmpDir, $injectLogger) {
            use LoggerAwareTrait;
            private string $dir;

            public function __construct(string $dir, ?LoggerInterface $logger)
            {
                $this->dir = $dir;
                if ($logger !== null) {
                    $this->setLogger($logger);
                }
            }

            protected function getModuleName(): string
            {
                return 'aware_test';
            }

            public function getDir(): string
            {
                return $this->dir;
            }

            public function doLog($level, string $message, array $context = []): void
            {
                $this->log($level, $message, $context);
            }
        };
    }

    public function testSetLoggerInjectsLogger(): void
    {
        $logger = new NullLogger();
        $obj = $this->createLoggerAware($logger);
        $this->assertSame($logger, $obj->getLogger());
    }

    public function testGetLoggerCreatesDefaultFileLogger(): void
    {
        // Override the default log dir by setting $GLOBALS for the constructor
        $obj = new class() {
            use LoggerAwareTrait;
            protected function getModuleName(): string { return 'default_test'; }
        };
        $logger = $obj->getLogger();
        $this->assertInstanceOf(FileLogger::class, $logger);
    }

    public function testLogConvenienceMethodWritesToFile(): void
    {
        $logger = new FileLogger('aware_test', $this->tmpDir);
        $obj = $this->createLoggerAware($logger);
        $obj->doLog(LogLevel::INFO, 'convenience test');

        $logPath = $this->tmpDir . '/aware_test.log';
        $this->assertFileExists($logPath);
        $content = file_get_contents($logPath);
        $this->assertStringContainsString('convenience test', $content);
    }

    public function testLogWithContextInterpolation(): void
    {
        $logger = new FileLogger('aware_test', $this->tmpDir);
        $obj = $this->createLoggerAware($logger);
        $obj->doLog(LogLevel::INFO, 'Hello {name}', ['name' => 'World']);

        $content = file_get_contents($this->tmpDir . '/aware_test.log');
        $this->assertStringContainsString('Hello World', $content);
    }
}
