<?php

namespace Ksfraser\Traits\Tests;

use PHPUnit\Framework\TestCase;
use Ksfraser\Traits\EnforceDeclaredPropsTrait;
use Ksfraser\Traits\EntityStateTrait;
use Ksfraser\Traits\TimestampTrait;

class TraitsTest extends TestCase
{
    public function testEnforceDeclaredPropsTrait(): void
    {
        $obj = new class {
            use EnforceDeclaredPropsTrait;
            public string $name = 'Test';
            private string $secret = 'hidden';

            public function getSecret(): string
            {
                return $this->secret;
            }
        };

        $this->assertEquals('Test', $obj->name);
        $this->assertNull($obj->undefined);
        $this->assertEquals('hidden', $obj->secret);

        $obj->name = 'Updated';
        $this->assertEquals('Updated', $obj->name);

        $obj->undefined = 'should not work';
        $this->assertNull($obj->undefined);
    }

    public function testEntityStateTrait(): void
    {
        $obj = new class {
            use EntityStateTrait;
            public function modify(string $key, $value): void
            {
                $this->setStateValue($key, $value);
            }
        };

        $this->assertFalse($obj->isModified());

        $obj->modify('key', 'value');
        $this->assertTrue($obj->isModified());
        $this->assertEquals('value', $obj->getState('key'));

        $obj->clearModified();
        $this->assertFalse($obj->isModified());
    }

    public function testTimestampTrait(): void
    {
        $obj = new class {
            use TimestampTrait;
            use EntityStateTrait;
            public function modify(string $key, $value): void
            {
                $this->setStateValue($key, $value);
            }
        };

        $this->assertIsInt($obj->getCreatedAt());
        $this->assertIsInt($obj->getUpdatedAt());

        $obj->setCreatedAt(1609459200);
        $this->assertEquals(1609459200, $obj->getCreatedAt());

        $obj->touch();
        $this->assertTrue($obj->isModified());
    }
}