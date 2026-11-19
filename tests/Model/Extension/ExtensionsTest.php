<?php declare(strict_types=1);

namespace Hval\Scalapay\Tests\Model\Extension;

use Hval\Scalapay\Model\Extension\Extensions;
use Hval\Scalapay\Model\Extension\LinkNotification;
use Hval\Scalapay\Model\Extension\TravelExtension;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(Extensions::class)]
#[UsesClass(LinkNotification::class)]
#[UsesClass(TravelExtension::class)]
final class ExtensionsTest extends TestCase
{
    public function testExtensionsWithoutAnyContentAreEmpty(): void
    {
        $extensions = new Extensions();

        self::assertTrue($extensions->isEmpty());
        self::assertSame([], $extensions->toArray());
        self::assertNull($extensions->getTravel());
        self::assertNull($extensions->getLinkNotification());
        self::assertSame([], $extensions->getAdditional());
    }

    public function testItNestsTheTravelExtensionUnderIndustry(): void
    {
        $extensions = new Extensions(new TravelExtension('2026-10-01', '2026-10-08'));

        self::assertFalse($extensions->isEmpty());
        self::assertSame([
            'industry' => ['travel' => ['startDate' => '2026-10-01', 'endDate' => '2026-10-08']],
        ], $extensions->toArray());
    }

    public function testItNestsTheNotificationUnderTypeLink(): void
    {
        $extensions = new Extensions(null, LinkNotification::byEmail('mario@example.com'));

        self::assertFalse($extensions->isEmpty());
        self::assertSame([
            'type' => ['link' => ['notification' => ['channels' => ['email'], 'emailAddress' => 'mario@example.com']]],
        ], $extensions->toArray());
    }

    public function testItComposesBothBlocksAtOnce(): void
    {
        $extensions = new Extensions(
            new TravelExtension('2026-10-01', '2026-10-08'),
            LinkNotification::bySms('+39', '3001234567')
        );

        $payload = $extensions->toArray();

        self::assertSame(['startDate' => '2026-10-01', 'endDate' => '2026-10-08'], $payload['industry']['travel']);
        self::assertSame(['sms'], $payload['type']['link']['notification']['channels']);
    }

    public function testUndocumentedPropertiesTravelAlongsideTheDocumentedOnes(): void
    {
        $extensions = new Extensions(null, null, ['risk' => ['score' => 7]]);

        self::assertFalse($extensions->isEmpty());
        self::assertSame(['risk' => ['score' => 7]], $extensions->toArray());
    }

    public function testFromArrayReadsBothDocumentedBlocks(): void
    {
        $extensions = Extensions::fromArray([
            'industry' => ['travel' => ['startDate' => '2026-10-01', 'endDate' => '2026-10-08']],
            'type' => ['link' => ['notification' => ['channels' => ['email'], 'emailAddress' => 'mario@example.com']]],
        ]);

        self::assertSame('2026-10-01', $extensions->getTravel()?->getStartDate());
        self::assertSame('mario@example.com', $extensions->getLinkNotification()?->getEmailAddress());
        self::assertSame([], $extensions->getAdditional());
    }

    public function testFromArrayKeepsUnknownBlocksAsAdditional(): void
    {
        $extensions = Extensions::fromArray([
            'industry' => ['travel' => ['startDate' => '2026-10-01', 'endDate' => '2026-10-08']],
            'pluginDetails' => ['name' => 'woocommerce'],
        ]);

        self::assertSame(['pluginDetails' => ['name' => 'woocommerce']], $extensions->getAdditional());
        self::assertNull($extensions->getLinkNotification());
    }

    public function testFromArrayIgnoresAnIndustryBlockWithoutTravel(): void
    {
        $extensions = Extensions::fromArray(['industry' => ['retail' => []]]);

        self::assertNull($extensions->getTravel());
        self::assertSame([], $extensions->getAdditional());
        self::assertTrue($extensions->isEmpty());
    }

    public function testFromArrayOnAnEmptyPayloadYieldsEmptyExtensions(): void
    {
        self::assertTrue(Extensions::fromArray([])->isEmpty());
    }

    public function testAPayloadSurvivesTheRoundTrip(): void
    {
        $payload = [
            'industry' => ['travel' => ['startDate' => '2026-10-01', 'endDate' => '2026-10-08']],
            'type' => ['link' => ['notification' => ['channels' => ['sms'], 'phoneCountryCode' => '+39', 'phoneNumber' => '3001234567']]],
        ];

        self::assertSame($payload, Extensions::fromArray($payload)->toArray());
    }
}
