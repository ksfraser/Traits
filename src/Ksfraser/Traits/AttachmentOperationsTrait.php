<?php
/**
 * Attachment Operations Trait
 *
 * Convenience operations for any class that attaches documents to its own
 * records: addAttachment, removeAttachment, getAttachments, searchAttachments,
 * renameAttachment, hardDeleteAttachment, getOrphanedAttachments,
 * copyAttachment, getAttachmentAudit.
 *
 * This trait deliberately has NO compile-time dependency on any attachment
 * package. The service is resolved lazily from a class-name string (defaulting
 * to the ksf_FA_Attachments implementation) and the optional before/after hooks
 * are called only if they are defined. That matters because this is a generic
 * utility package: a consumer must be able to `use` it without the attachment
 * implementation being installed, otherwise the class fails to load with a
 * "Trait not found" or "Class not found" fatal the moment the module is absent.
 *
 * Usage:
 *   class FA_Calendar_Module {
 *       use \Ksfraser\Traits\AttachmentOperationsTrait;
 *
 *       protected function getAttachmentServiceClass(): string {
 *           return \Ksfraser\Attachment\Service\AttachmentService::class;
 *       }
 *   }
 *
 * @package Ksfraser\Traits
 * @since   1.4.0
 */

declare(strict_types=1);

namespace Ksfraser\Traits;

/**
 * @see LoggerAwareTrait for the same lazy-service pattern.
 */
trait AttachmentOperationsTrait
{
    /** @var object|null Injected service; null means resolve lazily. */
    private $attachmentService;

    /** @var string|null Overrides the service class resolved by getAttachmentService(). */
    private $attachmentServiceClass;

    /**
     * Set the attachment service instance explicitly.
     *
     * Deliberately untyped: typing it against the concrete service would make
     * this package depend on the attachment implementation at compile time.
     *
     * @param object $service
     * @return void
     */
    public function setAttachmentService($service): void
    {
        $this->attachmentService = $service;
    }

    /**
     * Override the class name used to build the service lazily.
     *
     * @param string $class
     * @return void
     */
    public function setAttachmentServiceClass(string $class): void
    {
        $this->attachmentServiceClass = $class;
        $this->attachmentService = null;
    }

    /**
     * The service class this consumer wants, honouring a per-class override.
     *
     * @return string
     */
    protected function getAttachmentServiceClass(): string
    {
        if ($this->attachmentServiceClass !== null) {
            return $this->attachmentServiceClass;
        }

        // A string, not a class reference, so this package does not require the
        // attachment implementation to be installed.
        return 'Ksfraser\\Attachment\\Service\\AttachmentService';
    }

    /**
     * Resolve the attachment service, building it on first use.
     *
     * @return object
     * @throws \RuntimeException If the configured service class cannot be found.
     */
    protected function getAttachmentService()
    {
        if ($this->attachmentService === null) {
            $class = $this->getAttachmentServiceClass();

            if (!class_exists($class)) {
                throw new \RuntimeException(sprintf(
                    'Attachment service "%s" is not available. Install the attachment '
                    . 'package, or inject one with setAttachmentService().',
                    $class
                ));
            }

            $this->attachmentService = new $class();
        }

        return $this->attachmentService;
    }

    /**
     * Fire an attachment lifecycle hook if the host application defines it.
     *
     * The hooks ship with the attachment implementation, so they are optional
     * here. Guarded rather than assumed so consuming this trait never fatals.
     *
     * @param string $hook
     * @param mixed  ...$args
     * @return void
     */
    private function fireAttachmentHook(string $hook, ...$args): void
    {
        $fn = 'attachments_' . $hook . '_hook';

        if (function_exists($fn)) {
            $fn(...$args);
        }
    }

