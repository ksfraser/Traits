<?php
namespace Ksfraser\Traits;

trait EnforceDeclaredPropsTrait
{
    // Getter: allow read access to declared public properties and virtual getters
    public function __get($property)
    {
        if (array_key_exists($property, get_class_vars(get_class($this)))) {
            return $this->$property;
        } elseif (method_exists($this, 'get' . $property)) {
            return call_user_func([$this, 'get' . $property]);
        }
        return null;
    }

    // Setter: only set declared public properties
    public function __set($property, $value)
    {
        if (array_key_exists($property, get_class_vars(get_class($this)))) {
            $this->$property = $value;
        }
    }
}
