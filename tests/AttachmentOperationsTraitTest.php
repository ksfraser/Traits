<?php
/**
 * Tests AttachmentOperationsTrait.
 *
 * The whole point of the trait is that it loads and behaves sanely WITHOUT the
 * attachment implementation installed, so most of these run against a stub
 * service and assert the degradation paths rather than real attachment I/O.
 *
 * @package Ksfraser\Traits
 */

declare(strict_types=1);

namespace Ksfraser\Traits\Tests;

use Ksfraser\Traits\AttachmentOperationsTrait;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Ksfraser\Traits\AttachmentOperationsTrait
 */
final class AttachmentOperationsTraitTest extends TestCase
{
    /**
     * @return void
     */
    public function testTraitLoadsWithoutTheAttachmentPackageInstalled(): void
    {
        // The default service class is deliberately absent in this suite.
        self::assertFalse(
            class_exists('Ksfraser\\Attachment\\Service\\AttachmentService'),
            'This test is only meaningful when the attachment implementation is NOT installed'
        );

        $subject = new class () {
            use AttachmentOperationsTrait;
        };

        self::assertTrue(method_exists($subject, 'addAttachment'));
        self::assertTrue(method_exists($subject, 'getAttachments'));
    }

    /**
     * A missing service must not take the caller down: the contract is
     * false/[] on failure, not an exception.
     *
     * @return void
     */
    public function testOperationsDegradeGracefullyWhenServiceIsUnavailable(): void
    {
        $subject = new class () {
            use AttachmentOperationsTrait;
        };

        self::assertFalse($subject->addAttachment('calendar', 1, ['filename' => 'a.pdf']));
        self::assertSame([], $subject->getAttachments('calendar', 1));
        self::assertSame([], $subject->searchAttachments('a'));
        self::assertFalse($subject->removeAttachment(1));
        self::assertSame([], $subject->getOrphanedAttachments());
        self::assertSame([], $subject->getAttachmentAudit(1));
    }

    /**
     * @return void
     */
    public function testGetAttachmentServiceThrowsWithAnActionableMessage(): void
    {
        $subject = new class () {
            use AttachmentOperationsTrait;

            public function callResolve(): void
            {
                $this->getAttachmentService();
            }
        };

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Attachment service');

        $subject->callResolve();
    }

    /**
     * @return void
     */
    public function testInjectedServiceIsUsed(): void
    {
        $service = new FakeAttachmentService();

        $subject = new class () {
            use AttachmentOperationsTrait;
        };
        $subject->setAttachmentService($service);

        self::assertSame(['id' => 1], $subject->addAttachment('calendar', 7, ['filename' => 'x.pdf']));
        self::assertSame(7, $service->lastSourceId, 'the injected service should receive the call');
    }

    /**
     * @return void
     */
    public function testServiceClassOverrideResolvesTheConfiguredClass(): void
    {
        $subject = new class () {
            use AttachmentOperationsTrait;
        };
        $subject->setAttachmentServiceClass(FakeAttachmentService::class);

        self::assertSame(['id' => 1], $subject->addAttachment('calendar', 3, ['filename' => 'y.pdf']));
    }

    /**
     * @return void
     */
    public function testHooksAreOptionalAndDoNotFatalWhenUndefined(): void
    {
        self::assertFalse(
            function_exists('attachments_before_hook'),
            'Hooks live in the attachment implementation and should be absent here'
        );

        $service = new FakeAttachmentService();
        $subject = new class () {
            use AttachmentOperationsTrait;
        };
        $subject->setAttachmentService($service);

        // Must not fatal even though no hook functions exist.
        self::assertSame(['id' => 1], $subject->addAttachment('calendar', 1, []));
        self::assertTrue($subject->removeAttachment(1));
        self::assertSame(1, $subject->renameAttachment(1, 'z.pdf')->getId());
    }
}

/**
 * Minimal stand-in for the attachment service.
 */
final class FakeAttachmentService
{
    /** @var int */
    public $lastSourceId = 0;

    /**
     * @param string $sourceType
     * @param int    $sourceId
     * @param array  $data
     * @return FakeAttachment
     */
    public function addAttachment(string $sourceType, int $sourceId, array $data): FakeAttachment
    {
        $this->lastSourceId = $sourceId;

        return new FakeAttachment(1);
    }

    public function removeAttachment(int $id): bool
    {
        return true;
    }

    public function getAttachments(string $sourceType, int $sourceId): array
    {
        return [new FakeAttachment(2)];
    }

    public function searchFAAttachments(string $k, ?int $t, ?string $f, ?string $to): array
    {
        return [];
    }

    public function renameAttachment(int $id, string $newFilename): FakeAttachment
    {
        return new FakeAttachment($id);
    }

    public function hardDeleteAttachment(int $id): array
    {
        return ['deleted' => $id];
    }

    public function getOrphanedAttachments(array $validSources): array
    {
        return [];
    }

    public function copyAttachment(int $id, string $backend): FakeAttachment
    {
        return new FakeAttachment($id);
    }

    public function getAttachmentAudit(int $id): array
    {
        return [];
    }
}

/**
 * Minimal stand-in for the attachment entity.
 */
final class FakeAttachment
{
    /** @var int */
    private $id;

    /**
     * @param int $id
     */
    public function __construct(int $id)
    {
        $this->id = $id;
    }

    /**
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * @return array
     */
    public function toArray(): array
    {
        return ['id' => $this->id];
    }
}
