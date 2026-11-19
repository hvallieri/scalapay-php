<?php declare(strict_types=1);

namespace Hval\Scalapay\Tests\Model\Extension;

use Hval\Scalapay\Exception\ScalapayException;
use Hval\Scalapay\Model\Extension\LinkNotification;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(LinkNotification::class)]
final class LinkNotificationTest extends TestCase
{
    public function testItRequiresAtLeastOneChannel(): void
    {
        $this->expectException(ScalapayException::class);
        $this->expectExceptionMessage('At least one notification channel');

        new LinkNotification([]);
    }

    public function testTheSmsChannelRequiresBothThePrefixAndTheNumber(): void
    {
        $this->expectException(ScalapayException::class);
        $this->expectExceptionMessage('phoneCountryCode and phoneNumber');

        new LinkNotification([LinkNotification::CHANNEL_SMS], '+39');
    }

    public function testTheSmsChannelRejectsANumberWithoutItsPrefix(): void
    {
        $this->expectException(ScalapayException::class);

        new LinkNotification([LinkNotification::CHANNEL_SMS], null, '3001234567');
    }

    public function testTheEmailChannelRequiresAnAddress(): void
    {
        $this->expectException(ScalapayException::class);
        $this->expectExceptionMessage('emailAddress');

        new LinkNotification([LinkNotification::CHANNEL_EMAIL]);
    }

    public function testAnAddressAloneDoesNotSatisfyTheSmsChannel(): void
    {
        $this->expectException(ScalapayException::class);

        new LinkNotification(
            [LinkNotification::CHANNEL_EMAIL, LinkNotification::CHANNEL_SMS],
            null,
            null,
            'mario@example.com'
        );
    }

    public function testBothChannelsMayBeUsedTogetherWhenEveryContactIsGiven(): void
    {
        $notification = new LinkNotification(
            [LinkNotification::CHANNEL_EMAIL, LinkNotification::CHANNEL_SMS],
            '+39',
            '3001234567',
            'mario@example.com',
            LinkNotification::LANGUAGE_ITALIAN
        );

        self::assertSame([
            'channels' => ['email', 'sms'],
            'phoneCountryCode' => '+39',
            'phoneNumber' => '3001234567',
            'emailAddress' => 'mario@example.com',
            'preferredLanguage' => 'italiano',
        ], $notification->toArray());
    }

    public function testAnUnknownChannelCarriesNoContactRequirement(): void
    {
        $notification = new LinkNotification(['whatsapp']);

        self::assertSame(['channels' => ['whatsapp']], $notification->toArray());
    }

    public function testBySmsKeepsThePrefixApartFromTheLocalNumber(): void
    {
        $notification = LinkNotification::bySms('+39', '3001234567');

        self::assertSame([LinkNotification::CHANNEL_SMS], $notification->getChannels());
        self::assertSame('+39', $notification->getPhoneCountryCode());
        self::assertSame('3001234567', $notification->getPhoneNumber());
        self::assertNull($notification->getEmailAddress());
        self::assertNull($notification->getPreferredLanguage());
    }

    public function testByEmailCarriesTheAddressAndThePreferredLanguage(): void
    {
        $notification = LinkNotification::byEmail('mario@example.com', LinkNotification::LANGUAGE_FRENCH);

        self::assertSame([LinkNotification::CHANNEL_EMAIL], $notification->getChannels());
        self::assertSame('mario@example.com', $notification->getEmailAddress());
        self::assertSame('francais', $notification->getPreferredLanguage());
        self::assertNull($notification->getPhoneNumber());
    }

    public function testToArrayOmitsTheContactsThatWereNotGiven(): void
    {
        self::assertSame(
            ['channels' => ['sms'], 'phoneCountryCode' => '+39', 'phoneNumber' => '3001234567'],
            LinkNotification::bySms('+39', '3001234567')->toArray()
        );
    }

    public function testFromArrayRebuildsANotificationSentBackByTheApi(): void
    {
        $notification = LinkNotification::fromArray([
            'channels' => ['email'],
            'emailAddress' => 'mario@example.com',
            'preferredLanguage' => 'english',
        ]);

        self::assertSame(['email'], $notification->getChannels());
        self::assertSame('mario@example.com', $notification->getEmailAddress());
        self::assertSame('english', $notification->getPreferredLanguage());
    }

    public function testFromArrayReindexesTheChannelsAndCastsThemToStrings(): void
    {
        $notification = LinkNotification::fromArray(['channels' => [3 => 'sms'], 'phoneCountryCode' => 39, 'phoneNumber' => 3001234567]);

        self::assertSame(['sms'], $notification->getChannels());
        self::assertSame('39', $notification->getPhoneCountryCode());
        self::assertSame('3001234567', $notification->getPhoneNumber());
    }

    public function testFromArrayDiscardsAContactThatIsNotScalarAndThenFailsValidation(): void
    {
        $this->expectException(ScalapayException::class);

        LinkNotification::fromArray(['channels' => ['email'], 'emailAddress' => ['mario@example.com']]);
    }

    public function testFromArrayRejectsAPayloadWithoutChannels(): void
    {
        $this->expectException(ScalapayException::class);

        LinkNotification::fromArray(['emailAddress' => 'mario@example.com']);
    }

    public function testFromArrayDropsChannelsThatAreNotScalarInsteadOfWarning(): void
    {
        $notification = LinkNotification::fromArray(['channels' => ['email', ['sms']], 'emailAddress' => 'mario@example.com']);

        self::assertSame(['email'], $notification->getChannels());
    }

    public function testFromArrayRejectsChannelsThatAreNotAList(): void
    {
        $this->expectException(ScalapayException::class);

        LinkNotification::fromArray(['channels' => 'email']);
    }
}
