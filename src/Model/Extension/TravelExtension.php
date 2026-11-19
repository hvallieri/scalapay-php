<?php declare(strict_types=1);

namespace Hval\Scalapay\Model\Extension;

use Hval\Scalapay\Exception\ScalapayException;
use Hval\Scalapay\Model\ModelInterface;

/**
 * Travel industry extension, mandatory for travel merchants.
 *
 * Dates use the plain `YYYY-MM-DD` form, unlike every other temporal field of the API,
 * which carries a time. The travel start date must be later than the order creation
 * date, and `startDate` must not be later than `endDate`: violating the latter yields a
 * 400 carrying the message "startDate cannot be greater than endDate".
 */
final class TravelExtension implements ModelInterface
{
    public const DATE_FORMAT = 'Y-m-d';

    public function __construct(
        private readonly string $startDate,
        private readonly string $endDate,
    ) {
        self::assertDate($this->startDate, 'startDate');
        self::assertDate($this->endDate, 'endDate');

        if ($this->startDate > $this->endDate) {
            throw new ScalapayException('The travel startDate cannot be greater than the endDate.');
        }
    }

    public static function fromDates(\DateTimeInterface $startDate, \DateTimeInterface $endDate): self
    {
        return new self($startDate->format(self::DATE_FORMAT), $endDate->format(self::DATE_FORMAT));
    }

    public static function fromArray(array $data): self
    {
        $startDate = $data['startDate'] ?? null;
        $endDate = $data['endDate'] ?? null;

        return new self(
            is_scalar($startDate) ? (string) $startDate : '',
            is_scalar($endDate) ? (string) $endDate : ''
        );
    }

    public function getStartDate(): string
    {
        return $this->startDate;
    }

    public function getEndDate(): string
    {
        return $this->endDate;
    }

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return [
            'startDate' => $this->startDate,
            'endDate' => $this->endDate,
        ];
    }

    private static function assertDate(string $value, string $field): void
    {
        $parsed = \DateTimeImmutable::createFromFormat('!' . self::DATE_FORMAT, $value);

        if ($parsed === false || $parsed->format(self::DATE_FORMAT) !== $value) {
            throw new ScalapayException(sprintf('The travel %s must be a date in YYYY-MM-DD format, "%s" given.', $field, $value));
        }
    }
}
