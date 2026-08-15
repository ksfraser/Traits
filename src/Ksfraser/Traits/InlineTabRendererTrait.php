<?php
/**
 * Inline Tab Renderer Trait
 *
 * Shared rendering flow for FrontAccounting-style tab plugins. Delegates to a
 * dedicated UI renderer class so tab classes stay thin and other modules
 * (CRM, HRM, ...) can reuse the same pattern.
 *
 * The using class MUST declare this property (consumed by the trait):
 *   $tabClassName  string  FQCN of the UI renderer class (required)
 *
 * The trait declares and manages:
 *   $tab  the UI renderer instance produced by createTab()
 *
 * @package Ksfraser\Traits
 * @since 1.4.0
 */

declare(strict_types=1);

namespace Ksfraser\Traits;

trait InlineTabRendererTrait
{
    /** @var object|null The tab renderer produced by createTab(). */
    protected $tab;

    /**
     * Run inline POST actions, then render the tab through the UI renderer.
     *
     * @param string $stockId Stock item identifier
     * @return void
     *
     * @since 1.4.0
     */
    public function renderTabContent(string $stockId): void
    {
        $this->handlePostActions($stockId);

        $this->tab = $this->createTab();
        $this->tab->render($stockId);
    }

    /**
     * Create the UI renderer instance for this tab.
     *
     * Override in the using class when the renderer needs extra constructor
     * arguments beyond $this->dao.
     *
     * @return object The UI renderer instance
     *
     * @since 1.4.0
     */
    protected function createTab()
    {
        return new $this->tabClassName($this->dao);
    }
}
