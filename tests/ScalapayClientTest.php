<?php declare(strict_types=1);

namespace Hval\Scalapay\Tests;

use Hval\Scalapay\Config;
use Hval\Scalapay\Environment;
use Hval\Scalapay\Exception\ApiException;
use Hval\Scalapay\Exception\AuthenticationException;
use Hval\Scalapay\Exception\ConflictException;
use Hval\Scalapay\Exception\InvalidRequestException;
use Hval\Scalapay\Exception\ScalapayException;
use Hval\Scalapay\Exception\TransportException;
use Hval\Scalapay\Exception\UnprocessableEntityException;
use Hval\Scalapay\Http\Response;
use Hval\Scalapay\Http\Transport;
use Hval\Scalapay\Model\Address;
use Hval\Scalapay\Model\Amount;
use Hval\Scalapay\Model\Consumer;
use Hval\Scalapay\Model\Item;
use Hval\Scalapay\Model\Merchant;
use Hval\Scalapay\Model\OrderRequest;
use Hval\Scalapay\Response\MerchantConfiguration;
use Hval\Scalapay\Response\OperationResult;
use Hval\Scalapay\Response\OrderCreated;
use Hval\Scalapay\Response\OrderDetails;
use Hval\Scalapay\Response\OrderReference;
use Hval\Scalapay\Response\OrderUpdated;
use Hval\Scalapay\Response\ProductConfiguration;
use Hval\Scalapay\Response\RefundResult;
use Hval\Scalapay\Response\VoidResult;
use Hval\Scalapay\ScalapayClient;
use Hval\Scalapay\Tests\Fake\FakeHttpClient;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * @internal
 */
#[CoversClass(ScalapayClient::class)]
#[CoversClass(Transport::class)]
#[CoversClass(Response::class)]
#[CoversClass(Config::class)]
#[CoversClass(Environment::class)]
#[CoversClass(OrderCreated::class)]
#[CoversClass(OperationResult::class)]
#[CoversClass(OrderDetails::class)]
#[CoversClass(OrderReference::class)]
#[CoversClass(OrderUpdated::class)]
#[CoversClass(RefundResult::class)]
#[CoversClass(VoidResult::class)]
#[CoversClass(MerchantConfiguration::class)]
#[CoversClass(ProductConfiguration::class)]
#[UsesClass(Address::class)]
#[UsesClass(Amount::class)]
#[UsesClass(ApiException::class)]
#[UsesClass(Consumer::class)]
#[UsesClass(InvalidRequestException::class)]
#[UsesClass(Item::class)]
#[UsesClass(Merchant::class)]
#[UsesClass(OrderRequest::class)]
final class ScalapayClientTest extends TestCase
{
    private FakeHttpClient $httpClient;

    private ScalapayClient $client;

    protected function setUp(): void
    {
        $factory = new Psr17Factory();
        $this->httpClient = new FakeHttpClient();
        $this->client = new ScalapayClient(Config::sandbox('sp_test'), $this->httpClient, $factory);
    }

    public function testItCreatesAnOrderAndSendsTheBearerToken(): void
    {
        $this->httpClient->queue(200, '{"token":"71KH916VPE","checkoutUrl":"https://portal.scalapay.com/checkout/71KH916VPE"}');

        $order = $this->client->createOrder($this->anOrderRequest(), 'idem-1');

        self::assertSame('71KH916VPE', $order->getToken());
        self::assertSame('https://portal.scalapay.com/checkout/71KH916VPE', $order->getCheckoutUrl());
        self::assertNull($order->getExpires());

        $request = $this->httpClient->getLastRequest();
        self::assertNotNull($request);
        self::assertSame('Bearer sp_test', $request->getHeaderLine('Authorization'));
        self::assertSame('idem-1', $request->getHeaderLine('Idempotency-Key'));
        self::assertSame('https://integration.api.scalapay.com/v2/orders', (string) $request->getUri());
    }

    public function testTheOrderBodyKeepsShippingEvenWhenEmpty(): void
    {
        $this->httpClient->queue(200, '{"token":"T"}');

        $this->client->createOrder($this->anOrderRequest(Address::empty()));

        // Decoding cannot tell `[]` from `{}`, and the API rejects the former: check the
        // bytes actually sent.
        self::assertStringContainsString('"shipping":{}', (string) $this->httpClient->getLastRequest()?->getBody());
    }

    public function testItReportsAnIdempotentReplay(): void
    {
        $this->httpClient->queue(200, '{"token":"T"}', ['Request-Idempotency' => 'true']);

        self::assertTrue($this->client->createOrder($this->anOrderRequest())->isIdempotentReplay());
    }

