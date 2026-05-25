<?php
/**
 * Hook Query Provider Trait
 *
 * Provides standardised FA hook-based inter-module value query methods
 * (ksf_get_value / ksf_get_values / ksf_set_value) for KSF FA modules.
 *
 * Each module that uses this trait must implement _getAdvertisedValues()
 * returning an associative array of namespaced key => value pairs.
 *
 * IMPORTANT: FA's hook_invoke_first/all declare &$data (by-reference).
 * Always pass a variable when calling these hooks, never a literal.
 *
 * @package Ksfraser\Traits
 * @since   1.2.0
 */

declare(strict_types=1);

namespace Ksfraser\Traits;

trait HookQueryProviderTrait
{
    /**
     * Respond to a single-value query from another module.
     *
     * Dispatched via hook_invoke_first('ksf_get_value', $key). The first
     * provider that recognises the key returns its value; others return null
     * and the chain continues.
     *
     * Consumers:
     *   $key = 'calendar.api_version';
     *   $value = hook_invoke_first('ksf_get_value', $key);
     *
     * @param mixed $key   Namespaced key (e.g. 'calendar.api_version')
     * @param mixed $opts  Reserved (defaults to null per FA convention)
     * @return mixed|null  Value if recognised, null if not this provider's key
     *
     * @since 1.2.0
     */
    public function ksf_get_value(&$key, $opts = null)
    {
        $values = $this->_getAdvertisedValues();

        return array_key_exists($key, $values) ? $values[$key] : null;
    }

    /**
     * Respond to a multi-value query from another module.
     *
     * Dispatched via hook_invoke_all('ksf_get_values', $keys). All providers
     * respond with their matching key => value pairs.
     *
     * Consumers:
     *   $keys = ['calendar.api_version', 'rbac.hooks_version'];
     *   $results = hook_invoke_all('ksf_get_values', $keys);
     *
     * @param mixed $keys  List of requested keys (null or empty = return all)
     * @param mixed $opts  Reserved
     * @return array       Associative array of matching key => value pairs
     *
     * @since 1.2.0
     */
    public function ksf_get_values(&$keys = null, $opts = null)
    {
        $values = $this->_getAdvertisedValues();

        if (empty($keys)) {
            return $values;
        }

        return array_intersect_key($values, array_flip($keys));
    }

    /**
     * Receive a value pushed from another module.
     *
     * Dispatched via hook_invoke_all('ksf_set_value', $payload). Every
     * module receives the payload; those that recognise the key may act.
     *
     * Default implementation is a no-op. Override in modules that need
     * to accept pushed values.
     *
     * Consumers:
     *   $payload = ['key' => 'calendar.some_setting', 'value' => '...'];
     *   hook_invoke_all('ksf_set_value', $payload);
     *
     * @param mixed $data  Compound array with 'key' and 'value' entries
     * @param mixed $opts  Reserved
     * @return void
     *
     * @since 1.2.0
     */
    public function ksf_set_value(&$data, $opts = null)
    {
        // Default no-op — override in modules that accept pushed values
    }

    /**
     * Return all values this module advertises via the query hook system.
     *
     * Each key MUST be namespaced as "<module>.<value_name>" to prevent
     * collisions between modules. Values should be guarded for availability
     * (e.g. defined() for constants, function_exists() for FA helpers).
     *
     * @return array<string, mixed>  Associative array of key => value pairs
     *
     * @since 1.2.0
     */
    abstract protected function _getAdvertisedValues(): array;
}
