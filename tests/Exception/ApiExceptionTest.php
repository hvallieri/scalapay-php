<?php declare(strict_types=1);

namespace Hval\Scalapay\Tests\Exception;

use Hval\Scalapay\Exception\ApiException;
use Hval\Scalapay\Exception\InvalidRequestException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(ApiException::class)]
final class ApiExceptionTest extends TestCase
{
    /**
     * @param array<string, mixed> $payload
     */
    #[DataProvider('messages')]
    public function testItBuildsTheMostSpecificMessageAvailable(int $httpStatus, array $payload, string $rawBody, string $expected): void
    {
        self::assertSame($expected, ApiException::fromResponse($httpStatus, $payload, $rawBody)->getMessage());
    }

    /**
     * @return iterable<string, array{int, array<string, mixed>, string, string}>
     */
    public static function messages(): iterable
    {
        yield 'a string message, as on 409' => [
            409,
            ['errorCode' => 'conflicting_operation_in_progress', 'message' => 'Try again later.'],
            '',
            'Try again later.',
        ];

        yield 'an empty string message falls back to the error code' => [
            409,
            ['errorCode' => 'conflicting_operation_in_progress', 'message' => ''],
            '',
            'Scalapay API error conflicting_operation_in_progress (HTTP 409).',
        ];

        yield 'a validation object is flattened field by field' => [
            400,
            ['message' => ['errors' => [
                ['field' => ['totalAmount', 'amount'], 'messages' => ['"amount" is required', '"amount" must be a string']],
                ['field' => ['totalAmount', 'currency'], 'messages' => ['"currency" is required']],
            ]]],
            '',
            'totalAmount.amount: "amount" is required; "amount" must be a string | totalAmount.currency: "currency" is required',
        ];

        yield 'odd validation entries are tolerated' => [
            400,
            ['message' => ['errors' => [
                'noise',
                ['field' => 'merchant', 'messages' => ['"merchant" is required']],
                ['field' => [], 'messages' => ['the body is invalid']],
                ['field' => null, 'messages' => null],
            ]]],
            '',
            'merchant: "merchant" is required | the body is invalid',
        ];

        yield 'non-scalar path segments and messages are dropped instead of warning' => [
            400,
            ['message' => ['errors' => [
                ['field' => ['items', ['nested'], 'price'], 'messages' => ['"price" is required', ['nested']]],
            ]]],
            '',
            'items.price: "price" is required',
        ];

        yield 'a validation object without errors falls back to the error code' => [
            400,
            ['errorCode' => 'api_validationerror', 'message' => ['status' => 400, 'statusText' => 'Bad Request']],
            '',
            'Scalapay API error api_validationerror (HTTP 400).',
        ];

        yield 'a numeric error code is quoted as it is' => [
            400,
            ['errorCode' => 400],
            '',
            'Scalapay API error 400 (HTTP 400).',
        ];

        yield 'without payload the trimmed raw body is quoted' => [
            500,
            [],
            "  Service Unavailable \n",
            'Scalapay API error (HTTP 500): Service Unavailable',
        ];

        yield 'without anything only the status remains' => [
            503,
            [],
            '',
            'Scalapay API error (HTTP 503).',
        ];
    }

    public function testTheErrorCodeIsAlwaysAString(): void
    {
        self::assertSame('400', ApiException::fromResponse(400, ['errorCode' => 400], '')->getErrorCode());
    }

    public function testTheIdentifiersAreNullWhenAbsentOrNotScalar(): void
    {
        $absent = ApiException::fromResponse(500, [], '');
        $malformed = ApiException::fromResponse(500, ['errorCode' => ['nested'], 'errorId' => ['nested']], '');

        self::assertNull($absent->getErrorCode());
        self::assertNull($absent->getErrorId());
        self::assertNull($malformed->getErrorCode());
        self::assertNull($malformed->getErrorId());
    }

    public function testOnlyAConflictIsRetryable(): void
    {
        self::assertTrue(ApiException::fromResponse(409, [], '')->isRetryable());
        self::assertFalse(ApiException::fromResponse(400, [], '')->isRetryable());
        self::assertFalse(ApiException::fromResponse(500, [], '')->isRetryable());
    }

    public function testTheHttpStatusIsAlsoTheExceptionCode(): void
    {
        $exception = ApiException::fromResponse(422, [], '');

        self::assertSame(422, $exception->getHttpStatus());
        self::assertSame(422, $exception->getCode());
    }

    public function testTheOriginalsStayAvailable(): void
    {
        $payload = ['errorCode' => 'api_validationerror', 'errorId' => 'error-1', 'message' => ['errors' => []]];
        $rawBody = '{"errorCode":"api_validationerror","errorId":"error-1","message":{"errors":[]}}';

        $exception = ApiException::fromResponse(400, $payload, $rawBody);

        self::assertSame('error-1', $exception->getErrorId());
        self::assertSame($payload, $exception->getPayload());
        self::assertSame($rawBody, $exception->getRawBody());
        self::assertSame(['errors' => []], $exception->getApiMessage());
    }

    public function testTheFactoryBuildsTheSubclassItIsCalledOn(): void
    {
        self::assertInstanceOf(InvalidRequestException::class, InvalidRequestException::fromResponse(400, [], ''));
    }
}
