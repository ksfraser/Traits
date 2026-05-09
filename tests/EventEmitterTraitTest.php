<?php

namespace Ksfraser\Traits\Tests;

use PHPUnit\Framework\TestCase;
use Ksfraser\Traits\EventEmitterTrait;

class EventEmitterTraitTest extends TestCase
{
    public function testOnAndEmit(): void
    {
        $obj = new class {
            use EventEmitterTrait;
        };

        $received = null;
        $obj->on('test', function ($data) use (&$received) {
            $received = $data;
        });

        $obj->emit('test', 'hello');
        $this->assertEquals('hello', $received);
    }

    public function testOff(): void
    {
        $obj = new class {
            use EventEmitterTrait;
        };

        $count = 0;
        $callback = function () use (&$count) {
            $count++;
        };

        $obj->on('test', $callback);
        $obj->emit('test');
        $this->assertEquals(1, $count);

        $obj->off('test', $callback);
        $obj->emit('test');
        $this->assertEquals(1, $count);
    }

    public function testOnce(): void
    {
        $obj = new class {
            use EventEmitterTrait;
        };

        $count = 0;
        $obj->once('test', function () use (&$count) {
            $count++;
        });

        $obj->emit('test');
        $obj->emit('test');
        $this->assertEquals(1, $count);
    }
}