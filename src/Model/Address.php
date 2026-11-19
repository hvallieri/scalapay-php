<?php declare(strict_types=1);

namespace Hval\Scalapay\Model;

/**
 * An address, used by `billing` and `shipping`.
 *
 * The schema marks all six fields required on `shipping` and none on `billing`, yet the
 * official pay-by-link and travel examples send `"shipping": {}`. This library therefore
 * does not validate more strictly than the API: use empty() for that case.
 */
final class Address implements ModelInterface
{
    public function __construct(
        private readonly ?string $name = null,
        private readonly ?string $line1 = null,
        private readonly ?string $suburb = null,
        private readonly ?string $postcode = null,
        private readonly ?string $countryCode = null,
        private readonly ?string $phoneNumber = null,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            self::stringOrNull($data['name'] ?? null),
            self::stringOrNull($data['line1'] ?? null),
            self::stringOrNull($data['suburb'] ?? null),
            self::stringOrNull($data['postcode'] ?? null),
            self::stringOrNull($data['countryCode'] ?? null),
            self::stringOrNull($data['phoneNumber'] ?? null)
        );
    }

    /**
     * An empty address: `shipping` stays required in the schema, but shipping is
     * meaningless for travel merchants and the official examples pass `{}`.
     */
    public static function empty(): self
    {
        return new self();
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function getLine1(): ?string
    {
        return $this->line1;
    }

    public function getSuburb(): ?string
    {
        return $this->suburb;
    }

    public function getPostcode(): ?string
    {
        return $this->postcode;
    }

    public function getCountryCode(): ?string
    {
        return $this->countryCode;
    }

    public function getPhoneNumber(): ?string
    {
        return $this->phoneNumber;
    }

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return array_filter([
            'name' => $this->name,
            'line1' => $this->line1,
            'suburb' => $this->suburb,
            'postcode' => $this->postcode,
            'countryCode' => $this->countryCode,
            'phoneNumber' => $this->phoneNumber,
        ], static fn (?string $value): bool => $value !== null);
    }

    private static function stringOrNull(mixed $value): ?string
    {
        return is_scalar($value) ? (string) $value : null;
    }
}
