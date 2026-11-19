<?php declare(strict_types=1);

namespace Hval\Scalapay\Tests\Response;

use Hval\Scalapay\Http\Response;
use Hval\Scalapay\Response\OrderDetails;
use Hval\Scalapay\Status;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(OrderDetails::class)]
#[UsesClass(Response::class)]
final class OrderDetailsTest extends TestCase
{
    public function testItReadsTheThreeAxesOfAPendingOrder(): void
    {
        $details = self::detailsFrom(self::sandboxPayload());

        self::assertSame('SEOHZVUVN5VS', $details->getToken());
        self::assertSame('pending', $details->getStatus());
        self::assertSame('pending', $details->getCaptureStatus());
        self::assertFalse($details->isAuthorized());
        self::assertFalse($details->isCharged());
        self::assertFalse($details->isExpired());
    }

    /**
     * The sandbox returned the reference nested inside orderDetails and nothing at the top
     * level, which is the opposite of what the specification examples show.
     */
    public function testItFindsTheMerchantReferenceNestedInsideOrderDetails(): void
    {
        $details = self::detailsFrom(self::sandboxPayload());

        self::assertSame('sandbox-b-20261001-095053', $details->getMerchantReference());
    }

    public function testTheTopLevelMerchantReferenceStillWins(): void
    {
        $payload = self::sandboxPayload();
        $payload['merchantReference'] = 'top-level';

        self::assertSame('top-level', self::detailsFrom($payload)->getMerchantReference());
    }

    public function testTheMerchantReferenceIsNullWhenItIsNowhere(): void
    {
        $payload = self::sandboxPayload();
        unset($payload['orderDetails']['merchantReference']);

        self::assertNull(self::detailsFrom($payload)->getMerchantReference());
    }

    public function testItExposesWhyEligibilityWasDeclined(): void
    {
        $details = self::detailsFrom(self::sandboxPayload());

        self::assertSame('DECLINED_PAYMENT_AUTHORIZATION', $details->getLastEligibilityFeedback());
    }

    public function testTheEligibilityFeedbackIsNullOnAHealthyOrder(): void
    {
        $payload = self::sandboxPayload();
        unset($payload['lastEligibilityFeedback']);

        self::assertNull(self::detailsFrom($payload)->getLastEligibilityFeedback());
    }

    public function testAnAuthorizedOrderIsRecognised(): void
    {
        $payload = self::sandboxPayload();
        $payload['status'] = 'authorized';

        self::assertTrue(self::detailsFrom($payload)->isAuthorized());
    }

    public function testAnExpiredOrderKeepsItsCaptureStatusPending(): void
    {
        $payload = self::sandboxPayload();
        $payload['status'] = 'expired';

        $details = self::detailsFrom($payload);

        self::assertTrue($details->isExpired());
        self::assertSame('pending', $details->getCaptureStatus());
        self::assertFalse($details->isCaptured());
    }

    /**
     * The order status and the capture status are distinct axes: a predicate must look
     * at its own axis only.
     */
    #[DataProvider('orderStatuses')]
    public function testEachOrderStatusMatchesOnlyItsOwnPredicate(string $status): void
    {
        $details = self::detailsFrom(['token' => 'T', 'status' => $status, 'captureStatus' => Status::CAPTURE_CAPTURED]);

        self::assertSame([
            Status::ORDER_AUTHORIZED => $status === Status::ORDER_AUTHORIZED,
            Status::ORDER_CHARGED => $status === Status::ORDER_CHARGED,
            Status::ORDER_REFUNDED => $status === Status::ORDER_REFUNDED,
            Status::ORDER_EXPIRED => $status === Status::ORDER_EXPIRED,
        ], [
            Status::ORDER_AUTHORIZED => $details->isAuthorized(),
            Status::ORDER_CHARGED => $details->isCharged(),
            Status::ORDER_REFUNDED => $details->isRefunded(),
            Status::ORDER_EXPIRED => $details->isExpired(),
        ]);
        self::assertTrue($details->isCaptured());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function orderStatuses(): iterable
    {
        yield Status::ORDER_AUTHORIZED => [Status::ORDER_AUTHORIZED];

        yield Status::ORDER_CHARGED => [Status::ORDER_CHARGED];

        yield Status::ORDER_REFUNDED => [Status::ORDER_REFUNDED];

        yield Status::ORDER_EXPIRED => [Status::ORDER_EXPIRED];
    }

    public function testTheOrderDetailsBlockIsExposedWhenPresent(): void
    {
        self::assertSame('sandbox-b-20261001-095053', self::detailsFrom(self::sandboxPayload())->getOrderDetails()['merchantReference'] ?? null);
        self::assertNull(self::detailsFrom(['token' => 'T'])->getOrderDetails());
    }

    public function testTheWholePayloadStaysAvailable(): void
    {
        $details = self::detailsFrom(self::sandboxPayload());

        self::assertSame(100, $details->getRawPayload()['totalAmount']['amount']);
    }

    /**
     * Captured verbatim from the sandbox on 2026-10-01. Note that the top-level
     * totalAmount is a JSON number while the nested one is a string: the same payload
     * spells the same amount two ways.
     *
     * @return array<string, mixed>
     */
    private static function sandboxPayload(): array
    {
        return [
            'token' => 'SEOHZVUVN5VS',
            'created' => '2026-10-01T09:50:54.000Z',
            'status' => 'pending',
            'captureStatus' => 'pending',
            'totalAmount' => ['amount' => 100, 'currency' => 'EUR'],
            'orderDetails' => [
                'items' => [[
                    'sku' => 'SKU-1',
                    'name' => 'Sandbox item',
                    'price' => ['amount' => '100.00', 'currency' => 'EUR'],
                    'category' => 'test',
                    'quantity' => 1,
                ]],
                'consumer' => [
                    'email' => 'test@scalapay.com',
                    'surname' => 'Rossi',
                    'givenNames' => 'Mario',
                    'phoneNumber' => '+393001234567',
                ],
                'totalAmount' => ['amount' => '100.00', 'currency' => 'EUR'],
                'merchantReference' => 'sandbox-b-20261001-095053',
            ],
            'lastEligibilityFeedback' => 'DECLINED_PAYMENT_AUTHORIZATION',
        ];
    }

    /**
     * @param array<string, mixed> $payload
     */
    private static function detailsFrom(array $payload): OrderDetails
    {
        return OrderDetails::fromResponse(
            new Response(200, [], $payload, (string) json_encode($payload))
        );
    }
}
