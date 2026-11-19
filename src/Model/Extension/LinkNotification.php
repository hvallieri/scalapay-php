<?php declare(strict_types=1);

namespace Hval\Scalapay\Model\Extension;

use Hval\Scalapay\Exception\ScalapayException;
use Hval\Scalapay\Model\ModelInterface;

/**
 * Notification details for pay-by-link orders (`type` = `link`).
 *
 * `channels` is the only required property. `phoneCountryCode` and `phoneNumber` are
 * required when `sms` is selected, `emailAddress` when `email` is. Both channels may be
 * used together.
 *
 * Note: unlike Consumer, the official example keeps the dialling prefix apart, in
 * `phoneCountryCode`, with `phoneNumber` holding the local digits only.
 *
 * The online link is valid for 24 hours; the instore one for 10 minutes.
 */
final class LinkNotification implements ModelInterface
{
    public const CHANNEL_EMAIL = 'email';
    public const CHANNEL_SMS = 'sms';

    public const LANGUAGE_ENGLISH = 'english';
    public const LANGUAGE_ITALIAN = 'italiano';
    public const LANGUAGE_FRENCH = 'francais';
    public const LANGUAGE_GERMAN = 'deutsch';
    public const LANGUAGE_SPANISH = 'espanol';
    public const LANGUAGE_DUTCH = 'dutch';
    public const LANGUAGE_FINNISH = 'suomi';
    public const LANGUAGE_PORTUGUESE = 'portuguese';

    /**
     * @param list<string> $channels
     * @param string|null $phoneCountryCode
     * @param string|null $phoneNumber
     * @param string|null $emailAddress
     * @param string|null $preferredLanguage
     */
    public function __construct(
        private readonly array $channels,
        private readonly ?string $phoneCountryCode = null,
        private readonly ?string $phoneNumber = null,
        private readonly ?string $emailAddress = null,
        private readonly ?string $preferredLanguage = null,
    ) {
        if ($this->channels === []) {
            throw new ScalapayException('At least one notification channel is required.');
        }

        if (in_array(self::CHANNEL_SMS, $this->channels, true) === true && ($this->phoneNumber === null || $this->phoneCountryCode === null)) {
            throw new ScalapayException('The sms channel requires both phoneCountryCode and phoneNumber.');
        }

        if (in_array(self::CHANNEL_EMAIL, $this->channels, true) === true && $this->emailAddress === null) {
            throw new ScalapayException('The email channel requires an emailAddress.');
        }
    }

    public static function bySms(string $phoneCountryCode, string $phoneNumber, ?string $preferredLanguage = null): self
    {
        return new self([self::CHANNEL_SMS], $phoneCountryCode, $phoneNumber, null, $preferredLanguage);
    }

    public static function byEmail(string $emailAddress, ?string $preferredLanguage = null): self
    {
        return new self([self::CHANNEL_EMAIL], null, null, $emailAddress, $preferredLanguage);
    }

    public static function fromArray(array $data): self
    {
        $channels = $data['channels'] ?? [];

        return new self(
            is_array($channels) ? array_values(array_map(static fn (int|float|string|bool $value): string => (string) $value, array_filter($channels, is_scalar(...)))) : [],
            self::stringOrNull($data['phoneCountryCode'] ?? null),
            self::stringOrNull($data['phoneNumber'] ?? null),
            self::stringOrNull($data['emailAddress'] ?? null),
            self::stringOrNull($data['preferredLanguage'] ?? null)
        );
    }

    /**
     * @return list<string>
     */
    public function getChannels(): array
    {
        return $this->channels;
    }

    public function getPhoneCountryCode(): ?string
    {
        return $this->phoneCountryCode;
    }

    public function getPhoneNumber(): ?string
    {
        return $this->phoneNumber;
    }

    public function getEmailAddress(): ?string
    {
        return $this->emailAddress;
    }

    public function getPreferredLanguage(): ?string
    {
        return $this->preferredLanguage;
    }

    public function toArray(): array
    {
        $payload = ['channels' => $this->channels];

        foreach ([
            'phoneCountryCode' => $this->phoneCountryCode,
            'phoneNumber' => $this->phoneNumber,
            'emailAddress' => $this->emailAddress,
            'preferredLanguage' => $this->preferredLanguage,
        ] as $key => $value) {
            if ($value !== null) {
                $payload[$key] = $value;
            }
        }

        return $payload;
    }

    private static function stringOrNull(mixed $value): ?string
    {
        return is_scalar($value) ? (string) $value : null;
    }
}
