<?php declare(strict_types=1);

namespace Hval\Scalapay\Tests\Response;

use Hval\Scalapay\Exception\ScalapayException;
use Hval\Scalapay\Http\Response;
use Hval\Scalapay\Model\Amount;
use Hval\Scalapay\Response\OperationResult;
use Hval\Scalapay\Response\OrderCreated;
use Hval\Scalapay\Response\OrderReference;
use Hval\Scalapay\Response\OrderUpdated;
use Hval\Scalapay\Response\RefundResult;
use Hval\Scalapay\Response\VoidResult;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * Where the documentation gives one, each resource is read from the official response
 * example of its endpoint, so that a misspelt key shows up as a failure rather than as a
 * silent null.
 *
 * @internal
 */
#[CoversClass(OrderCreated::class)]
#[CoversClass(OperationResult::class)]
#[CoversClass(OrderUpdated::class)]
#[CoversClass(VoidResult::class)]
#[CoversClass(RefundResult::class)]
#[CoversClass(OrderReference::class)]
#[UsesClass(Response::class)]
#[UsesClass(Amount::class)]
final class ResponseResourcesTest extends TestCase
{
    public function testAnOrderCreationReadsTheOfficialExample(): void
    {
        $created = OrderCreated::fromResponse(self::response('{"token":"71KH916VPE","expires":"2024-11-08T13:06:17.858Z","checkoutUrl":"https://portal.scalapay.com/checkout/71KH916VPE"}'));

        self::assertSame('71KH916VPE', $created->getToken());
        self::assertSame('2024-11-08T13:06:17.858Z', $created->getExpires());
        self::assertSame('https://portal.scalapay.com/checkout/71KH916VPE', $created->getCheckoutUrl());
    }

    /**
     * The token is the only field the specification guarantees, and without it the
     * order cannot be followed up at all.
     */
    public function testAnOrderCreationWithoutATokenIsRejected(): void
    {
        $this->expectException(ScalapayException::class);

        OrderCreated::fromResponse(self::response('{"checkoutUrl":"https://portal.scalapay.com/checkout/X"}'));
    }

    public function testAnOperationResultExposesTheOrderDetailsOnlyWhenPresent(): void
    {
        $withDetails = OperationResult::fromResponse(self::response('{"token":"T","status":"AUTHORIZED","orderDetails":{"merchantReference":"merchantOrder-1234"}}'));
        $withoutDetails = OperationResult::fromResponse(self::response('{"token":"T","status":"APPROVED"}'));

        self::assertSame('T', $withDetails->getToken());
        self::assertSame(['merchantReference' => 'merchantOrder-1234'], $withDetails->getOrderDetails());
        self::assertNull($withoutDetails->getOrderDetails());
    }

    /**
     * The documentation lists these fields without a full example: the payloads follow
     * the documented field tables.
     */
    public function testACaptureExposesTheCapturedAmountAndWhenItHappened(): void
    {
        $capture = OperationResult::fromResponse(self::response('{"token":"T","status":"APPROVED","totalAmount":{"amount":"40.70","currency":"EUR"},"capturedAt":"2024-11-08T11:30:00.000Z"}'));

        self::assertSame(['amount' => '40.70', 'currency' => 'EUR'], $capture->getTotalAmount()?->toArray());
        self::assertSame('2024-11-08T11:30:00.000Z', $capture->getCapturedAt());
        self::assertNull($capture->getAuthorizationExpiryAt());
    }

    public function testADelayExposesWhenTheAuthorizationLapses(): void
    {
        $delay = OperationResult::fromResponse(self::response('{"token":"T","status":"AUTHORIZED","authorizationExpiryAt":"2024-11-13T11:30:00.000Z"}'));

        self::assertSame('2024-11-13T11:30:00.000Z', $delay->getAuthorizationExpiryAt());
        self::assertNull($delay->getCapturedAt());
        self::assertNull($delay->getTotalAmount());
    }

    public function testAReferenceUpdateReadsBothReferences(): void
    {
        $updated = OrderUpdated::fromResponse(self::response('{"token":"71KH916VPE","merchantReference":"merchantOrder-1234","merchantProcessorReference":"processorOrder-1234"}'));

        self::assertSame('71KH916VPE', $updated->getToken());
        self::assertSame('merchantOrder-1234', $updated->getMerchantReference());
        self::assertSame('processorOrder-1234', $updated->getMerchantProcessorReference());
    }

