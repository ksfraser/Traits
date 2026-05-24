<?php
/**
 * CRUD Event Emitter Trait
 *
 * Provides standardised CRUD event emission for ksfraser FA modules.
 *
 * Dispatches events via FA's hook_invoke_all() on create/update/delete
 * so that other modules can react without a direct code dependency.
 *
 * Each event fires under two hook names:
 *   1. Specific: <module>_<action>_<recordType>  (e.g. "calendar_created_entry")
 *   2. Generic:  ksf_crud_event                  (broadcast with full payload)
 *
 * @package Ksfraser\Traits
 * @since   1.1.0
 */

declare(strict_types=1);

namespace Ksfraser\Traits;

trait CrudEventEmitterTrait
{
    /**
     * Fire a CRUD event via FA's hook_invoke_all().
     *
     * @param string     $action     One of: created, updated, deleted
     * @param string     $module     Module slug (e.g. 'calendar', 'crm')
     * @param string     $recordType Record type slug (e.g. 'entry', 'customer')
     * @param int|string $recordId   Primary key of the affected record
     * @param array      $data       Additional context (e.g. changed fields)
     *
     * @return void
     *
     * @since 1.1.0
     */
    protected function emitCrudEvent(
        string $action,
        string $module,
        string $recordType,
        $recordId,
        array $data = []
    ): void {
        $payload = [
            'action'      => $action,
            'module'      => $module,
            'record_type' => $recordType,
            'record_id'   => $recordId,
            'data'        => $data,
        ];

        // 1. Specific hook — e.g. "calendar_created_entry"
        $specific = $module . '_' . $action . '_' . $recordType;

        if (function_exists('hook_invoke_all')) {
            hook_invoke_all($specific, $payload);

            // 2. Generic hook for broad listeners
            hook_invoke_all('ksf_crud_event', $payload);
        }
    }

    /**
     * Emit a "created" event.
     *
     * @param string     $module
     * @param string     $recordType
     * @param int|string $recordId
     * @param array      $data
     *
     * @return void
     *
     * @since 1.1.0
     */
    protected function emitCreated(
        string $module,
        string $recordType,
        $recordId,
        array $data = []
    ): void {
        $this->emitCrudEvent('created', $module, $recordType, $recordId, $data);
    }

    /**
     * Emit an "updated" event.
     *
     * @param string     $module
     * @param string     $recordType
     * @param int|string $recordId
     * @param array      $data
     *
     * @return void
     *
     * @since 1.1.0
     */
    protected function emitUpdated(
        string $module,
        string $recordType,
        $recordId,
        array $data = []
    ): void {
        $this->emitCrudEvent('updated', $module, $recordType, $recordId, $data);
    }

    /**
     * Emit a "deleted" event.
     *
     * @param string     $module
     * @param string     $recordType
     * @param int|string $recordId
     * @param array      $data
     *
     * @return void
     *
     * @since 1.1.0
     */
    protected function emitDeleted(
        string $module,
        string $recordType,
        $recordId,
        array $data = []
    ): void {
        $this->emitCrudEvent('deleted', $module, $recordType, $recordId, $data);
    }
}
