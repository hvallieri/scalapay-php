<?php declare(strict_types=1);

namespace Hval\Scalapay\Tests\Model\Extension;

use Hval\Scalapay\Exception\ScalapayException;
use Hval\Scalapay\Model\Extension\TravelExtension;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(TravelExtension::class)]
final class TravelExtensionTest extends TestCase
{
    public function testItKeepsBothDatesInThePlainDateForm(): void
    {
        $travel = new TravelExtension('2026-10-01', '2026-10-08');

        self::assertSame('2026-10-01', $travel->getStartDate());
        self::assertSame('2026-10-08', $travel->getEndDate());
        self::assertSame(['startDate' => '2026-10-01', 'endDate' => '2026-10-08'], $travel->toArray());
    }

    public function testItAcceptsATravelThatStartsAndEndsOnTheSameDay(): void
    {
        $travel = new TravelExtension('2026-10-01', '2026-10-01');

        self::assertSame('2026-10-01', $travel->getEndDate());
    }

    public function testItRejectsAStartDateLaterThanTheEndDate(): void
    {
        $this->expectException(ScalapayException::class);
        $this->expectExceptionMessage('cannot be greater than the endDate');

        new TravelExtension('2026-10-09', '2026-10-08');
    }

    #[DataProvider('malformedDates')]
    public function testItRejectsAnythingButYearMonthDay(string $date): void
    {
        $this->expectException(ScalapayException::class);
        $this->expectExceptionMessage('YYYY-MM-DD');

        new TravelExtension($date, '2026-12-31');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function malformedDates(): iterable
    {
        yield 'empty' => [''];

        yield 'day first' => ['01/10/2026'];

        yield 'unpadded' => ['2026-1-1'];

        yield 'with a time' => ['2026-10-01T00:00:00Z'];

        yield 'nonexistent day' => ['2026-02-30'];

        yield 'not a date at all' => ['tomorrow'];
    }

    public function testItRejectsAMalformedEndDateToo(): void
    {
        $this->expectException(ScalapayException::class);
        $this->expectExceptionMessage('endDate');

        new TravelExtension('2026-10-01', 'whenever');
    }

    public function testFromDatesDropsTheTimePartOfTheGivenObjects(): void
    {
        $travel = TravelExtension::fromDates(
            new \DateTimeImmutable('2026-10-01 18:45:00'),
            new \DateTime('2026-10-08 09:00:00')
        );

        self::assertSame(['startDate' => '2026-10-01', 'endDate' => '2026-10-08'], $travel->toArray());
    }

    public function testFromArrayReadsTheTwoDocumentedFields(): void
    {
        $travel = TravelExtension::fromArray(['startDate' => '2026-10-01', 'endDate' => '2026-10-08']);

        self::assertSame('2026-10-01', $travel->getStartDate());
        self::assertSame('2026-10-08', $travel->getEndDate());
    }

    public function testFromArrayRejectsAPayloadWithoutDates(): void
    {
        $this->expectException(ScalapayException::class);

        TravelExtension::fromArray([]);
    }

    public function testFromArrayRejectsDatesThatAreNotScalar(): void
    {
        $this->expectException(ScalapayException::class);

        TravelExtension::fromArray(['startDate' => ['2026-10-01'], 'endDate' => '2026-10-08']);
    }
}
