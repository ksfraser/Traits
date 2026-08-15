<?php

namespace Ksfraser\Traits\Tests;

use PHPUnit\Framework\TestCase;
use Ksfraser\Traits\InlineTabRendererTrait;

require_once __DIR__ . '/InlineFixtures.php';

class InlineTabRendererTraitTest extends TestCase
{
    public function testRenderTabContentDelegatesToRenderer(): void
    {
        $dao = new FakeInlineDao();

        $tab = new class ($dao) {
            use InlineTabRendererTrait;

            protected $tabClassName = FakeTabRenderer::class;

            public function __construct($dao)
            {
                $this->dao = $dao;
            }

            protected function handlePostActions(string $stockId): void
            {
            }
        };

        ob_start();
        $tab->renderTabContent('SKU001');
        ob_get_clean();

        $reflection = new \ReflectionObject($tab);
        $property   = $reflection->getProperty('tab');
        $property->setAccessible(true);

        /** @var FakeTabRenderer $renderer */
        $renderer = $property->getValue($tab);
        $this->assertInstanceOf(FakeTabRenderer::class, $renderer);
        $this->assertSame($dao, $renderer->dao);
        $this->assertSame(['SKU001'], $renderer->rendered);
    }

    public function testCreateTabCanBeOverriddenWithExtraArguments(): void
    {
        $dao = new FakeInlineDao();

        $tab = new class ($dao) {
            use InlineTabRendererTrait;

            protected $tabClassName = FakeExtraTabRenderer::class;

            public function __construct($dao)
            {
                $this->dao = $dao;
            }

            protected function handlePostActions(string $stockId): void
            {
            }

            protected function createTab()
            {
                return new $this->tabClassName($this->dao, 'extra');
            }
        };

        $method = new \ReflectionMethod($tab, 'createTab');
        $method->setAccessible(true);

        /** @var FakeExtraTabRenderer $renderer */
        $renderer = $method->invoke($tab);
        $this->assertInstanceOf(FakeExtraTabRenderer::class, $renderer);
        $this->assertSame('extra', $renderer->extra);
    }
}
