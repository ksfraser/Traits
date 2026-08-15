<?php

namespace Ksfraser\Traits\Tests;

use PHPUnit\Framework\TestCase;
use Ksfraser\Traits\InlinePostActionsTrait;

require_once __DIR__ . '/InlineFixtures.php';

class InlinePostActionsTraitTest extends TestCase
{
    /** @var FakeInlineDao */
    private $dao;

    protected function setUp(): void
    {
        $this->dao = new FakeInlineDao();
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_POST = [];
    }

    protected function tearDown(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_POST = [];
    }

    private function makeTab()
    {
        return new class ($this->dao) {
            use InlinePostActionsTrait;

            protected $upsertClassName     = FakeUpsertAction::class;
            protected $saveButtonName      = 'fake_save';
            protected $notificationMessage = 'Saved.';

            public function __construct($dao)
            {
                $this->dao = $dao;
                $this->initUpsertClass();
            }
        };
    }

    public function testConstructorBuildsUpsertClassFromClassName(): void
    {
        $tab = $this->makeTab();

        $reflection = new \ReflectionObject($tab);
        $property   = $reflection->getProperty('upsertClass');
        $property->setAccessible(true);

        $this->assertInstanceOf(FakeUpsertAction::class, $property->getValue($tab));
    }

    public function testHandleSavePersistsViaUpsertClass(): void
    {
        $tab = $this->makeTab();
        $tab->handleSave('SKU001', ['brand' => 'Acme']);

        $reflection = new \ReflectionObject($tab);
        $property   = $reflection->getProperty('upsertClass');
        $property->setAccessible(true);

        $this->assertSame([['SKU001', ['brand' => 'Acme']]], $property->getValue($tab)->calls);
    }

    public function testHandleDeleteDelegatesToDao(): void
    {
        $tab = $this->makeTab();
        $tab->handleDelete('SKU001');

        $this->assertSame(['SKU001'], $this->dao->deleted);
    }

    public function testHandlePostActionsSavesWhenButtonPresent(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['fake_save' => 'Save', 'brand' => 'Acme'];

        $tab = $this->makeTab();
        $this->invokePostActions($tab, 'SKU002');

        $reflection = new \ReflectionObject($tab);
        $property   = $reflection->getProperty('upsertClass');
        $property->setAccessible(true);
        $this->assertSame([['SKU002', ['fake_save' => 'Save', 'brand' => 'Acme']]], $property->getValue($tab)->calls);
    }

    public function testHandlePostActionsSkipsWithoutButton(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['action' => 'save', 'brand' => 'Acme'];

        $tab = $this->makeTab();
        $this->invokePostActions($tab, 'SKU001');

        $reflection = new \ReflectionObject($tab);
        $property   = $reflection->getProperty('upsertClass');
        $property->setAccessible(true);
        $this->assertSame([], $property->getValue($tab)->calls);
    }

    public function testHandlePostActionsSkipsOnGetRequest(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_POST = ['fake_save' => 'Save'];

        $tab = $this->makeTab();
        $this->invokePostActions($tab, 'SKU001');

        $reflection = new \ReflectionObject($tab);
        $property   = $reflection->getProperty('upsertClass');
        $property->setAccessible(true);
        $this->assertSame([], $property->getValue($tab)->calls);
    }

    public function testHandlePostActionsSkipsEmptyStockId(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['fake_save' => 'Save'];

        $tab = $this->makeTab();
        $this->invokePostActions($tab, '');

        $reflection = new \ReflectionObject($tab);
        $property   = $reflection->getProperty('upsertClass');
        $property->setAccessible(true);
        $this->assertSame([], $property->getValue($tab)->calls);
    }

    private function invokePostActions($tab, string $stockId): void
    {
        $method = new \ReflectionMethod($tab, 'handlePostActions');
        $method->setAccessible(true);
        $method->invoke($tab, $stockId);
    }
}
