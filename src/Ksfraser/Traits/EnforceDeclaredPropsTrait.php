<?php
/**
 * Enforce Declared Properties Trait
 *
 * Restricts property access to only declared public properties
 * and virtual getters.
 *
 * @package Ksfraser\Traits
 */

declare(strict_types=1);

namespace Ksfraser\Traits;

trait EnforceDeclaredPropsTrait
{
    public function __get($property)
    {
        if (array_key_exists($property, get_class_vars(get_class($this)))) {
            return $this->$property;
        } elseif (method_exists($this, 'get' . $property)) {
            return call_user_func([$this, 'get' . $property]);
        }
        return null;
    }

    public function __set($property, $value)
    {
        if (array_key_exists($property, get_class_vars(get_class($this)))) {
            $this->$property = $value;
        }
    }
}