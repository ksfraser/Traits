<?php
/**
 * Entity State Trait
 *
 * Provides state management for entity objects.
 *
 * @package Ksfraser\Traits
 */

declare(strict_types=1);

namespace Ksfraser\Traits;

trait EntityStateTrait
{
    private array $state = [];
    private bool $modified = false;

    public function markModified(): void
    {
        $this->modified = true;
        $this->state['modified_at'] = time();
    }

    public function isModified(): bool
    {
        return $this->modified;
    }

    public function clearModified(): void
    {
        $this->modified = false;
    }

    public function getState(string $key = null)
    {
        if ($key === null) {
            return $this->state;
        }
        return $this->state[$key] ?? null;
    }

    protected function setStateValue(string $key, $value): void
    {
        $this->state[$key] = $value;
        $this->markModified();
    }
}