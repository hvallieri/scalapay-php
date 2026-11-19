<?php declare(strict_types=1);

namespace Hval\Scalapay\Tests\Webhook;

use Hval\Scalapay\Exception\WebhookSignatureException;
use Hval\Scalapay\Status;
use Hval\Scalapay\Webhook\WebhookHandler;
use Hval\Scalapay\Webhook\WebhookPayload;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(WebhookHandler::class)]
#[CoversClass(WebhookPayload::class)]
final class WebhookHandlerTest extends TestCase
{
    private const API_KEY = 'sp_test_key';

    public function testItAcceptsAPayloadSignedWithTheApiKey(): void
    {
        $handler = new WebhookHandler(self::API_KEY);
        $rawBody = '{"merchantReference":"merchantOrder-1234","totalAmount":40.70,"status":"charged","orderToken":"21KLT3YDWT"}';
        $timestamp = '1234567890123';

        $payload = $handler->handle($rawBody, [
            'X-Scalapay-Hmac-V1' => $handler->sign($rawBody, $timestamp),
            'X-Scalapay-Timestamp' => $timestamp,
        ]);

        self::assertSame('21KLT3YDWT', $payload->getOrderToken());
        self::assertSame(Status::ORDER_CHARGED, $payload->getStatus());
        self::assertTrue($payload->isCharged());
    }

    public function testItSignsTheDocumentedStringShape(): void
    {
        $handler = new WebhookHandler(self::API_KEY);
        $expected = hash_hmac('sha256', 'V1:1234567890123:{"a":1}', self::API_KEY);

        self::assertSame($expected, $handler->sign('{"a":1}', '1234567890123'));
    }

    public function testItRejectsATamperedPayload(): void
    {
        $handler = new WebhookHandler(self::API_KEY);
        $timestamp = '1234567890123';
        $signature = $handler->sign('{"status":"charged"}', $timestamp);

        $this->expectException(WebhookSignatureException::class);

        $handler->handle('{"status":"refunded"}', [
            'x-scalapay-hmac-v1' => $signature,
            'x-scalapay-timestamp' => $timestamp,
        ]);
    }

    public function testItRejectsAMissingSignatureHeader(): void
    {
        $handler = new WebhookHandler(self::API_KEY);

        $this->expectException(WebhookSignatureException::class);

        $handler->handle('{}', ['x-scalapay-timestamp' => '1234567890123']);
    }

    public function testItPreservesTrailingZerosOfTheTotalAmount(): void
    {
        $handler = new WebhookHandler(self::API_KEY);
        $rawBody = '{"totalAmount":40.70,"status":"created","orderToken":"21KLT3YDWT","merchantReference":"ref"}';
        $timestamp = '1234567890123';

        $payload = $handler->handle($rawBody, [
            'x-scalapay-hmac-v1' => $handler->sign($rawBody, $timestamp),
            'x-scalapay-timestamp' => $timestamp,
        ]);

        self::assertSame('40.70', $payload->getTotalAmountAsString());
        self::assertSame(40.70, $payload->getTotalAmount());
    }

    public function testItKeepsTheRawPayloadForStatusesCarryingExtraKeys(): void
    {
        $handler = new WebhookHandler(self::API_KEY);
        $rawBody = '{"status":"created","orderToken":"T","merchantReference":"r","totalAmount":10,"orderDetails":{"taxAmount":{"amount":"3.70","currency":"EUR"}}}';
        $timestamp = '1234567890123';

        $payload = $handler->handle($rawBody, [
            'x-scalapay-hmac-v1' => $handler->sign($rawBody, $timestamp),
            'x-scalapay-timestamp' => $timestamp,
        ]);

        self::assertTrue($payload->isCreated());
        self::assertSame(['amount' => '3.70', 'currency' => 'EUR'], $payload->getOrderDetails()['taxAmount']);
        self::assertArrayHasKey('orderDetails', $payload->getRawPayload());
    }

