<?php declare(strict_types=1);

namespace Hval\Scalapay\Tests\Model;

use Hval\Scalapay\Model\Amount;
use Hval\Scalapay\Model\Discount;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(Discount::class)]
#[UsesClass(Amount::class)]
final class DiscountTest extends TestCase
{
    public function testItExposesAndSerialisesItsTwoFields(): void
    {
        $amount = new Amount('2.00');
        $discount = new Discount('SUMMER', $amount);

        self::assertSame('SUMMER', $discount->getDisplayName());
        self::assertSame($amount, $discount->getAmount());
        self::assertSame(['displayName' => 'SUMMER', 'amount' => ['amount' => '2.00', 'currency' => 'EUR']], $discount->toArray());
    }

    public function testFromArraySurvivesTheRoundTrip(): void
    {
        $payload = ['displayName' => 'SUMMER', 'amount' => ['amount' => '2.00', 'currency' => 'EUR']];

        self::assertSame($payload, Discount::fromArray($payload)->toArray());
    }

    public function testFromArrayFallsBackToDefaultsOnAnEmptyPayload(): void
    {
        self::assertSame(['displayName' => '', 'amount' => ['amount' => '0.00', 'currency' => 'EUR']], Discount::fromArray([])->toArray());
    }
}
