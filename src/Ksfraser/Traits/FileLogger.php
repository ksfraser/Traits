<?php
/**
 * File Logger
 *
 * PSR-3 logger implementation that writes to FA's company/<comp>/logs/
 * directory.  Resolves the log path at runtime from $GLOBALS['path_to_root']
 * and the current session company.
 *
 * Usage (inside an FA module):
 *   $logger = new FileLogger('my_module');
 *   $logger->info('Event created', ['id' => 42]);
 *
 * @package Ksfraser\Traits
 * @since   1.3.0
 */

declare(strict_types=1);

namespace Ksfraser\Traits;

use Psr\Log\AbstractLogger;
use Psr\Log\LogLevel;

class FileLogger extends AbstractLogger
{
    private string $logDir;
    private string $filename;

    /**
     * @param string      $moduleName  Used as the log filename (<module>.log).
     * @param string|null $logDir      Absolute path to the logs directory.
     *                                 Default: resolves from FA globals.
     */
    public function __construct(string $moduleName, ?string $logDir = null)
    {
        $this->filename = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $moduleName) . '.log';

        if ($logDir !== null) {
            $this->logDir = rtrim($logDir, '/');
        } else {
            $this->logDir = $this->resolveDefaultLogDir();
        }
    }

    public function log($level, $message, array $context = []): void
    {
        $path = $this->logDir . '/' . $this->filename;

        if (!is_dir($this->logDir)) {
            @mkdir($this->logDir, 0755, true);
            if (!is_dir($this->logDir)) {
                return; // cannot create directory — fail silently
            }
        }

        $interpolated = $this->interpolate((string) $message, $context);
        $line = sprintf(
            '[%s] %s %s%s',
            date('d-M-Y H:i:s e'),
            strtoupper((string) $level),
            $interpolated,
            PHP_EOL
        );

        @file_put_contents($path, $line, FILE_APPEND | LOCK_EX);
    }

    private function interpolate(string $message, array $context): string
    {
        $replace = [];
        foreach ($context as $key => $val) {
            $replace['{' . $key . '}'] = $this->stringify($val);
        }
        return strtr($message, $replace);
    }

    private function stringify($value): string
    {
        if ($value === null) {
            return 'null';
        }
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (is_scalar($value)) {
            return (string) $value;
        }
        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }
        if (is_object($value) && method_exists($value, '__toString')) {
            return (string) $value;
        }
        return gettype($value);
    }

    private function resolveDefaultLogDir(): string
    {
        $path_to_root = $GLOBALS['path_to_root'] ?? '';
        if ($path_to_root === '') {
            return sys_get_temp_dir() . '/ksf_logs';
        }

        $comp = '0';
        if (isset($_SESSION['wa_current_user'], $_SESSION['wa_current_user']->company)) {
            $comp = $_SESSION['wa_current_user']->company;
        }

        return rtrim($path_to_root, '/') . '/company/' . $comp . '/logs';
    }
}
