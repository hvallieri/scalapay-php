<?php declare(strict_types=1);

namespace Hval\Scalapay\Tests\Exception;

use Hval\Scalapay\Exception\ApiException;
use Hval\Scalapay\Exception\InvalidRequestException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(InvalidRequestException::class)]
#[UsesClass(ApiException::class)]
final class InvalidRequestExceptionTest extends TestCase
{
    /**
     * Returned by the sandbox on 2026-11-18 for an order of type `offline-link`.
     */
    public function testItReadsTheRejectedFieldOfARealSandboxError(): void
    {
        $rawBody = '{"errorCode":"api_validationerror","errorId":"error-19g61mwm2x5jz","message":{"status":400,"statusText":"Bad Request","errors":[{"field":["type"],"location":"body","messages":["\"type\" must be one of [online, offline, card, link, card-online, card-offline, charge-card]"],"types":["any.allowOnly"]}]},"httpStatusCode":400}';

        $exception = self::exceptionFrom($rawBody);

        self::assertSame(['type'], $exception->getInvalidFields());
        self::assertSame('error-19g61mwm2x5jz', $exception->getErrorId());
        self::assertSame('type: "type" must be one of [online, offline, card, link, card-online, card-offline, charge-card]', $exception->getMessage());
        self::assertSame(['any.allowOnly'], $exception->getValidationErrors()[0]['types']);
    }

    public function testNumericPathSegmentsAreJoinedLikeTheOthers(): void
    {
        $exception = self::exceptionFrom('{"message":{"errors":[{"field":["items",0,"price","amount"],"messages":["required"]}]}}');

        self::assertSame(['items.0.price.amount'], $exception->getInvalidFields());
    }

    public function testNonScalarPathSegmentsAreDroppedInsteadOfWarning(): void
    {
        $exception = self::exceptionFrom('{"message":{"errors":[{"field":["items",{"x":1},"price"]}]}}');

        self::assertSame(['items.price'], $exception->getInvalidFields());
    }

    public function testAScalarFieldIsKeptAndAMissingOneIsSkipped(): void
    {
        $exception = self::exceptionFrom('{"message":{"errors":[{"field":"consumer.email"},{"messages":["no field at all"]},{"field":null}]}}');

        self::assertSame(['consumer.email'], $exception->getInvalidFields());
    }

    #[DataProvider('messagesWithoutErrors')]
    public function testAMessageWithoutAnErrorListYieldsNoValidationErrors(string $rawBody): void
    {
        $exception = self::exceptionFrom($rawBody);

        self::assertSame([], $exception->getValidationErrors());
        self::assertSame([], $exception->getInvalidFields());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function messagesWithoutErrors(): iterable
    {
        yield 'a string message' => ['{"errorCode":"400","message":"Bad request"}'];

        yield 'an object without errors' => ['{"message":{"status":400}}'];

        yield 'errors that are not a list' => ['{"message":{"errors":"oops"}}'];

        yield 'no message at all' => ['{"errorCode":"400"}'];
    }

    public function testEntriesThatAreNotObjectsAreSkippedAndTheListReindexed(): void
    {
        $exception = self::exceptionFrom('{"message":{"errors":["noise",{"field":["a"]},3,{"field":["b"]}]}}');

        self::assertSame([['field' => ['a']], ['field' => ['b']]], $exception->getValidationErrors());
    }

    private static function exceptionFrom(string $rawBody): InvalidRequestException
    {
        $payload = json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($payload);

        $exception = InvalidRequestException::fromResponse(400, $payload, $rawBody);
        self::assertInstanceOf(InvalidRequestException::class, $exception);

        return $exception;
    }
}
