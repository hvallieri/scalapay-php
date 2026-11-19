<?php declare(strict_types=1);

namespace Hval\Scalapay\Model;

final class Discount implements ModelInterface
{
    public function __construct(
        private readonly string $displayName,
        private readonly Amount $amount,
    ) {
    }

    public static function fromArray(array $data): self
    {
        $amount = $data['amount'] ?? null;
        $displayName = $data['displayName'] ?? null;

        return new self(
            is_scalar($displayName) ? (string) $displayName : '',
            is_array($amount) ? Amount::fromArray($amount) : new Amount('0.00')
        );
    }

    public function getDisplayName(): string
    {
        return $this->displayName;
    }

    public function getAmount(): Amount
    {
        return $this->amount;
    }

    public function toArray(): array
    {
        return [
            'displayName' => $this->displayName,
            'amount' => $this->amount->toArray(),
        ];
    }
}