    public function testAVoidReadsTheOfficialExample(): void
    {
        $void = VoidResult::fromResponse(self::response('{"token":"B1KLLDPMIH","merchantReference":"RF127261AD22","voidedAt":"2021-02-25T21:29:41.835Z"}'));

        self::assertSame('B1KLLDPMIH', $void->getToken());
        self::assertSame('RF127261AD22', $void->getMerchantReference());
        self::assertSame('2021-02-25T21:29:41.835Z', $void->getVoidedAt());
    }

    public function testARefundReadsTheOfficialExample(): void
    {
        $refund = RefundResult::fromResponse(self::response('{"token":"71KH916VPE","amount":{"amount":"190.00","currency":"EUR"},"merchantReference":"ABC123","merchantRefundReference":"ABC123","refundToken":"pre_transfer","refundedAt":"2024-11-08T11:32:06.502Z"}'));

        self::assertSame('71KH916VPE', $refund->getToken());
        self::assertSame(['amount' => '190.00', 'currency' => 'EUR'], $refund->getAmount()?->toArray());
        self::assertSame('ABC123', $refund->getMerchantReference());
        self::assertSame('ABC123', $refund->getMerchantRefundReference());
        self::assertSame('pre_transfer', $refund->getRefundToken());
        self::assertSame('2024-11-08T11:32:06.502Z', $refund->getRefundedAt());
    }

    /**
     * Only the token is guaranteed: every other field may be missing.
     */
    public function testARefundWithOnlyItsTokenYieldsNullsRatherThanFailing(): void
    {
        $refund = RefundResult::fromResponse(self::response('{"token":"T"}'));

        self::assertNull($refund->getAmount());
        self::assertNull($refund->getRefundToken());
        self::assertNull($refund->getRefundedAt());
    }

    public function testASearchByReferenceReadsTheOfficialExample(): void
    {
        $references = OrderReference::listFromResponse(self::response('[{"orderToken":"71KH916VPE","merchantOrderReference":"merchantOrder-1234","merchantProcessorReference":"processor-ref-5678","createdAt":"2024-11-08T11:26:17.000Z"},{"orderToken":"71KH916VPD","merchantOrderReference":"merchantOrder-1234","merchantProcessorReference":null,"createdAt":"2024-11-07T09:15:32.000Z"}]'));

        self::assertCount(2, $references);
        self::assertSame('merchantOrder-1234', $references[0]->getMerchantOrderReference());
        self::assertSame('processor-ref-5678', $references[0]->getMerchantProcessorReference());
        self::assertSame('2024-11-08T11:26:17.000Z', $references[0]->getCreatedAt());
        self::assertSame('71KH916VPD', $references[1]->getOrderToken());
        self::assertNull($references[1]->getMerchantProcessorReference());
    }

    public function testASearchByReferenceSkipsEntriesThatAreNotObjects(): void
    {
        $references = OrderReference::listFromResponse(self::response('[1,{"orderToken":"T"},"noise"]'));

        self::assertCount(1, $references);
        self::assertSame('T', $references[0]->getOrderToken());
    }

    public function testAReplayedResponseIsReportedByEveryWriteResult(): void
    {
        $replayed = self::response('{"token":"T"}', ['request-idempotency' => ['true']]);

        self::assertTrue(OrderCreated::fromResponse($replayed)->isIdempotentReplay());
        self::assertTrue(OperationResult::fromResponse($replayed)->isIdempotentReplay());
        self::assertTrue(OrderUpdated::fromResponse($replayed)->isIdempotentReplay());
        self::assertTrue(VoidResult::fromResponse($replayed)->isIdempotentReplay());
        self::assertTrue(RefundResult::fromResponse($replayed)->isIdempotentReplay());
        self::assertFalse(RefundResult::fromResponse(self::response('{"token":"T"}'))->isIdempotentReplay());
    }

    /**
     * @param array<string, list<string>> $headers header names already lower-cased
     */
    private static function response(string $rawBody, array $headers = []): Response
    {
        return new Response(200, $headers, json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR), $rawBody);
    }
}