    public function testToleranceIsDisabledByDefaultSoOldTimestampsAreAccepted(): void
    {
        $handler = new WebhookHandler(self::API_KEY);
        $rawBody = '{"status":"charged"}';
        $timestamp = '1234567890123';

        $payload = $handler->handle($rawBody, [
            'x-scalapay-hmac-v1' => $handler->sign($rawBody, $timestamp),
            'x-scalapay-timestamp' => $timestamp,
        ]);

        self::assertSame(Status::ORDER_CHARGED, $payload->getStatus());
    }

    public function testAnExplicitToleranceRejectsAStaleTimestamp(): void
    {
        $handler = new WebhookHandler(self::API_KEY, 300);
        $rawBody = '{"status":"charged"}';
        $timestamp = '1234567890123';

        $this->expectException(WebhookSignatureException::class);

        $handler->handle($rawBody, [
            'x-scalapay-hmac-v1' => $handler->sign($rawBody, $timestamp),
            'x-scalapay-timestamp' => $timestamp,
        ]);
    }

    public function testAnUnknownStatusIsReturnedAsIsInsteadOfFailing(): void
    {
        $handler = new WebhookHandler(self::API_KEY);
        $rawBody = '{"status":"partially_charged","orderToken":"T"}';
        $timestamp = '1234567890123';

        $payload = $handler->handle($rawBody, [
            'x-scalapay-hmac-v1' => $handler->sign($rawBody, $timestamp),
            'x-scalapay-timestamp' => $timestamp,
        ]);

        self::assertSame('partially_charged', $payload->getStatus());
        self::assertFalse($payload->isCharged());
    }

    public function testItHandlesAPsr7RequestWhateverTheCaseOfItsHeaders(): void
    {
        $handler = new WebhookHandler(self::API_KEY);
        $rawBody = '{"status":"authorized","orderToken":"T"}';
        $timestamp = '1234567890123';
        $factory = new Psr17Factory();

        $request = $factory->createServerRequest('POST', '/webhooks/scalapay')
            ->withHeader('X-SCALAPAY-HMAC-V1', $handler->sign($rawBody, $timestamp))
            ->withHeader('x-Scalapay-Timestamp', $timestamp)
            ->withBody($factory->createStream($rawBody))
        ;

        self::assertTrue($handler->handleRequest($request)->isAuthorized());
    }

    public function testItRewindsABodyThatWasAlreadyRead(): void
    {
        $handler = new WebhookHandler(self::API_KEY);
        $rawBody = '{"status":"charged","orderToken":"T"}';
        $timestamp = '1234567890123';
        $factory = new Psr17Factory();
        $stream = $factory->createStream($rawBody);
        $stream->getContents();

        $request = $factory->createServerRequest('POST', '/webhooks/scalapay')
            ->withHeader(WebhookHandler::HEADER_SIGNATURE, $handler->sign($rawBody, $timestamp))
            ->withHeader(WebhookHandler::HEADER_TIMESTAMP, $timestamp)
            ->withBody($stream)
        ;

        self::assertSame($rawBody, $handler->handleRequest($request)->getRawBody());
    }

    public function testItRejectsAMissingTimestampHeader(): void
    {
        $handler = new WebhookHandler(self::API_KEY);

        $this->expectException(WebhookSignatureException::class);
        $this->expectExceptionMessage(WebhookHandler::HEADER_TIMESTAMP);

        $handler->handle('{}', [WebhookHandler::HEADER_SIGNATURE => $handler->sign('{}', '1234567890123')]);
    }

    public function testItRejectsAnEmptySignatureHeader(): void
    {
        $handler = new WebhookHandler(self::API_KEY);

        $this->expectException(WebhookSignatureException::class);
        $this->expectExceptionMessage(WebhookHandler::HEADER_SIGNATURE);

        $handler->handle('{}', [WebhookHandler::HEADER_SIGNATURE => '', WebhookHandler::HEADER_TIMESTAMP => '1234567890123']);
    }

