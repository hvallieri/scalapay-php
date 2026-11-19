<?php declare(strict_types=1);

namespace Hval\Scalapay\Tests\Response;

use Hval\Scalapay\Model\Amount;
use Hval\Scalapay\Model\Frequency;
use Hval\Scalapay\Model\OrderRequest;
use Hval\Scalapay\Response\MerchantConfiguration;
use Hval\Scalapay\Response\ProductConfiguration;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(MerchantConfiguration::class)]
#[CoversClass(ProductConfiguration::class)]
#[UsesClass(Amount::class)]
#[UsesClass(Frequency::class)]
final class MerchantConfigurationTest extends TestCase
{
    public function testItReadsEveryCombinationTheSandboxReturned(): void
    {
        self::assertCount(14, self::sandboxConfiguration()->all());
    }

    public function testItReadsTheLimitsAsAmountsAlthoughTheyArriveAsNumbers(): void
    {
        $payIn3 = self::sandboxConfiguration()->find(OrderRequest::TYPE_ONLINE, OrderRequest::PRODUCT_PAY_IN_3);

        self::assertNotNull($payIn3);
        self::assertSame(['amount' => '5', 'currency' => 'EUR'], $payIn3->getMinimumAmount()?->toArray());
        self::assertSame(['amount' => '4999.99', 'currency' => 'EUR'], $payIn3->getMaximumAmount()?->toArray());
    }

    public function testItReadsTheFrequencyAndTheFlags(): void
    {
        $configuration = self::sandboxConfiguration();
        $later = $configuration->find(OrderRequest::TYPE_ONLINE, OrderRequest::PRODUCT_LATER);
        $payIn4 = $configuration->find(OrderRequest::TYPE_ONLINE, OrderRequest::PRODUCT_PAY_IN_4);

        self::assertSame(14, $later?->getFrequency()?->getNumber());
        self::assertSame(Frequency::TYPE_DAILY, $later->getFrequency()->getFrequencyType());
        self::assertFalse($later->isStackable());
        self::assertFalse($later->hasSplitFee());
        self::assertTrue($payIn4?->hasSplitFee());
        self::assertSame([], $payIn4->getBlockedUserCountries());
    }

    public function testItKeepsTheTypeThatOrderCreationRejects(): void
    {
        $offlineLink = self::sandboxConfiguration()->find(ProductConfiguration::TYPE_OFFLINE_LINK, OrderRequest::PRODUCT_PAY_IN_4);

        self::assertNotNull($offlineLink);
        self::assertSame('1500', $offlineLink->getMaximumAmount()?->getAmount());
    }

    public function testFindReturnsNullForACombinationThatIsNotEnabled(): void
    {
        self::assertNull(self::sandboxConfiguration()->find(OrderRequest::TYPE_OFFLINE, OrderRequest::PRODUCT_PAY_IN_4));
    }

    public function testSupportsIncludesBothBounds(): void
    {
        $configuration = self::sandboxConfiguration();

        self::assertTrue($configuration->supports(OrderRequest::TYPE_ONLINE, OrderRequest::PRODUCT_PAY_IN_3, new Amount('5.00')));
        self::assertTrue($configuration->supports(OrderRequest::TYPE_ONLINE, OrderRequest::PRODUCT_PAY_IN_3, new Amount('4999.99')));
        self::assertFalse($configuration->supports(OrderRequest::TYPE_ONLINE, OrderRequest::PRODUCT_PAY_IN_3, new Amount('4.99')));
        self::assertFalse($configuration->supports(OrderRequest::TYPE_ONLINE, OrderRequest::PRODUCT_PAY_IN_3, new Amount('5000')));
    }

    public function testTheSameAmountDependsOnTheCombination(): void
    {
        $configuration = self::sandboxConfiguration();

        self::assertTrue($configuration->supports(OrderRequest::TYPE_ONLINE, OrderRequest::PRODUCT_PAY_IN_3, new Amount('2000')));
        self::assertFalse($configuration->supports(OrderRequest::TYPE_ONLINE, OrderRequest::PRODUCT_LATER, new Amount('2000')));
        self::assertTrue($configuration->supports(OrderRequest::TYPE_LINK, OrderRequest::PRODUCT_PAY_NOW_CART_SAVER, new Amount('2000')));
    }

    public function testSupportsRejectsACombinationThatIsNotEnabled(): void
    {
        self::assertFalse(self::sandboxConfiguration()->supports(OrderRequest::TYPE_OFFLINE, OrderRequest::PRODUCT_PAY_IN_4, new Amount('100')));
    }

    public function testSupportsRejectsAnotherCurrency(): void
    {
        self::assertFalse(self::sandboxConfiguration()->supports(OrderRequest::TYPE_ONLINE, OrderRequest::PRODUCT_PAY_IN_3, new Amount('100', 'USD')));
    }

    public function testAMissingLimitLeavesThatSideUnbounded(): void
    {
        $configuration = new ProductConfiguration([
            'type' => 'online',
            'product' => 'pay-in-3',
            'configuration' => ['minimumAmount' => ['amount' => 5, 'currency' => 'EUR']],
        ]);

        self::assertTrue($configuration->accepts(new Amount('1000000')));
        self::assertFalse($configuration->accepts(new Amount('4.99')));
    }