    public function testCaptureSendsTheTokenInTheBodyNotInThePath(): void
    {
        $this->httpClient->queue(200, '{"token":"T","status":"APPROVED"}');

        $result = $this->client->capture('T', new Amount('40.70'), 'merchantOrder-updated');

        self::assertSame('APPROVED', $result->getStatus());

        $request = $this->httpClient->getLastRequest();
        self::assertNotNull($request);
        self::assertSame('https://integration.api.scalapay.com/v2/payments/capture', (string) $request->getUri());
        self::assertSame([
            'token' => 'T',
            'merchantReference' => 'merchantOrder-updated',
            'amount' => ['amount' => '40.70', 'currency' => 'EUR'],
        ], $this->httpClient->getLastRequestBody());
    }

    public function testCaptureWithoutAmountCapturesTheFullOrder(): void
    {
        $this->httpClient->queue(200, '{"token":"T","status":"APPROVED"}');

        $this->client->capture('T');

        self::assertSame(['token' => 'T'], $this->httpClient->getLastRequestBody());
    }

    public function testDelayCaptureSendsTheFiveDayDefaultInsteadOfAnEmptyBody(): void
    {
        $this->httpClient->queue(200, '{"token":"T","status":"AUTHORIZED"}');

        $this->client->delayCapture('T');

        self::assertSame(['authorizationExpiryMilliseconds' => 432000000], $this->httpClient->getLastRequestBody());
    }

    public function testDelayCaptureSendsAnExplicitExpiryAndTheReference(): void
    {
        $this->httpClient->queue(200, '{"token":"T","status":"AUTHORIZED"}');

        $this->client->delayCapture('T', 86400000, 'merchantOrder-1234');

        self::assertSame(['authorizationExpiryMilliseconds' => 86400000, 'merchantReference' => 'merchantOrder-1234'], $this->httpClient->getLastRequestBody());
    }

    public function testVoidSendsTheReferenceWhenGiven(): void
    {
        $this->httpClient->queue(200, '{"token":"T","voidedAt":"2021-02-25T21:29:41.835Z"}');

        $this->client->voidOrder('T', 'RF127261AD22');

        self::assertSame('{"merchantReference":"RF127261AD22"}', (string) $this->httpClient->getLastRequest()?->getBody());
    }

    public function testVoidSendsNoBodyWhenNoReferenceIsGiven(): void
    {
        $this->httpClient->queue(200, '{"token":"B1KLLDPMIH","voidedAt":"2021-02-25T21:29:41.835Z"}');

        $result = $this->client->voidOrder('B1KLLDPMIH');

        self::assertSame('2021-02-25T21:29:41.835Z', $result->getVoidedAt());
        self::assertSame('', (string) $this->httpClient->getLastRequest()?->getBody());
    }

    public function testRefundDoesNotSendAMerchantReference(): void
    {
        $this->httpClient->queue(200, '{"token":"71KH916VPE","refundToken":"pre_transfer","amount":{"amount":"190.00","currency":"EUR"}}');

        $result = $this->client->refund('71KH916VPE', new Amount('190.00'), 'ABC123');

        self::assertSame('190.00', $result->getAmount()?->getAmount());
        self::assertSame('pre_transfer', $result->getRefundToken());

        $body = $this->httpClient->getLastRequestBody();
        self::assertArrayNotHasKey('merchantReference', $body);
        self::assertSame('ABC123', $body['merchantRefundReference']);
    }

    public function testSearchingByReferenceRequiresAtLeastOneCriterion(): void
    {
        $this->expectException(ScalapayException::class);

        $this->client->getOrdersByReference();
    }

    public function testSearchingByReferenceReadsATopLevelArray(): void
    {
        $this->httpClient->queue(200, '[{"orderToken":"71KH916VPE","merchantOrderReference":"merchantOrder-1234","merchantProcessorReference":null,"createdAt":"2024-11-08T11:26:17.000Z"}]');

        $references = $this->client->getOrdersByReference(merchantOrderReference: 'merchantOrder-1234');

        self::assertCount(1, $references);
        self::assertSame('71KH916VPE', $references[0]->getOrderToken());
        self::assertNull($references[0]->getMerchantProcessorReference());
        self::assertStringContainsString('merchantOrderReference=merchantOrder-1234', (string) $this->httpClient->getLastRequest()?->getUri());
    }