    public function testItReadsTheFirstValueOfHeadersGivenAsLists(): void
    {
        $handler = new WebhookHandler(self::API_KEY);
        $rawBody = '{"status":"charged"}';
        $timestamp = '1234567890123';

        $payload = $handler->handle($rawBody, [
            WebhookHandler::HEADER_SIGNATURE => [$handler->sign($rawBody, $timestamp), 'ignored'],
            WebhookHandler::HEADER_TIMESTAMP => [$timestamp],
        ]);

        self::assertTrue($payload->isCharged());
    }

    public function testAnExplicitToleranceAcceptsACurrentTimestamp(): void
    {
        $handler = new WebhookHandler(self::API_KEY, 300);
        $rawBody = '{"status":"charged"}';
        $timestamp = (string) (time() * 1000);

        self::assertTrue($handler->handle($rawBody, self::signedHeaders($handler, $rawBody, $timestamp))->isCharged());
    }

    public function testAnExplicitToleranceRejectsATimestampTooFarInTheFuture(): void
    {
        $handler = new WebhookHandler(self::API_KEY, 300);
        $rawBody = '{"status":"charged"}';
        $timestamp = (string) ((time() + 3600) * 1000);

        $this->expectException(WebhookSignatureException::class);
        $this->expectExceptionMessage('tolerance');

        $handler->handle($rawBody, self::signedHeaders($handler, $rawBody, $timestamp));
    }

    public function testAnExplicitToleranceRejectsATimestampThatIsNotNumeric(): void
    {
        $handler = new WebhookHandler(self::API_KEY, 300);
        $rawBody = '{"status":"charged"}';

        $this->expectException(WebhookSignatureException::class);
        $this->expectExceptionMessage('not numeric');

        $handler->handle($rawBody, self::signedHeaders($handler, $rawBody, 'yesterday'));
    }

    public function testWithoutToleranceATimestampThatIsNotNumericIsOnlyPartOfTheSignature(): void
    {
        $handler = new WebhookHandler(self::API_KEY);
        $rawBody = '{"status":"charged"}';

        self::assertTrue($handler->handle($rawBody, self::signedHeaders($handler, $rawBody, 'yesterday'))->isCharged());
    }

    public function testItRejectsASignedBodyThatIsNotJson(): void
    {
        $handler = new WebhookHandler(self::API_KEY);
        $rawBody = 'status=charged';

        $this->expectException(WebhookSignatureException::class);
        $this->expectExceptionMessage('not valid JSON');

        $handler->handle($rawBody, self::signedHeaders($handler, $rawBody, '1234567890123'));
    }

    public function testASignedJsonValueThatIsNotAnObjectYieldsAnEmptyPayload(): void
    {
        $handler = new WebhookHandler(self::API_KEY);
        $rawBody = '"charged"';

        $payload = $handler->handle($rawBody, self::signedHeaders($handler, $rawBody, '1234567890123'));

        self::assertSame([], $payload->getRawPayload());
        self::assertNull($payload->getStatus());
    }

    public function testASignatureMadeWithAnotherKeyIsInvalid(): void
    {
        $handler = new WebhookHandler(self::API_KEY);
        $signature = (new WebhookHandler('another_key'))->sign('{}', '1234567890123');

        self::assertFalse($handler->isSignatureValid('{}', '1234567890123', $signature));
        self::assertTrue($handler->isSignatureValid('{}', '1234567890123', $handler->sign('{}', '1234567890123')));
    }

    /**
     * @return array<string, string>
     */
    private static function signedHeaders(WebhookHandler $handler, string $rawBody, string $timestamp): array
    {
        return [
            WebhookHandler::HEADER_SIGNATURE => $handler->sign($rawBody, $timestamp),
            WebhookHandler::HEADER_TIMESTAMP => $timestamp,
        ];
    }
}
