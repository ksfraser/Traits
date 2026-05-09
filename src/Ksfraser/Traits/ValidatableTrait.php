<?php
/**
 * Validatable Trait
 *
 * Provides validation capability to entities.
 *
 * @package Ksfraser\Traits
 */

declare(strict_types=1);

namespace Ksfraser\Traits;

trait ValidatableTrait
{
    protected array $validationErrors = [];

    abstract protected function validate(): array;

    public function isValid(): bool
    {
        $this->validationErrors = $this->validate();
        return count($this->validationErrors) === 0;
    }

    public function getValidationErrors(): array
    {
        if (empty($this->validationErrors)) {
            $this->validationErrors = $this->validate();
        }
        return $this->validationErrors;
    }

    public function assertValid(): void
    {
        $errors = $this->validate();
        if (count($errors) > 0) {
            throw new \RuntimeException('Validation failed: ' . implode(', ', $errors));
        }
    }
}