<?php

namespace Ksfraser\Traits\Tests;

use PHPUnit\Framework\TestCase;
use Ksfraser\Traits\HookQueryProviderTrait;

class HookQueryProviderTraitTest extends TestCase
{
    private function createProvider(array $values)
    {
        return new class($values) {
            use HookQueryProviderTrait;

            private $values;

            public function __construct($values)
            {
                $this->values = $values;
            }

            protected function _getAdvertisedValues(): array
            {
                return $this->values;
            }
        };
    }

    public function testKsfGetValueReturnsValueForKnownKey(): void
    {
        $provider = $this->createProvider([
            'calendar.api_version' => '2.4.3',
            'calendar.hooks_version' => '2.0',
        ]);

        $key = 'calendar.api_version';
        $result = $provider->ksf_get_value($key);

        $this->assertSame('2.4.3', $result);
    }

    public function testKsfGetValueReturnsNullForUnknownKey(): void
    {
        $provider = $this->createProvider([
            'calendar.api_version' => '2.4.3',
        ]);

        $key = 'rbac.hooks_version';
        $result = $provider->ksf_get_value($key);

        $this->assertNull($result);
    }

    public function testKsfGetValueReturnsNullForNullValue(): void
    {
        $provider = $this->createProvider([
            'calendar.api_version' => null,
            'calendar.some_key' => 'value',
        ]);

        $key = 'calendar.api_version';
        $result = $provider->ksf_get_value($key);
        $this->assertNull($result);
    }

    public function testKsfGetValuesReturnsAllWhenKeysEmpty(): void
    {
        $provider = $this->createProvider([
            'mod.version' => '1.0',
            'mod.name' => 'Test',
        ]);

        $empty = [];
        $result = $provider->ksf_get_values($empty);

        $this->assertSame(['mod.version' => '1.0', 'mod.name' => 'Test'], $result);
    }

    public function testKsfGetValuesReturnsAllWhenKeysNull(): void
    {
        $provider = $this->createProvider([
            'mod.version' => '1.0',
        ]);

        $keys = null;
        $result = $provider->ksf_get_values($keys);

        $this->assertSame(['mod.version' => '1.0'], $result);
    }

    public function testKsfGetValuesReturnsMatchingSubset(): void
    {
        $provider = $this->createProvider([
            'mod.a' => '1',
            'mod.b' => '2',
            'mod.c' => '3',
        ]);

        $keys = ['mod.a', 'mod.c'];
        $result = $provider->ksf_get_values($keys);

        $this->assertSame(['mod.a' => '1', 'mod.c' => '3'], $result);
    }

    public function testKsfGetValuesReturnsEmptyArrayForNoMatch(): void
    {
        $provider = $this->createProvider([
            'mod.a' => '1',
        ]);

        $keys = ['other.x', 'other.y'];
        $result = $provider->ksf_get_values($keys);

        $this->assertSame([], $result);
    }

    public function testKsfSetValueIsNoopByDefault(): void
    {
        $provider = $this->createProvider([]);

        $data = ['key' => 'some.key', 'value' => 'test'];
        $provider->ksf_set_value($data);

        $this->assertSame('test', $data['value']);
    }

    public function testKsfGetValueKeyNotModifiedByProvider(): void
    {
        $provider = $this->createProvider([
            'test.key' => 'result',
        ]);

        $key = 'test.key';
        $original = $key;
        $provider->ksf_get_value($key);

        $this->assertSame($original, $key);
    }
}
