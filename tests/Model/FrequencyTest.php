<?php declare(strict_types=1);

namespace Hval\Scalapay\Tests\Model;

use Hval\Scalapay\Model\Frequency;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(Frequency::class)]
final class FrequencyTest extends TestCase
{
    public function testTheCadenceDefaultsToMonthly(): void
    {
        $frequency = new Frequency(1);

        self::assertSame(1, $frequency->getNumber());
        self::assertSame(Frequency::TYPE_MONTHLY, $frequency->getFrequencyType());
        self::assertSame(['number' => 1, 'frequencyType' => 'monthly'], $frequency->toArray());
    }

    public function testADailyCadenceAsConfiguredForLater(): void
    {
        self::assertSame(['number' => 14, 'frequencyType' => 'daily'], (new Frequency(14, Frequency::TYPE_DAILY))->toArray());
    }

    /**
     * One official example sends `"number": "1"`, contradicting the integer of the schema.
     */
    public function testFromArrayReadsANumberSentAsAString(): void
    {
        self::assertSame(1, Frequency::fromArray(['number' => '1', 'frequencyType' => 'monthly'])->getNumber());
    }

    /**
     * The merchant configuration reports `0 daily` for the pay-now products.
     */
    public function testFromArrayKeepsAZeroNumber(): void
    {
        $frequency = Frequency::fromArray(['number' => 0, 'frequencyType' => 'daily']);

        self::assertSame(0, $frequency->getNumber());
        self::assertSame(Frequency::TYPE_DAILY, $frequency->getFrequencyType());
    }

    public function testFromArrayFallsBackToOneMonthlyOnMissingOrMalformedData(): void
    {
        self::assertSame(['number' => 1, 'frequencyType' => 'monthly'], Frequency::fromArray([])->toArray());
        self::assertSame(['number' => 1, 'frequencyType' => 'monthly'], Frequency::fromArray(['number' => 'often', 'frequencyType' => ['daily']])->toArray());
    }
}
