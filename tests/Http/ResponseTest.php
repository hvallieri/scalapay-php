<?php declare(strict_types=1);

namespace Hval\Scalapay\Tests\Http;

use Hval\Scalapay\Http\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(Response::class)]
final class ResponseTest extends TestCase
{
    public function testAHeaderIsFoundWhateverTheCaseOfTheNameAskedFor(): void
    {
        $response = new Response(200, ['request-idempotency' => ['true', 'ignored']], null, '');

        self::assertSame('true', $response->getHeader('Request-Idempotency'));
        self::assertSame('true', $response->getHeader('REQUEST-IDEMPOTENCY'));
    }

    public function testAMissingOrEmptyHeaderIsNull(): void
    {
        $response = new Response(200, ['request-idempotency' => []], null, '');

        self::assertNull($response->getHeader('Request-Idempotency'));
        self::assertNull($response->getHeader('X-Unknown'));
    }

    /**
     * Only the literal `true` marks a replay: any other value means the operation ran.
     */
    public function testOnlyTheLiteralTrueMarksAReplay(): void
    {
        self::assertTrue((new Response(200, ['request-idempotency' => ['true']], null, ''))->isIdempotentReplay());
        self::assertFalse((new Response(200, ['request-idempotency' => ['false']], null, ''))->isIdempotentReplay());
        self::assertFalse((new Response(200, [], null, ''))->isIdempotentReplay());
    }

    public function testABodyThatIsNotAnObjectNorAListReadsAsEmpty(): void
    {
        $response = new Response(401, [], 'Unauthorized', '"Unauthorized"');

        self::assertSame([], $response->toArray());
        self::assertSame([], $response->toList());
        self::assertSame('Unauthorized', $response->getDecoded());
    }

    public function testAListKeepsOnlyItsObjectsAndIsReindexed(): void
    {
        $response = new Response(200, [], [1, ['a' => 1], 'noise', ['b' => 2]], '');

        self::assertSame([['a' => 1], ['b' => 2]], $response->toList());
    }
}
