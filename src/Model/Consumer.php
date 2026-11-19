<?php declare(strict_types=1);

namespace Hval\Scalapay\Model;

/**
 * Customer details. The schema marks all four fields required.
 *
 * Mind the dialling prefix: official examples include it in the number here
 * (`+393001234567`), whereas pay-by-link notifications keep it apart in
 * `phoneCountryCode`.
 */
final class Consumer implements ModelInterface
{
    public function __construct(
        private readonly string $givenNames,
        private readonly string $surname,
        private readonly string $email,
        private readonly string $phoneNumber,
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            self::stringValue($data['givenNames'] ?? null),
            self::stringValue($data['surname'] ?? null),
            self::stringValue($data['email'] ?? null),
            self::stringValue($data['phoneNumber'] ?? null)
        );
    }

    public function getGivenNames(): string
    {
        return $this->givenNames;
    }

    public function getSurname(): string
    {
        return $this->surname;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getPhoneNumber(): string
    {
        return $this->phoneNumber;
    }

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return [
            'givenNames' => $this->givenNames,
            'surname' => $this->surname,
            'email' => $this->email,
            'phoneNumber' => $this->phoneNumber,
        ];
    }

    private static function stringValue(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }
}