    /**
     * Add an attachment to a module record.
     *
     * @param string $sourceType Module identifier (e.g. 'calendar', 'crm')
     * @param int    $sourceId   Record ID
     * @param array  $data       filename, file_path, file_size, mime_type, uploaded_by, fa_source_ref
     * @return array|false Attachment array on success, false on failure
     */
    public function addAttachment(string $sourceType, int $sourceId, array $data)
    {
        try {
            $data = array_merge(['entity_type' => 'attachment', 'action' => 'create'], $data);
            $this->fireAttachmentHook('before', 'before_save', $data);

            $attachment = $this->getAttachmentService()->addAttachment($sourceType, $sourceId, $data);
            $result = $attachment->toArray();

            $this->fireAttachmentHook('after', 'after_save', $attachment->getId(), [
                'entity_type' => 'attachment',
                'action'      => 'create',
                'data'        => $result + ['source_type' => $sourceType, 'source_id' => $sourceId],
            ]);

            return $result;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Soft-delete an attachment by its ID.
     *
     * @param int $attachmentId
     * @return bool
     */
    public function removeAttachment(int $attachmentId): bool
    {
        try {
            $this->fireAttachmentHook('before', 'before_delete', [
                'entity_type'   => 'attachment',
                'attachment_id' => $attachmentId,
                'mode'          => 'soft',
            ]);

            $this->getAttachmentService()->removeAttachment($attachmentId);

            $this->fireAttachmentHook('after', 'after_delete', $attachmentId, [
                'entity_type' => 'attachment',
                'mode'        => 'soft',
            ]);

            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Get all active attachments for a module record.
     *
     * @param string $sourceType
     * @param int    $sourceId
     * @return array
     */
    public function getAttachments(string $sourceType, int $sourceId): array
    {
        try {
            $attachments = $this->getAttachmentService()->getAttachments($sourceType, $sourceId);

            return array_map(static function ($a) {
                return $a->toArray();
            }, $attachments);
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Search the host application's native attachments table.
     *
     * @param string      $keyword
     * @param int|null    $typeNo
     * @param string|null $dateFrom
     * @param string|null $dateTo
     * @return array
     */
    public function searchAttachments(
        string $keyword = '',
        ?int $typeNo = null,
        ?string $dateFrom = null,
        ?string $dateTo = null
    ): array {
        try {
            return $this->getAttachmentService()->searchFAAttachments($keyword, $typeNo, $dateFrom, $dateTo);
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Rename an attachment by its ID.
     *
     * @param int    $id
     * @param string $newFilename
     * @return object|false
     */
    public function renameAttachment(int $id, string $newFilename)
    {
        try {
            $this->fireAttachmentHook('before', 'before_save', [
                'entity_type'   => 'attachment',
                'action'        => 'rename',
                'attachment_id' => $id,
                'new_filename'  => $newFilename,
            ]);

            $attachment = $this->getAttachmentService()->renameAttachment($id, $newFilename);

            $this->fireAttachmentHook('after', 'after_save', $id, [
                'entity_type' => 'attachment',
                'action'      => 'rename',
                'data'        => ['id' => $id, 'new_filename' => $newFilename] + $attachment->toArray(),
            ]);

            return $attachment;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Permanently delete an attachment from the database and filesystem.
     *
     * @param int $id
     * @return array|false
     */
    public function hardDeleteAttachment(int $id)
    {
        try {
            $this->fireAttachmentHook('before', 'before_delete', [
                'entity_type'   => 'attachment',
                'attachment_id' => $id,
                'mode'          => 'hard',
            ]);

            $result = $this->getAttachmentService()->hardDeleteAttachment($id);

            $this->fireAttachmentHook('after', 'after_delete', $id, [
                'entity_type' => 'attachment',
                'mode'        => 'hard',
                'data'        => $result,
            ]);

            return $result;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Get attachments whose source record no longer exists.
     *
     * @param array $validSources Map of source_type => [table_name, id_column]
     * @return array
     */
    public function getOrphanedAttachments(array $validSources = []): array
    {
        try {
            return $this->getAttachmentService()->getOrphanedAttachments($validSources);
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Copy an attachment to a different storage backend.
     *
     * @param int    $id
     * @param string $targetBackend
     * @return object|false
     */
    public function copyAttachment(int $id, string $targetBackend)
    {
        try {
            $this->fireAttachmentHook('before', 'before_save', [
                'entity_type'    => 'attachment',
                'action'         => 'copy',
                'attachment_id'  => $id,
                'target_backend' => $targetBackend,
            ]);

            $attachment = $this->getAttachmentService()->copyAttachment($id, $targetBackend);

            $this->fireAttachmentHook('after', 'after_save', $id, [
                'entity_type' => 'attachment',
                'action'      => 'copy',
                'data'        => [
                    'new_attachment_id' => $attachment->getId(),
                    'target_backend'    => $targetBackend,
                ] + $attachment->toArray(),
            ]);

            return $attachment;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Get the audit trail for an attachment.
     *
     * @param int $attachmentId
     * @return array
     */
    public function getAttachmentAudit(int $attachmentId): array
    {
        try {
            return $this->getAttachmentService()->getAttachmentAudit($attachmentId);
        } catch (\Throwable $e) {
            return [];
        }
    }
}
