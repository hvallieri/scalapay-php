<?php declare(strict_types=1);

namespace Hval\Scalapay\Model;

use Hval\Scalapay\Exception\ScalapayException;
use Hval\Scalapay\Model\Extension\Extensions;

/**
 * Body of `POST /v2/orders`.
 *
 * Required: `consumer`, `items`, `merchant`, `shipping`, `totalAmount`. Note that
 * `billing` is optional while `shipping` is required, the opposite of what intuition
 * suggests; pass Address::empty() when shipping is meaningless.
 *
 * `type`, `product` and `frequency` depend on the merchant contract: the constants below
 * list the syntactically valid values, not the ones a given account may use. No default
 * is imposed for that reason.
 */
final class OrderRequest
{
    public const TYPE_ONLINE = 'online';
    public const TYPE_OFFLINE = 'offline';
    public const TYPE_LINK = 'link';

    /**
     * Absent from the specification and from the merchant configuration: listed by the
     * `POST /v2/orders` validator in a sandbox error on 2026-11-18. Their meaning is
     * undocumented.
     */
    public const TYPE_CARD = 'card';
    public const TYPE_CARD_ONLINE = 'card-online';
    public const TYPE_CARD_OFFLINE = 'card-offline';
    public const TYPE_CHARGE_CARD = 'charge-card';

    public const PRODUCT_PAY_IN_3 = 'pay-in-3';
    public const PRODUCT_PAY_IN_4 = 'pay-in-4';
    public const PRODUCT_LATER = 'later';
    public const PRODUCT_PAY_NOW_STANDALONE = 'pay-now-standalone';

    /**
     * Absent from the specification: first seen in `GET /v3/configurations`, then accepted
     * by `POST /v2/orders` in the sandbox on 2026-11-18.
     */
    public const PRODUCT_PAY_NOW_CART_SAVER = 'pay-now-cart-saver';
    public const PRODUCT_PAY_NOW_CHECKOUT = 'pay-now-checkout';
    public const PRODUCT_PAY_IN_X = 'pay-in-x';

    /**
     * Absent from the specification and from the merchant configuration: listed by the
     * `POST /v2/orders` validator in a sandbox error on 2026-11-18. Their meaning is
     * undocumented.
     */
    public const PRODUCT_PAY_IN_6 = 'pay-in-6';
    public const PRODUCT_PAY_IN_9 = 'pay-in-9';
    public const PRODUCT_PAY_IN_12 = 'pay-in-12';

    /**
     * @param Amount $totalAmount
     * @param Consumer $consumer
     * @param Merchant $merchant
     * @param Address $shipping
     * @param list<Item> $items
     * @param Address|null $billing
     * @param list<Discount> $discounts
     * @param string|null $merchantReference
     * @param Amount|null $taxAmount
     * @param Amount|null $shippingAmount
     * @param string|null $type
     * @param string|null $product
     * @param Frequency|null $frequency
     * @param int|null $orderExpiryMilliseconds
     * @param Extensions|null $extensions
     * @param array<string, mixed> $additional undocumented top-level properties, sent as they are
     */
    public function __construct(
        private readonly Amount $totalAmount,
        private readonly Consumer $consumer,
        private readonly Merchant $merchant,
        private readonly Address $shipping,
        private readonly array $items,
        private readonly ?Address $billing = null,
        private readonly array $discounts = [],
        private readonly ?string $merchantReference = null,
        private readonly ?Amount $taxAmount = null,
        private readonly ?Amount $shippingAmount = null,
        private readonly ?string $type = null,
        private readonly ?string $product = null,
        private readonly ?Frequency $frequency = null,
        private readonly ?int $orderExpiryMilliseconds = null,
        private readonly ?Extensions $extensions = null,
        private readonly array $additional = [],
    ) {
        if ($this->items === []) {
            throw new ScalapayException('An order requires at least one item.');
        }

        foreach ($this->items as $item) {
            if ($item instanceof Item === false) {
                throw new ScalapayException('Every order item must be an instance of ' . Item::class . '.');
            }
        }

        if ($this->type === self::TYPE_LINK && ($this->extensions === null || $this->extensions->getLinkNotification() === null)) {
            throw new ScalapayException('Orders of type "link" require extensions with a link notification.');
        }
    }

    public function getTotalAmount(): Amount
    {
        return $this->totalAmount;
    }

    public function getConsumer(): Consumer
    {
        return $this->consumer;
    }

    public function getMerchant(): Merchant
    {
        return $this->merchant;
    }

    public function getShipping(): Address
    {
        return $this->shipping;
    }

    /**
     * @return list<Item>
     */
    public function getItems(): array
    {
        return $this->items;
    }

    public function getBilling(): ?Address
    {
        return $this->billing;
    }

    /**
     * @return list<Discount>
     */
    public function getDiscounts(): array
    {
        return $this->discounts;
    }

    public function getMerchantReference(): ?string
    {
        return $this->merchantReference;
    }

    public function getTaxAmount(): ?Amount
    {
        return $this->taxAmount;
    }

    public function getShippingAmount(): ?Amount
    {
        return $this->shippingAmount;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function getProduct(): ?string
    {
        return $this->product;
    }

    public function getFrequency(): ?Frequency
    {
        return $this->frequency;
    }

    public function getOrderExpiryMilliseconds(): ?int
    {
        return $this->orderExpiryMilliseconds;
    }

    public function getExtensions(): ?Extensions
    {
        return $this->extensions;
    }

    /**
     * An empty address is returned as an empty \stdClass, not as an empty array: it would
     * otherwise encode as `[]`, which the API rejects with `"shipping" must be an object`.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $payload = $this->additional;

        $payload['totalAmount'] = $this->totalAmount->toArray();
        $payload['consumer'] = $this->consumer->toArray();
        $payload['merchant'] = $this->merchant->toArray();
        $payload['shipping'] = self::addressPayload($this->shipping);
        $payload['items'] = array_map(static fn (Item $item): array => $item->toArray(), $this->items);

        if ($this->billing !== null) {
            $payload['billing'] = self::addressPayload($this->billing);
        }

        if ($this->discounts !== []) {
            $payload['discounts'] = array_map(static fn (Discount $discount): array => $discount->toArray(), $this->discounts);
        }

        if ($this->merchantReference !== null) {
            $payload['merchantReference'] = $this->merchantReference;
        }

        if ($this->taxAmount !== null) {
            $payload['taxAmount'] = $this->taxAmount->toArray();
        }

        if ($this->shippingAmount !== null) {
            $payload['shippingAmount'] = $this->shippingAmount->toArray();
        }

        if ($this->type !== null) {
            $payload['type'] = $this->type;
        }

        if ($this->product !== null) {
            $payload['product'] = $this->product;
        }

        if ($this->frequency !== null) {
            $payload['frequency'] = $this->frequency->toArray();
        }

        if ($this->orderExpiryMilliseconds !== null) {
            $payload['orderExpiryMilliseconds'] = $this->orderExpiryMilliseconds;
        }

        if ($this->extensions !== null && $this->extensions->isEmpty() === false) {
            $payload['extensions'] = $this->extensions->toArray();
        }

        return $payload;
    }

    /**
     * @return array<string, string>|\stdClass
     */
    private static function addressPayload(Address $address): array|\stdClass
    {
        $payload = $address->toArray();

        return $payload === [] ? new \stdClass() : $payload;
    }
}
