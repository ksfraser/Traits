<?php
/**
 * Logger Aware Trait
 *
 * Provides PSR-3 logger injection and a convenience log() method for
 * any class that needs logging.  When no logger has been injected via
 * setLogger(), the log() method auto-creates a FileLogger using the
 * module name returned by getModuleName().
 *
 * Usage:
 *   class MyService {
 *       use LoggerAwareTrait;
 *
 *       protected function getModuleName(): string { return 'my_module'; }
 *
 *       public function doSomething(): void
 *       {
 *           $this->log('info', 'Did something', ['id' => 42]);
 *       }
 *   }
 *
 * @package Ksfraser\Traits
 * @since   1.3.0
 */

declare(strict_types=1);

namespace Ksfraser\Traits;

use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

trait LoggerAwareTrait
{
    private ?LoggerInterface $logger = null;

    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

    public function getLogger(): LoggerInterface
    {
        if ($this->logger === null) {
            $this->logger = new FileLogger($this->getModuleName());
        }
        return $this->logger;
    }

    /**
     * Convenience shortcut — delegates to $this->getLogger()->log().
     *
     * @param mixed  $level   One of Psr\Log\LogLevel::* constants.
     * @param string $message Log message (supports {key} placeholders).
     * @param array  $context Context values for placeholder interpolation.
     */
    protected function log($level, string $message, array $context = []): void
    {
        $this->getLogger()->log($level, $message, $context);
    }

    /**
     * Subclasses MUST return the module name used as the log filename.
     */
    abstract protected function getModuleName(): string;
}
