<?php declare(strict_types=1);

namespace Hval\Scalapay\Model;

use Hval\Scalapay\Exception\ScalapayException;

/**
 * A monetary amount. `amount` is a **string** in the specification and is kept as such:
 * converting it to a float would introduce rounding errors and drop trailing zeros.
 *
 * No positivity validation: refund fees in the Reporting API are negative (`-1.22`), so
 * a "greater than zero" constraint would reject legitimate data.
 */
final class Amount implements ModelInterface
{
    public const CURRENCY_EUR = 'EUR';

    public function __construct(
        private readonly string $amount,
        private readonly string $currency = self::CURRENCY_EUR,
    ) {
        if (trim($this->amount) === '') {
            throw new ScalapayException('The amount cannot be empty.');
        }

        if (trim($this->currency) === '') {
            throw new ScalapayException('The currency cannot be empty.');
        }
    }

    public static function fromArray(array $data): self
    {
        $amount = $data['amount'] ?? null;
        $currency = $data['currency'] ?? self::CURRENCY_EUR;

        return new self(
            is_scalar($amount) ? self::stringify($amount) : '',
            is_scalar($currency) ? (string) $currency : self::CURRENCY_EUR
        );
    }

    /**
     * @param array<array-key, mixed>|null $data
     *
     * @return self|null
     */
    public static function fromArrayOrNull(?array $data): ?self
    {
        if ($data === null || isset($data['amount']) === false) {
            return null;
        }

        return self::fromArray($data);
    }

    /**
     * Builds the amount from a value in minor units, avoiding any float arithmetic.
     */
    public static function fromMinorUnits(int $minorUnits, string $currency = self::CURRENCY_EUR): self
    {
        $sign = $minorUnits < 0 ? '-' : '';
        $absolute = (string) abs($minorUnits);
        $padded = str_pad($absolute, 3, '0', STR_PAD_LEFT);

        return new self($sign . substr($padded, 0, -2) . '.' . substr($padded, -2), $currency);
    }

    public function getAmount(): string
    {
        return $this->amount;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function isNegative(): bool
    {
        return str_starts_with(trim($this->amount), '-');
    }

    /**
     * Compares two amounts digit by digit, so that neither floats nor bcmath are involved
     * and different scales compare equal (`5` and `5.00`).
     *
     * @param Amount $other
     *
     * @throws ScalapayException when the currencies differ or an amount is not a decimal number
     *
     * @return int -1, 0 or 1
     */
    public function compareTo(self $other): int
    {
        if ($this->currency !== $other->currency) {
            throw new ScalapayException(sprintf('Cannot compare an amount in %s with one in %s.', $this->currency, $other->currency));
        }

        [$sign, $integer, $fraction] = self::decompose($this->amount);
        [$otherSign, $otherInteger, $otherFraction] = self::decompose($other->amount);

        if ($sign !== $otherSign) {
            return $sign <=> $otherSign;
        }

        $scale = max(strlen($fraction), strlen($otherFraction));

        $magnitude = (strlen($integer) <=> strlen($otherInteger))
            ?: (strcmp($integer, $otherInteger) <=> 0)
            ?: (strcmp(str_pad($fraction, $scale, '0'), str_pad($otherFraction, $scale, '0')) <=> 0);

        return $sign * $magnitude;
    }

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return [
            'amount' => $this->amount,
            'currency' => $this->currency,
        ];
    }

    /**
     * Some official response examples report the amount as a JSON number rather than a
     * string (the webhook `totalAmount` and the instore refund). This conversion avoids
     * float arithmetic whenever the value is already an integer. A float is rounded to
     * two decimals and loses its trailing zeros: `4999.999` becomes `"5000"`.
     */
    private static function stringify(bool|float|int|string $value): string
    {
        if (is_string($value)) {
            return $value;
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_int($value)) {
            return (string) $value;
        }

        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.') ?: '0';
    }

    /**
     * @param string $amount
     *
     * @return array{int, string, string} the sign, the integer digits without leading
     *                                    zeros and the fraction digits without trailing zeros
     */
    private static function decompose(string $amount): array
    {
        if (
            preg_match('/^\s*([+-]?)(\d*)(?:\.(\d*))?\s*$/', $amount, $matches) !== 1 || $matches[2] . ($matches[3] ?? '') === ''
        ) {
            throw new ScalapayException(sprintf('"%s" is not a decimal amount.', $amount));
        }

        $integer = ltrim($matches[2], '0');
        $fraction = rtrim($matches[3] ?? '', '0');
        $sign = $integer === '' && $fraction === '' ? 0 : ($matches[1] === '-' ? -1 : 1);

        return [$sign, $integer, $fraction];
    }
}
