<?php declare(strict_types=1);

namespace Hval\Scalapay\Model;

/**
 * Instalment cadence, for "pay later" products.
 *
 * Must not be sent with `pay-in-3`, `pay-in-4` or `pay-now-standalone`: one official
 * example shows it next to `pay-in-3`, but that contradicts the schema and should not be
 * imitated.
 *
 * `number` is an integer: the schema declares it so, and two examples out of three agree.
 */
final class Frequency implements ModelInterface
{
    public const TYPE_MONTHLY = 'monthly';

    /**
     * Absent from the specification: `later` is configured as 14 daily in the sandbox, and
     * an order carrying that frequency was accepted on 2026-11-18.
     */
    public const TYPE_DAILY = 'daily';

    public function __construct(
        private readonly int $number,
        private readonly string $frequencyType = self::TYPE_MONTHLY,
    ) {
    }

    public static function fromArray(array $data): self
    {
        $type = $data['frequencyType'] ?? self::TYPE_MONTHLY;

        return new self(
            is_numeric($data['number'] ?? null) ? (int) $data['number'] : 1,
            is_scalar($type) ? (string) $type : self::TYPE_MONTHLY
        );
    }

    public function getNumber(): int
    {
        return $this->number;
    }

    public function getFrequencyType(): string
    {
        return $this->frequencyType;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'number' => $this->number,
            'frequencyType' => $this->frequencyType,
        ];
    }
}
