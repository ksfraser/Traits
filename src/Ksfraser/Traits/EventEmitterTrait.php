<?php
/**
 * Event Emitter Trait
 *
 * Provides simple event emission capability.
 *
 * @package Ksfraser\Traits
 */

declare(strict_types=1);

namespace Ksfraser\Traits;

trait EventEmitterTrait
{
    private array $listeners = [];

    public function on(string $event, callable $callback): void
    {
        if (!isset($this->listeners[$event])) {
            $this->listeners[$event] = [];
        }
        $this->listeners[$event][] = $callback;
    }

    public function off(string $event, callable $callback): void
    {
        if (isset($this->listeners[$event])) {
            $this->listeners[$event] = array_filter(
                $this->listeners[$event],
                fn($cb) => $cb !== $callback
            );
        }
    }

    public function emit(string $event, $data = null): void
    {
        if (isset($this->listeners[$event])) {
            foreach ($this->listeners[$event] as $callback) {
                $callback($data);
            }
        }
    }

    public function once(string $event, callable $callback): void
    {
        $wrapper = function ($data) use ($event, $callback, &$wrapper) {
            $callback($data);
            $this->off($event, $wrapper);
        };
        $this->on($event, $wrapper);
    }
}