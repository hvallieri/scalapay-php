<?php declare(strict_types=1);

namespace Hval\Scalapay\Tests\Model;

use Hval\Scalapay\Exception\ScalapayException;
use Hval\Scalapay\Model\Address;
use Hval\Scalapay\Model\Amount;
use Hval\Scalapay\Model\Consumer;
use Hval\Scalapay\Model\Discount;
use Hval\Scalapay\Model\Extension\Extensions;
use Hval\Scalapay\Model\Extension\LinkNotification;
use Hval\Scalapay\Model\Extension\TravelExtension;
use Hval\Scalapay\Model\Frequency;
use Hval\Scalapay\Model\Item;
use Hval\Scalapay\Model\Merchant;
use Hval\Scalapay\Model\OrderRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(OrderRequest::class)]
#[UsesClass(Address::class)]
#[UsesClass(Amount::class)]
#[UsesClass(Consumer::class)]
#[UsesClass(Discount::class)]
#[UsesClass(Extensions::class)]
#[UsesClass(Frequency::class)]
#[UsesClass(Item::class)]
#[UsesClass(LinkNotification::class)]
#[UsesClass(Merchant::class)]
#[UsesClass(TravelExtension::class)]
final class OrderRequestTest extends TestCase
{
    public function testItRejectsAnOrderWithoutItems(): void
    {
        $this->expectException(ScalapayException::class);
        $this->expectExceptionMessage('at least one item');

        $this->anOrder(items: []);
    }

    public function testItRejectsAnItemThatIsNotAnItemInstance(): void
    {
        $this->expectException(ScalapayException::class);
        $this->expectExceptionMessage(Item::class);

        $this->anOrder(items: [$this->anItem(), ['name' => 'T-Shirt']]);
    }

    public function testAnOrderOfTypeLinkRequiresExtensions(): void
    {
        $this->expectException(ScalapayException::class);
        $this->expectExceptionMessage('link');

        $this->anOrder(type: OrderRequest::TYPE_LINK);
    }

    public function testAnOrderOfTypeLinkRequiresANotificationInsideItsExtensions(): void
    {
        $this->expectException(ScalapayException::class);

        $this->anOrder(
            type: OrderRequest::TYPE_LINK,
            extensions: new Extensions(new TravelExtension('2026-10-01', '2026-10-08'))
        );
    }

    public function testAnOrderOfTypeLinkIsAcceptedWithItsNotification(): void
    {
        $order = $this->anOrder(
            type: OrderRequest::TYPE_LINK,
            extensions: new Extensions(null, LinkNotification::byEmail('mario@example.com'))
        );

        self::assertSame(OrderRequest::TYPE_LINK, $order->getType());
        self::assertSame('mario@example.com', $order->getExtensions()?->getLinkNotification()?->getEmailAddress());
    }

    public function testTheNotificationIsOnlyDemandedForTheLinkType(): void
    {
        $order = $this->anOrder(type: OrderRequest::TYPE_ONLINE);

        self::assertSame(OrderRequest::TYPE_ONLINE, $order->getType());
        self::assertNull($order->getExtensions());
    }

    public function testAMinimalOrderCarriesOnlyTheRequiredProperties(): void
    {
        $payload = $this->anOrder()->toArray();

        self::assertSame(['totalAmount', 'consumer', 'merchant', 'shipping', 'items'], array_keys($payload));
        self::assertSame(['amount' => '10.00', 'currency' => 'EUR'], $payload['totalAmount']);
        self::assertCount(1, $payload['items']);
    }

    public function testAnEmptyShippingAddressIsStillSentAsAnEmptyObject(): void
    {
        $payload = $this->anOrder(shipping: Address::empty())->toArray();

        self::assertEquals(new \stdClass(), $payload['shipping']);
        self::assertStringContainsString('"shipping":{}', (string) json_encode($payload));
    }

    public function testAnEmptyBillingAddressIsSentAsAnEmptyObjectToo(): void
    {
        $payload = $this->anOrder(billing: Address::empty())->toArray();

        self::assertStringContainsString('"billing":{}', (string) json_encode($payload));
    }

    public function testEveryOptionalPropertyReachesThePayloadWhenGiven(): void
    {
        $payload = $this->anOrder(
            billing: new Address('Mario Rossi', 'Via Roma 1', 'Milano', '20100', 'IT'),
            discounts: [new Discount('SUMMER', new Amount('2.00'))],
            merchantReference: 'order-42',
            taxAmount: new Amount('1.80'),
            shippingAmount: new Amount('4.90'),
            type: OrderRequest::TYPE_ONLINE,
            product: OrderRequest::PRODUCT_PAY_IN_4,
            frequency: new Frequency(1),
            orderExpiryMilliseconds: 600000,
        )->toArray();

        self::assertSame('Via Roma 1', $payload['billing']['line1']);
        self::assertSame([['displayName' => 'SUMMER', 'amount' => ['amount' => '2.00', 'currency' => 'EUR']]], $payload['discounts']);
        self::assertSame('order-42', $payload['merchantReference']);
        self::assertSame(['amount' => '1.80', 'currency' => 'EUR'], $payload['taxAmount']);
        self::assertSame(['amount' => '4.90', 'currency' => 'EUR'], $payload['shippingAmount']);
        self::assertSame('online', $payload['type']);
        self::assertSame('pay-in-4', $payload['product']);
        self::assertSame(['number' => 1, 'frequencyType' => 'monthly'], $payload['frequency']);
        self::assertSame(600000, $payload['orderExpiryMilliseconds']);
    }