    public function testTheMerchantConfigurationIsReadFromTheUndocumentedV3Path(): void
    {
        $this->httpClient->queue(200, '[{"type":"online","product":"pay-in-3","configuration":{"minimumAmount":{"amount":5,"currency":"EUR"},"maximumAmount":{"amount":4999.99,"currency":"EUR"}}}]');

        $configuration = $this->client->getMerchantConfiguration();

        self::assertCount(1, $configuration->all());
        self::assertSame('pay-in-3', $configuration->all()[0]->getProduct());
        self::assertSame('GET', $this->httpClient->getLastRequest()?->getMethod());
        self::assertSame('/v3/configurations', $this->httpClient->getLastRequest()->getUri()->getPath());
    }

    public function testUpdatingReferencesOmitsOnlyWhatIsExplicitlyCleared(): void
    {
        $this->httpClient->queue(200, '{"token":"T","merchantReference":"kept"}');

        $updated = $this->client->updateOrderReferences('T', 'kept', null);

        self::assertSame('kept', $updated->getMerchantReference());
        self::assertSame(['merchantReference' => 'kept'], $this->httpClient->getLastRequestBody());
    }

    public function testUpdatingReferencesSendsBothWhenBothAreGiven(): void
    {
        $this->httpClient->queue(200, '{"token":"T"}');

        $this->client->updateOrderReferences('T', 'merchantOrder-1234', 'processorOrder-1234');

        self::assertSame(['merchantReference' => 'merchantOrder-1234', 'merchantProcessorReference' => 'processorOrder-1234'], $this->httpClient->getLastRequestBody());
    }

    /**
     * The endpoint replaces rather than merges: an empty object clears both references,
     * and it must travel as `{}`, never as `[]`.
     */
    public function testClearingBothReferencesSendsAnEmptyObject(): void
    {
        $this->httpClient->queue(200, '{"token":"T"}');

        $this->client->updateOrderReferences('T', null, null);

        self::assertSame('{}', (string) $this->httpClient->getLastRequest()?->getBody());
    }

    /**
     * A network failure is not an API error: it must not be caught by a handler written
     * for ApiException, and it must keep the client's exception as its cause.
     */
    public function testANetworkFailureBecomesATransportExceptionKeepingItsCause(): void
    {
        $cause = new class('Connection refused') extends \RuntimeException implements ClientExceptionInterface {};
        $client = new ScalapayClient(Config::sandbox('sp_test'), new class($cause) implements ClientInterface {
            public function __construct(private readonly ClientExceptionInterface $cause)
            {
            }

            public function sendRequest(RequestInterface $request): ResponseInterface
            {
                throw $this->cause;
            }
        }, new Psr17Factory());

        try {
            $client->getOrderDetails('T');
            self::fail('A TransportException was expected.');
        } catch (TransportException $exception) {
            self::assertNotInstanceOf(ApiException::class, $exception);
            self::assertSame($cause, $exception->getPrevious());
            self::assertStringContainsString('/v2/payments/T', $exception->getMessage());
            self::assertStringContainsString('Connection refused', $exception->getMessage());
        }
    }

    /**
     * A 2XX that is not JSON, such as a maintenance page from a proxy, must not pass for
     * an empty success.
     */
    public function testASuccessfulStatusWithABodyThatIsNotJsonIsATransportFailure(): void
    {
        $this->httpClient->queue(200, '<html><body>Maintenance</body></html>');

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('not valid JSON');

        $this->client->getOrderDetails('T');
    }

    public function testABodyThatCannotBeEncodedIsRejectedBeforeAnythingIsSent(): void
    {
        $order = new OrderRequest(
            totalAmount: new Amount('40.70'),
            consumer: new Consumer('Mario', 'Rossi', 'test@scalapay.com', '+393001234567'),
            merchant: new Merchant(Merchant::SANDBOX_CONFIRM_URL, Merchant::SANDBOX_CANCEL_URL),
            shipping: Address::empty(),
            items: [new Item('T-Shirt', '12341234', 'clothes', new Amount('10.00'))],
            additional: ['note' => "\xB1\x31"],
        );

        try {
            $this->client->createOrder($order);
            self::fail('A TransportException was expected.');
        } catch (TransportException $exception) {
            self::assertStringContainsString('encode', $exception->getMessage());
            self::assertSame(0, $this->httpClient->countRequests());
        }
    }

    public function testItExposesTheOrderAndCaptureStatusSeparately(): void
    {
        $this->httpClient->queue(200, '{"token":"T","status":"authorized","captureStatus":"delayed"}');

        $details = $this->client->getOrderDetails('T');

        self::assertTrue($details->isAuthorized());
        self::assertFalse($details->isCaptured());
        self::assertSame('delayed', $details->getCaptureStatus());
    }

