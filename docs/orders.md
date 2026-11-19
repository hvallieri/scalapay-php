# Orders

The [quick start](../README.md#2-create-an-order) shows how to create an order. This page covers the values that depend on your contract, the special kinds of order, and the endpoints that look orders up or change them.

## Contract-dependent values

`type`, `product` and `frequency` must match your Scalapay contract. The constants on `OrderRequest` list the syntactically valid values, **not** the ones your account may use: `pay-in-4` or `pay-now-standalone` can be rejected by an account that does not have them enabled. For that reason this library imposes no default — pick the values your contract allows:

```php
use Hval\Scalapay\Model\Frequency;
use Hval\Scalapay\Model\OrderRequest;

$order = new OrderRequest(
    // … the usual required fields …
    type: OrderRequest::TYPE_ONLINE,
    product: OrderRequest::PRODUCT_LATER,
    frequency: new Frequency(14, Frequency::TYPE_DAILY),
);
```

Do not send a `frequency` with `pay-in-3`, `pay-in-4` or `pay-now-standalone`: the specification excludes it for those products.

Some constants go beyond the specification. `PRODUCT_PAY_NOW_CART_SAVER`, `PRODUCT_PAY_NOW_CHECKOUT`, `PRODUCT_PAY_IN_X` and `Frequency::TYPE_DAILY` are undocumented, but order creation accepts them. `TYPE_CARD`, `TYPE_CARD_ONLINE`, `TYPE_CARD_OFFLINE`, `TYPE_CHARGE_CARD`, `PRODUCT_PAY_IN_6`, `PRODUCT_PAY_IN_9` and `PRODUCT_PAY_IN_12` are accepted by the API's validator, but nothing documents what they mean.

Amount limits are contractual too, and the documentation gives no figures beyond stating that the minimum order cannot be below 5 euro. Exceeding the maximum yields an `order_amount_exceeds_maximum_limit` error. The [merchant configuration](#merchant-configuration) tells you the actual limits of your account.

## Pay-by-link and travel orders

Pay-by-link is not a separate endpoint: it is an order of type `link` carrying a notification block.

```php
use Hval\Scalapay\Model\Extension\Extensions;
use Hval\Scalapay\Model\Extension\LinkNotification;
use Hval\Scalapay\Model\OrderRequest;

$order = new OrderRequest(
    // … the usual required fields …
    type: OrderRequest::TYPE_LINK,
    extensions: new Extensions(
        linkNotification: LinkNotification::bySms('+39', '3470000000', LinkNotification::LANGUAGE_ITALIAN),
    ),
);
```

The online link is valid for **24 hours**. (The instore pay-by-link, which this library does not cover, expires in 10 minutes — do not carry one expectation over to the other.)

Travel merchants must supply the travel dates, as plain `YYYY-MM-DD` values, with the start date later than the order creation date:

```php
use Hval\Scalapay\Model\Extension\Extensions;
use Hval\Scalapay\Model\Extension\TravelExtension;

$extensions = new Extensions(travel: new TravelExtension('2026-10-15', '2026-10-30'));
```

Travel orders frequently have no meaningful shipping address. `shipping` stays required by the schema, so pass an empty one, as the official examples do:

```php
use Hval\Scalapay\Model\Address;

$shipping = Address::empty();
```

## Merchant configuration

`getMerchantConfiguration()` returns the `type`/`product` combinations enabled on your account, each with its frequency and amount limits. Use it to decide whether to offer Scalapay before creating an order that would be rejected:

```php
use Hval\Scalapay\Model\Amount;
use Hval\Scalapay\Model\OrderRequest;

$configuration = $scalapay->getMerchantConfiguration();

if ($configuration->supports(OrderRequest::TYPE_ONLINE, OrderRequest::PRODUCT_PAY_IN_3, new Amount('249.90'))) {
    // show Scalapay at checkout
}

$payIn3 = $configuration->find(OrderRequest::TYPE_ONLINE, OrderRequest::PRODUCT_PAY_IN_3);
$payIn3?->getMinimumAmount();
$payIn3?->getMaximumAmount();
```

**This endpoint (`GET /v3/configurations`) is undocumented**, so it comes with no stability guarantee from Scalapay. Consider caching its result rather than calling it on every page view.

A few things to know:

- `supports()` treats both limits as inclusive — an assumption, since nothing documents it — and never accepts an amount in another currency.
- The configuration reports values that order creation rejects: type `offline-link` (`ProductConfiguration::TYPE_OFFLINE_LINK`) is listed but `POST /v2/orders` refuses it. Do not feed configuration values back into an order blindly.
- `getFrequency()` is present for every product, including those that must not send a frequency when creating an order.
- Limits arrive as JSON numbers rather than strings; they are converted to `Amount` without float arithmetic for integers, rounded to two decimals otherwise. `Amount::compareTo()` compares amounts digit by digit, with neither floats nor bcmath.

## Countries and currency

Scalapay authorises **EUR only**, in Austria, Belgium, Finland, France, French Guiana, Germany, Guadeloupe, Italy, Martinique, Mayotte, the Netherlands, Portugal, Réunion and Spain.

Note that five of those are French overseas departments with their own ISO alpha-2 codes — `GF`, `GP`, `MQ`, `YT`, `RE` — distinct from `FR`. This library does not enforce the list.

## Search orders by reference

```php
$references = $scalapay->getOrdersByReference(merchantOrderReference: 'order-1234');

foreach ($references as $reference) {
    $reference->getOrderToken();
    $reference->getCreatedAt();
}
```

At least one criterion is required, and this library checks it before sending the request; several criteria are combined with AND.

**This endpoint returns at most 100 matches and silently truncates.** There is no paging parameter and no flag telling you results were dropped. With more than 100 matches, narrow the search.

Mind the naming: what the rest of the API calls `merchantReference` is `merchantOrderReference` here.

## Update the merchant references

```php
$scalapay->updateOrderReferences($token, 'order-1234', null);
```

**This endpoint replaces, it does not merge.** The documentation states "Omit the field to remove the value", so omitting a reference *clears* it — despite the operation being named "update". Both references are therefore required parameters with no defaults, so that clearing one is always deliberate: pass `null` to clear, or re-send the current value to keep it.

The response cannot tell "cleared" from "unchanged", so do not use it to infer the stored state.