    public function testTheGettersReturnWhatWasGiven(): void
    {
        $totalAmount = new Amount('10.00');
        $consumer = $this->aConsumer();
        $merchant = $this->aMerchant();
        $shipping = Address::empty();
        $billing = new Address('Mario Rossi');
        $item = $this->anItem();
        $discount = new Discount('SUMMER', new Amount('2.00'));
        $frequency = new Frequency(3);

        $order = new OrderRequest(
            totalAmount: $totalAmount,
            consumer: $consumer,
            merchant: $merchant,
            shipping: $shipping,
            items: [$item],
            billing: $billing,
            discounts: [$discount],
            merchantReference: 'order-42',
            taxAmount: new Amount('1.80'),
            shippingAmount: new Amount('4.90'),
            product: OrderRequest::PRODUCT_LATER,
            frequency: $frequency,
            orderExpiryMilliseconds: 600000,
        );

        self::assertSame($totalAmount, $order->getTotalAmount());
        self::assertSame($consumer, $order->getConsumer());
        self::assertSame($merchant, $order->getMerchant());
        self::assertSame($shipping, $order->getShipping());
        self::assertSame($billing, $order->getBilling());
        self::assertSame([$item], $order->getItems());
        self::assertSame([$discount], $order->getDiscounts());
        self::assertSame('order-42', $order->getMerchantReference());
        self::assertSame('1.80', $order->getTaxAmount()?->getAmount());
        self::assertSame('4.90', $order->getShippingAmount()?->getAmount());
        self::assertSame(OrderRequest::PRODUCT_LATER, $order->getProduct());
        self::assertSame($frequency, $order->getFrequency());
        self::assertSame(600000, $order->getOrderExpiryMilliseconds());
        self::assertNull($order->getType());
    }

    public function testAnEmptyExtensionsBlockIsNotSent(): void
    {
        $payload = $this->anOrder(extensions: new Extensions())->toArray();

        self::assertArrayNotHasKey('extensions', $payload);
    }

    public function testANonEmptyExtensionsBlockIsSent(): void
    {
        $payload = $this->anOrder(extensions: new Extensions(new TravelExtension('2026-10-01', '2026-10-08')))->toArray();

        self::assertSame(['startDate' => '2026-10-01', 'endDate' => '2026-10-08'], $payload['extensions']['industry']['travel']);
    }

    public function testUndocumentedTopLevelPropertiesAreSentAsTheyAre(): void
    {
        $payload = $this->anOrder(additional: ['channel' => 'ecommerce'])->toArray();

        self::assertSame('ecommerce', $payload['channel']);
    }

    public function testAModelledPropertyOverwritesTheAdditionalOneOfTheSameName(): void
    {
        $payload = $this->anOrder(
            merchantReference: 'order-42',
            additional: ['totalAmount' => 'nonsense', 'merchantReference' => 'from-additional'],
        )->toArray();

        self::assertSame(['amount' => '10.00', 'currency' => 'EUR'], $payload['totalAmount']);
        self::assertSame('order-42', $payload['merchantReference']);
    }

    public function testAnAdditionalPropertySurvivesWhenTheModelledOneWasNotGiven(): void
    {
        $payload = $this->anOrder(additional: ['merchantReference' => 'from-additional'])->toArray();

        self::assertSame('from-additional', $payload['merchantReference']);
    }

    public function testAnEmptyDiscountListIsNotSent(): void
    {
        self::assertArrayNotHasKey('discounts', $this->anOrder()->toArray());
    }

    /**
     * @param list<mixed>|null $items
     * @param list<Discount> $discounts
     * @param array<string, mixed> $additional
     */
    private function anOrder(
        ?array $items = null,
        ?Address $shipping = null,
        ?Address $billing = null,
        array $discounts = [],
        ?string $merchantReference = null,
        ?Amount $taxAmount = null,
        ?Amount $shippingAmount = null,
        ?string $type = null,
        ?string $product = null,
        ?Frequency $frequency = null,
        ?int $orderExpiryMilliseconds = null,
        ?Extensions $extensions = null,
        array $additional = [],
    ): OrderRequest {
        return new OrderRequest(
            totalAmount: new Amount('10.00'),
            consumer: $this->aConsumer(),
            merchant: $this->aMerchant(),
            shipping: $shipping ?? new Address('Mario Rossi', 'Via Roma 1', 'Milano', '20100', 'IT'),
            items: $items ?? [$this->anItem()],
            billing: $billing,
            discounts: $discounts,
            merchantReference: $merchantReference,
            taxAmount: $taxAmount,
            shippingAmount: $shippingAmount,
            type: $type,
            product: $product,
            frequency: $frequency,
            orderExpiryMilliseconds: $orderExpiryMilliseconds,
            extensions: $extensions,
            additional: $additional,
        );
    }

    private function aConsumer(): Consumer
    {
        return new Consumer('Mario', 'Rossi', 'test@scalapay.com', '+393001234567');
    }

    private function aMerchant(): Merchant
    {
        return new Merchant(Merchant::SANDBOX_CONFIRM_URL, Merchant::SANDBOX_CANCEL_URL);
    }

    private function anItem(): Item
    {
        return new Item('T-Shirt', '12341234', 'clothes', new Amount('10.00'));
    }
}
