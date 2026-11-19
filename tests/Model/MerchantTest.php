<?php declare(strict_types=1);

namespace Hval\Scalapay\Tests\Model;

use Hval\Scalapay\Model\Merchant;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(Merchant::class)]
final class MerchantTest extends TestCase
{
    public function testItExposesAndSerialisesBothReturnUris(): void
    {
        $merchant = new Merchant(Merchant::SANDBOX_CONFIRM_URL, Merchant::SANDBOX_CANCEL_URL);

        self::assertSame(Merchant::SANDBOX_CONFIRM_URL, $merchant->getRedirectConfirmUrl());
        self::assertSame(Merchant::SANDBOX_CANCEL_URL, $merchant->getRedirectCancelUrl());
        self::assertSame([
            'redirectConfirmUrl' => Merchant::SANDBOX_CONFIRM_URL,
            'redirectCancelUrl' => Merchant::SANDBOX_CANCEL_URL,
        ], $merchant->toArray());
    }

    public function testFromArraySurvivesTheRoundTrip(): void
    {
        $payload = ['redirectConfirmUrl' => 'https://shop.example/ok', 'redirectCancelUrl' => 'https://shop.example/ko'];

        self::assertSame($payload, Merchant::fromArray($payload)->toArray());
    }

    public function testFromArrayTurnsMissingOrNonScalarUrisIntoEmptyStrings(): void
    {
        $merchant = Merchant::fromArray(['redirectConfirmUrl' => ['https://shop.example/ok']]);

        self::assertSame('', $merchant->getRedirectConfirmUrl());
        self::assertSame('', $merchant->getRedirectCancelUrl());
    }
}
