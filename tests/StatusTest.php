<?php declare(strict_types=1);

namespace Hval\Scalapay\Tests;

use Hval\Scalapay\Status;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * The lists exist to detect a status this library does not know yet, so they must hold
 * exactly the constants of their own axis.
 *
 * @internal
 */
#[CoversClass(Status::class)]
final class StatusTest extends TestCase
{
    public function testTheKnownOrderStatusesAreTheFiveOrderConstants(): void
    {
        self::assertSame(['created', 'authorized', 'charged', 'refunded', 'expired'], Status::knownOrderStatuses());
    }

    public function testTheKnownOperationOutcomesAreTheTwoOperationConstants(): void
    {
        self::assertSame(['AUTHORIZED', 'APPROVED'], Status::knownOperationOutcomes());
    }

    public function testTheKnownCaptureStatusesAreTheFourCaptureConstants(): void
    {
        self::assertSame(['captured', 'pending', 'delayed', 'voided'], Status::knownCaptureStatuses());
    }

    public function testAnUnknownStatusIsRecognisedAsSuch(): void
    {
        self::assertFalse(in_array('partially_charged', Status::knownOrderStatuses(), true));
        self::assertTrue(in_array(Status::ORDER_CHARGED, Status::knownOrderStatuses(), true));
    }

    /**
     * The axes share no value: `authorized` is an order status, `AUTHORIZED` an operation
     * outcome, and neither is a capture status. The comparison is case-sensitive on
     * purpose.
     */
    public function testTheThreeListsShareNoValue(): void
    {
        self::assertSame([], array_intersect(Status::knownOrderStatuses(), Status::knownOperationOutcomes()));
        self::assertSame([], array_intersect(Status::knownOrderStatuses(), Status::knownCaptureStatuses()));
        self::assertSame([], array_intersect(Status::knownOperationOutcomes(), Status::knownCaptureStatuses()));
    }
}
