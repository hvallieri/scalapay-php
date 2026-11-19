<?php declare(strict_types=1);

namespace Hval\Scalapay\Tests\Webhook;

use Hval\Scalapay\Status;
use Hval\Scalapay\Webhook\WebhookPayload;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(WebhookPayload::class)]
final class WebhookPayloadTest extends TestCase
{
    public function testItReadsTheFourKeysEveryNotificationCarries(): void
    {
        $payload = self::payloadFrom('{"merchantReference":"merchantOrder-1234","totalAmount":40.70,"status":"charged","orderToken":"21KLT3YDWT"}');

        self::assertSame('21KLT3YDWT', $payload->getOrderToken());
        self::assertSame('merchantOrder-1234', $payload->getMerchantReference());
        self::assertSame(Status::ORDER_CHARGED, $payload->getStatus());
        self::assertSame('40.70', $payload->getTotalAmountAsString());
    }

    #[DataProvider('statuses')]
    public function testExactlyOnePredicateMatchesEachStatus(string $status): void
    {
        $payload = self::payloadFrom(sprintf('{"status":"%s"}', $status));

        self::assertSame([
            Status::ORDER_CREATED => $status === Status::ORDER_CREATED,
            Status::ORDER_AUTHORIZED => $status === Status::ORDER_AUTHORIZED,
            Status::ORDER_CHARGED => $status === Status::ORDER_CHARGED,
            Status::ORDER_REFUNDED => $status === Status::ORDER_REFUNDED,
            Status::ORDER_EXPIRED => $status === Status::ORDER_EXPIRED,
        ], [
            Status::ORDER_CREATED => $payload->isCreated(),
            Status::ORDER_AUTHORIZED => $payload->isAuthorized(),
            Status::ORDER_CHARGED => $payload->isCharged(),
            Status::ORDER_REFUNDED => $payload->isRefunded(),
            Status::ORDER_EXPIRED => $payload->isExpired(),
        ]);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function statuses(): iterable
    {
        yield Status::ORDER_CREATED => [Status::ORDER_CREATED];

        yield Status::ORDER_AUTHORIZED => [Status::ORDER_AUTHORIZED];

        yield Status::ORDER_CHARGED => [Status::ORDER_CHARGED];

        yield Status::ORDER_REFUNDED => [Status::ORDER_REFUNDED];

        yield Status::ORDER_EXPIRED => [Status::ORDER_EXPIRED];
    }

    public function testATotalAmountSentAsAStringIsReadFromTheDecodedPayload(): void
    {
        $payload = self::payloadFrom('{"totalAmount":"40.70","status":"charged"}');

        self::assertSame('40.70', $payload->getTotalAmountAsString());
        self::assertNull($payload->getTotalAmount());
    }

    public function testAnIntegerTotalAmountStaysAnInteger(): void
    {
        $payload = self::payloadFrom('{"totalAmount":10,"status":"charged"}');

        self::assertSame('10', $payload->getTotalAmountAsString());
        self::assertSame(10, $payload->getTotalAmount());
    }

    public function testAMissingTotalAmountIsNull(): void
    {
        $payload = self::payloadFrom('{"status":"charged"}');

        self::assertNull($payload->getTotalAmountAsString());
        self::assertNull($payload->getTotalAmount());
    }

    /**
     * The nested `orderDetails.totalAmount` is an object, so the literal of the top-level
     * key is found even when it comes later in the body.
     */
    public function testTheTopLevelTotalAmountWinsOverTheOneNestedBeforeIt(): void
    {
        $payload = self::payloadFrom('{"orderDetails":{"totalAmount":{"amount":"99.00","currency":"EUR"}},"totalAmount":40.70,"status":"created"}');

        self::assertSame('40.70', $payload->getTotalAmountAsString());
        self::assertSame(['amount' => '99.00', 'currency' => 'EUR'], $payload->getOrderDetails()['totalAmount'] ?? null);
    }

    public function testOrderDetailsAreNullWhenAbsentOrNotAnObject(): void
    {
        self::assertNull(self::payloadFrom('{"status":"charged"}')->getOrderDetails());
        self::assertNull(self::payloadFrom('{"status":"created","orderDetails":"none"}')->getOrderDetails());
    }

    public function testTheRawBodyIsKeptExactlyAndNonScalarFieldsAreNull(): void
    {
        $rawBody = '{"orderToken":["T"],"merchantReference":{"id":1},"status":"charged"}';

        $payload = self::payloadFrom($rawBody);

        self::assertSame($rawBody, $payload->getRawBody());
        self::assertNull($payload->getOrderToken());
        self::assertNull($payload->getMerchantReference());
    }

    private static function payloadFrom(string $rawBody): WebhookPayload
    {
        $decoded = json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);

        return new WebhookPayload($decoded, $rawBody);
    }
}
