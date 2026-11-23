<?php declare(strict_types=1);

namespace Hval\Scalapay\Tests\Model;

use Hval\Scalapay\Exception\ScalapayException;
use Hval\Scalapay\Model\Amount;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(Amount::class)]
final class AmountTest extends TestCase
{
    public function testItKeepsTheAmountAsAStringWithoutLosingTrailingZeros(): void
    {
        $amount = new Amount('40.70');

        self::assertSame('40.70', $amount->getAmount());
        self::assertSame(['amount' => '40.70', 'currency' => 'EUR'], $amount->toArray());
    }

    public function testItAcceptsNegativeAmountsBecauseRefundFeesAreNegative(): void
    {
        $amount = new Amount('-1.22');

        self::assertSame('-1.22', $amount->getAmount());
        self::assertTrue($amount->isNegative());
    }

    public function testItRejectsAnEmptyAmount(): void
    {
        $this->expectException(ScalapayException::class);

        new Amount('');
    }

    public function testItReadsAnAmountSentAsAJsonNumber(): void
    {
        $amount = Amount::fromArray(['amount' => 10, 'currency' => 'EUR']);

        self::assertSame('10', $amount->getAmount());
    }

    public function testItBuildsFromMinorUnitsWithoutFloatArithmetic(): void
    {
        self::assertSame('187.95', Amount::fromMinorUnits(18795)->getAmount());
        self::assertSame('0.05', Amount::fromMinorUnits(5)->getAmount());
        self::assertSame('-1.22', Amount::fromMinorUnits(-122)->getAmount());
    }

    public function testFromArrayOrNullReturnsNullWhenTheAmountIsAbsent(): void
    {
        self::assertNull(Amount::fromArrayOrNull(null));
        self::assertNull(Amount::fromArrayOrNull(['currency' => 'EUR']));
    }

    public function testItRejectsAnEmptyCurrency(): void
    {
        $this->expectException(ScalapayException::class);
        $this->expectExceptionMessage('currency');

        new Amount('10.00', ' ');
    }

    public function testFromArrayOrNullBuildsTheAmountWhenOneIsPresent(): void
    {
        $amount = Amount::fromArrayOrNull(['amount' => '190.00', 'currency' => 'EUR']);

        self::assertSame(['amount' => '190.00', 'currency' => 'EUR'], $amount?->toArray());
    }

    /**
     * Numeric amounts arrive in a few responses; a float is rounded to two decimals and
     * loses its trailing zeros, as documented on the conversion.
     */
    #[DataProvider('numericAmounts')]
    public function testANumericAmountIsTurnedIntoItsDecimalString(float|int $value, string $expected): void
    {
        self::assertSame($expected, Amount::fromArray(['amount' => $value, 'currency' => 'EUR'])->getAmount());
    }

    /**
     * @return iterable<string, array{float|int, string}>
     */
    public static function numericAmounts(): iterable
    {
        yield 'an integer stays exact' => [4999, '4999'];

        yield 'a float drops its trailing zero' => [40.70, '40.7'];

        yield 'a whole float drops its decimals' => [10.0, '10'];

        yield 'zero' => [0.0, '0'];

        yield 'a negative refund fee' => [-1.22, '-1.22'];

        yield 'beyond two decimals it is rounded' => [4999.999, '5000'];
    }

    public function testFromArrayRejectsAnAmountThatIsNotScalarAndDefaultsAMalformedCurrency(): void
    {
        self::assertSame('EUR', Amount::fromArray(['amount' => '5', 'currency' => ['EUR']])->getCurrency());

        $this->expectException(ScalapayException::class);

        Amount::fromArray(['amount' => ['5'], 'currency' => 'EUR']);
    }

    #[DataProvider('comparisons')]
    public function testItComparesAmountsDigitByDigit(string $left, string $right, int $expected): void
    {
        self::assertSame($expected, (new Amount($left))->compareTo(new Amount($right)));
    }

    /**
     * @return iterable<string, array{string, string, int}>
     */
    public static function comparisons(): iterable
    {
        yield 'equal at different scales' => ['5', '5.00', 0];

        yield 'trailing zeros in the fraction' => ['0.10', '0.1', 0];

        yield 'signed zero' => ['-0.00', '0', 0];

        yield 'leading zeros' => ['007.5', '7.50', 0];

        yield 'one cent below' => ['4999.99', '5000', -1];

        yield 'one cent above' => ['5000.01', '5000', 1];

        yield 'longer integer part' => ['10', '9.99', 1];

        yield 'negative below zero' => ['-1.22', '0', -1];

        yield 'two negatives' => ['-2', '-1.5', -1];

        yield 'beyond float precision' => ['12345678901234567890.01', '12345678901234567890.02', -1];
    }

    public function testItRefusesToCompareDifferentCurrencies(): void
    {
        $this->expectException(ScalapayException::class);

        (new Amount('5', 'EUR'))->compareTo(new Amount('5', 'USD'));
    }

    #[DataProvider('malformedAmounts')]
    public function testItRejectsAnAmountThatIsNotADecimalNumber(string $amount): void
    {
        $this->expectException(ScalapayException::class);
        $this->expectExceptionMessage('is not a decimal amount');

        new Amount($amount);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function malformedAmounts(): iterable
    {
        yield 'letters' => ['abc'];

        yield 'scientific notation' => ['1e3'];

        yield 'decimal comma' => ['10,50'];

        yield 'two decimal points' => ['1.2.3'];

        yield 'a sign alone' => ['-'];

        yield 'a point alone' => ['.'];

        yield 'a currency symbol' => ['€10.00'];
    }

    public function testFromArrayRejectsAMalformedAmountFromAResponse(): void
    {
        $this->expectException(ScalapayException::class);

        Amount::fromArray(['amount' => 'n/a', 'currency' => 'EUR']);
    }
}