    public function testAValidationErrorExposesTheRejectedFields(): void
    {
        $this->httpClient->queue(400, '{"errorCode":"api_validationerror","errorId":"error-19g6ll3ucve9e","message":{"status":400,"statusText":"Bad Request","errors":[{"field":["totalAmount","amount"],"location":"body","messages":["\"amount\" is not allowed to be empty"],"types":["any.empty"]}]},"httpStatusCode":400}');

        try {
            $this->client->createOrder($this->anOrderRequest());
            self::fail('An InvalidRequestException was expected.');
        } catch (InvalidRequestException $exception) {
            self::assertSame('api_validationerror', $exception->getErrorCode());
            self::assertSame('error-19g6ll3ucve9e', $exception->getErrorId());
            self::assertSame(['totalAmount.amount'], $exception->getInvalidFields());
            self::assertStringContainsString('is not allowed to be empty', $exception->getMessage());
            self::assertFalse($exception->isRetryable());
        }
    }

    public function testABareUnauthorizedStringBecomesAnAuthenticationException(): void
    {
        $this->httpClient->queue(401, '"Unauthorized"');

        $this->expectException(AuthenticationException::class);

        $this->client->getOrderDetails('T');
    }

    public function testTheBareUnauthorizedStringBecomesTheMessage(): void
    {
        $this->httpClient->queue(401, '"Unauthorized"');

        try {
            $this->client->getOrderDetails('T');
            self::fail('An AuthenticationException was expected.');
        } catch (AuthenticationException $exception) {
            self::assertSame('Unauthorized', $exception->getMessage());
            self::assertSame('Unauthorized', $exception->getApiMessage());
            self::assertSame('"Unauthorized"', $exception->getRawBody());
        }
    }

    public function testAForbiddenResponseIsAnAuthenticationFailureToo(): void
    {
        $this->httpClient->queue(403, '{"errorCode":"forbidden","message":"Forbidden"}');

        $this->expectException(AuthenticationException::class);

        $this->client->getOrderDetails('T');
    }

    public function testAnUnmappedStatusFallsBackToThePlainApiException(): void
    {
        $this->httpClient->queue(500, '{"errorCode":"internal_error","errorId":"error-2","message":"Something went wrong"}');

        try {
            $this->client->getOrderDetails('T');
            self::fail('An ApiException was expected.');
        } catch (ApiException $exception) {
            self::assertSame(ApiException::class, $exception::class);
            self::assertSame(500, $exception->getHttpStatus());
            self::assertSame('error-2', $exception->getErrorId());
        }
    }

    public function testAnErrorBodyThatIsNotJsonKeepsTheHttpStatus(): void
    {
        $this->httpClient->queue(502, '<html><body>Bad Gateway</body></html>');

        try {
            $this->client->getOrderDetails('T');
            self::fail('An ApiException was expected.');
        } catch (ApiException $exception) {
            self::assertSame(502, $exception->getHttpStatus());
            self::assertSame([], $exception->getPayload());
            self::assertSame('<html><body>Bad Gateway</body></html>', $exception->getRawBody());
            self::assertStringContainsString('HTTP 502', $exception->getMessage());
        }
    }

    public function testAConflictIsReportedAsRetryable(): void
    {
        $this->httpClient->queue(409, '{"errorCode":"conflicting_operation_in_progress","errorId":"error-19g6iml8px18g","message":"Try again later.","httpStatusCode":409}');

        try {
            $this->client->capture('T');
            self::fail('A ConflictException was expected.');
        } catch (ConflictException $exception) {
            self::assertTrue($exception->isRetryable());
            self::assertSame('Try again later.', $exception->getMessage());
        }
    }

    public function testAnInvalidTokenIsReportedAsUnprocessable(): void
    {
        $this->httpClient->queue(422, '{"errorCode":"invalid_token","errorId":"error-1","message":"Invalid token","httpStatusCode":422}');

        $this->expectException(UnprocessableEntityException::class);

        $this->client->getOrderDetails('nope');
    }

    private function anOrderRequest(?Address $shipping = null): OrderRequest
    {
        return new OrderRequest(
            totalAmount: new Amount('40.70'),
            consumer: new Consumer('Mario', 'Rossi', 'test@scalapay.com', '+393001234567'),
            merchant: new Merchant(Merchant::SANDBOX_CONFIRM_URL, Merchant::SANDBOX_CANCEL_URL),
            shipping: $shipping ?? new Address('Mario Rossi', 'Via Puglia, 58', 'Castelfranco Veneto', '31033', 'IT', '+393001234567'),
            items: [new Item('T-Shirt', '12341234', 'clothes', new Amount('10.00'))],
        );
    }
}
