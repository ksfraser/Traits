<?php
/**
 * Inline Post Actions Trait
 *
 * Shared inline POST-action handling for FrontAccounting-style tab plugins.
 * Encapsulates the common "persist via an upsert action + notify" behaviour so
 * other modules (CRM, HRM, ...) can reuse the same tab pattern.
 *
 * The using class MUST declare these properties (consumed by the trait):
 *   $upsertClassName      string  FQCN of the upsert action class (required)
 *   $saveButtonName       string  submit-button name that gates the save
 *   $notificationMessage  string  message shown after a successful save
 *
 * The trait declares and manages:
 *   $dao          the injected data-access object
 *   $upsertClass  the upsert action instance built during construction
 *
 * @package Ksfraser\Traits
 * @since 1.4.0
 */

declare(strict_types=1);

namespace Ksfraser\Traits;

trait InlinePostActionsTrait
{
    /** @var object The injected data-access object. */
    protected $dao;

    /** @var object The upsert action created from $upsertClassName. */
    protected $upsertClass;

    /**
     * Build the upsert action from $upsertClassName.
     *
     * Call this from the using class constructor AFTER assigning $this->dao.
     *
     * @return void
     *
     * @since 1.4.0
     */
    protected function initUpsertClass(): void
    {
        $this->upsertClass = new $this->upsertClassName($this->dao);
    }

    /**
     * Handle an inline save triggered by the tab's own submit button.
     *
     * No-op unless the request is a POST, $stockId is non-empty and the tab's
     * submit button ($saveButtonName) is present, so ordinary main-form posts
     * (tab switches, product selector changes) never trigger a save.
     *
     * @param string $stockId Stock item identifier
     * @return void
     *
     * @since 1.4.0
     */
    protected function handlePostActions(string $stockId): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || $stockId === '') {
            return;
        }
        if (!isset($_POST[$this->saveButtonName])) {
            return;
        }

        $this->upsertClass->handle($stockId, $_POST);

        if (function_exists('display_notification')) {
            display_notification($this->localise($this->notificationMessage));
        }
    }

    /**
     * Persist the given POST data via the upsert action.
     *
     * @param string             $stockId  Stock item identifier
     * @param array<string,mixed> $postData Incoming POST data
     * @return void
     *
     * @since 1.4.0
     */
    public function handleSave(string $stockId, array $postData): void
    {
        $this->upsertClass->handle($stockId, $postData);
    }

    /**
     * Delete all tab data for the stock item via the data-access object.
     *
     * @param string $stockId Stock item identifier
     * @return void
     *
     * @since 1.4.0
     */
    public function handleDelete(string $stockId): void
    {
        $this->dao->delete($stockId);
    }

    /**
     * Translate a message when FrontAccounting's _() helper is available.
     *
     * @param string $message Untranslated message
     * @return string Translated message (or original outside FA)
     *
     * @since 1.4.0
     */
    private function localise(string $message): string
    {
        return function_exists('_') ? _($message) : $message;
    }
}
