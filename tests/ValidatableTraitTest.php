<?php

namespace Ksfraser\Traits\Tests;

use PHPUnit\Framework\TestCase;
use Ksfraser\Traits\ValidatableTrait;

class ValidatableTraitTest extends TestCase
{
    public function testIsValidWithNoErrors(): void
    {
        $obj = new class {
            use ValidatableTrait;
            public string $name = 'Valid';

            protected function validate(): array
            {
                return [];
            }
        };

        $this->assertTrue($obj->isValid());
        $this->assertEmpty($obj->getValidationErrors());
    }

    public function testIsValidWithErrors(): void
    {
        $obj = new class {
            use ValidatableTrait;
            public string $name = '';

            protected function validate(): array
            {
                if ($this->name === '') {
                    return ['Name is required'];
                }
                return [];
            }
        };

        $this->assertFalse($obj->isValid());
        $errors = $obj->getValidationErrors();
        $this->assertCount(1, $errors);
    }
}