    public function testEntriesThatAreNotObjectsAreSkipped(): void
    {
        $configuration = new MerchantConfiguration([null, 'noise', ['type' => 'online', 'product' => 'pay-in-3']]);

        self::assertCount(1, $configuration->all());
        self::assertNull($configuration->all()[0]->getFrequency());
        self::assertNull($configuration->all()[0]->isStackable());
    }

    public function testTheWholePayloadStaysAvailable(): void
    {
        self::assertSame(self::sandboxPayload(), self::sandboxConfiguration()->getRawPayload());
    }

    private static function sandboxConfiguration(): MerchantConfiguration
    {
        return new MerchantConfiguration(self::sandboxPayload());
    }

    /**
     * Captured from the sandbox with the public test key on 2026-11-18.
     *
     * @return array<array-key, mixed>
     */
    private static function sandboxPayload(): array
    {
        $json = <<<'JSON'
            [{"type":"online","product":"pay-in-3","frequency":{"number":1,"frequencyType":"monthly"},"isStackable":true,"configuration":{"minimumAmount":{"amount":5,"currency":"EUR"},"maximumAmount":{"amount":4999.99,"currency":"EUR"},"splitFee":false,"blockedUserCountries":[]}},
            {"type":"online","product":"pay-in-4","frequency":{"number":1,"frequencyType":"monthly"},"isStackable":true,"configuration":{"minimumAmount":{"amount":5,"currency":"EUR"},"maximumAmount":{"amount":4999.99,"currency":"EUR"},"splitFee":true,"blockedUserCountries":[]}},
            {"type":"online","product":"later","frequency":{"number":14,"frequencyType":"daily"},"isStackable":false,"configuration":{"minimumAmount":{"amount":5,"currency":"EUR"},"maximumAmount":{"amount":1500,"currency":"EUR"},"splitFee":false,"blockedUserCountries":[]}},
            {"type":"online","product":"pay-now-cart-saver","frequency":{"number":0,"frequencyType":"daily"},"isStackable":false,"configuration":{"minimumAmount":{"amount":5,"currency":"EUR"},"maximumAmount":{"amount":1500,"currency":"EUR"},"splitFee":false,"blockedUserCountries":[]}},
            {"type":"online","product":"pay-now-checkout","frequency":{"number":0,"frequencyType":"daily"},"isStackable":true,"configuration":{"minimumAmount":{"amount":5,"currency":"EUR"},"maximumAmount":{"amount":1500,"currency":"EUR"},"splitFee":false,"blockedUserCountries":[]}},
            {"type":"online","product":"pay-in-x","frequency":{"number":1,"frequencyType":"monthly"},"isStackable":false,"configuration":{"minimumAmount":{"amount":701,"currency":"EUR"},"maximumAmount":{"amount":703,"currency":"EUR"},"splitFee":false,"blockedUserCountries":[]}},
            {"type":"online","product":"pay-now-standalone","frequency":{"number":0,"frequencyType":"daily"},"isStackable":false,"configuration":{"minimumAmount":{"amount":5,"currency":"EUR"},"maximumAmount":{"amount":1500,"currency":"EUR"},"splitFee":false,"blockedUserCountries":[]}},
            {"type":"offline","product":"pay-in-3","frequency":{"number":1,"frequencyType":"monthly"},"isStackable":false,"configuration":{"minimumAmount":{"amount":5,"currency":"EUR"},"maximumAmount":{"amount":1500,"currency":"EUR"},"splitFee":false,"blockedUserCountries":[]}},
            {"type":"link","product":"pay-in-3","frequency":{"number":1,"frequencyType":"monthly"},"isStackable":true,"configuration":{"minimumAmount":{"amount":5,"currency":"EUR"},"maximumAmount":{"amount":1500,"currency":"EUR"},"splitFee":false,"blockedUserCountries":[]}},
            {"type":"link","product":"pay-in-4","frequency":{"number":1,"frequencyType":"monthly"},"isStackable":true,"configuration":{"minimumAmount":{"amount":5,"currency":"EUR"},"maximumAmount":{"amount":1500,"currency":"EUR"},"splitFee":false,"blockedUserCountries":[]}},
            {"type":"link","product":"pay-now-cart-saver","frequency":{"number":0,"frequencyType":"daily"},"isStackable":false,"configuration":{"minimumAmount":{"amount":5,"currency":"EUR"},"maximumAmount":{"amount":2000,"currency":"EUR"},"splitFee":false,"blockedUserCountries":[]}},
            {"type":"link","product":"pay-now-checkout","frequency":{"number":0,"frequencyType":"daily"},"isStackable":false,"configuration":{"minimumAmount":{"amount":5,"currency":"EUR"},"maximumAmount":{"amount":1500,"currency":"EUR"},"splitFee":false,"blockedUserCountries":[]}},
            {"type":"offline-link","product":"pay-in-3","frequency":{"number":1,"frequencyType":"monthly"},"isStackable":false,"configuration":{"minimumAmount":{"amount":5,"currency":"EUR"},"maximumAmount":{"amount":1500,"currency":"EUR"},"splitFee":false,"blockedUserCountries":[]}},
            {"type":"offline-link","product":"pay-in-4","frequency":{"number":1,"frequencyType":"monthly"},"isStackable":false,"configuration":{"minimumAmount":{"amount":5,"currency":"EUR"},"maximumAmount":{"amount":1500,"currency":"EUR"},"splitFee":false,"blockedUserCountries":[]}}]
            JSON;

        $payload = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($payload);

        return $payload;
    }
}
