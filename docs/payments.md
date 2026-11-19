# Payments

Everything that happens to an order after the customer has paid: capture, delay, void and refund, plus idempotency and the status values. Check the status before acting, as shown in the [quick start](../README.md#3-check-the-status-before-capturing).

## Capture

```php
$result = $scalapay->capture($token);
```

The first instalment is charged and the order becomes `charged`. To capture less than the order total, pass an amount; this endpoint also accepts an updated merchant reference, which it applies to the order.

```php
use Hval\Scalapay\Model\Amount;

$result = $scalapay->capture($token, new Amount('20.00'), 'order-1234-partial');
```

What happens to the remainder of a partial capture is not documented.

## Delay the capture, or void the authorization

```php
// Announce a delayed capture. The instalment plan is created but nothing is settled.
$scalapay->delayCapture($token);

// Later, either capture…
$scalapay->capture($token);

// …or release the authorization and cancel the order.
$scalapay->voidOrder($token);
```

An authorization left uncaptured is voided automatically once `authorizationExpiryMilliseconds` elapses. The default is five days, and is sent explicitly:

```php
$scalapay->delayCapture($token, authorizationExpiryMilliseconds: 86_400_000); // one day
```

No webhook is documented for a void, so do not wait for one after cancelling.

## Refund

```php
use Hval\Scalapay\Model\Amount;

$refund = $scalapay->refund($token, new Amount('190.00'), 'refund-ABC123');

$refund->getRefundToken();
$refund->getRefundedAt();
```

The amount is always explicit: unlike capture, there is no implicit "refund everything".

Refunds can also be issued by hand from the Scalapay Partner Portal, so a `refunded` webhook does not imply your code called this method.

## Idempotency

Every write operation accepts an optional idempotency key as its last argument:

```php
$created = $scalapay->createOrder($order, 'order-1234-attempt-1');

if ($created->isIdempotentReplay() === true) {
    // this response came from the cache; the operation was not executed again
}
```

## Status values

The API has three distinct status axes, easily mistaken for three spellings of one thing. They must never be unified nor compared with each other. `Hval\Scalapay\Status` gathers the known values of each.

| Axis              | Where                                          | Case  | Known values                                              |
|-------------------|------------------------------------------------|-------|-----------------------------------------------------------|
| Order status      | `getOrderDetails()`, webhooks                  | lower | `created`, `authorized`, `charged`, `refunded`, `expired` |
| Operation outcome | the result of `capture()` and `delayCapture()` | UPPER | `AUTHORIZED`, `APPROVED`                                  |
| Capture status    | `getCaptureStatus()`                           | lower | `captured`, `pending`, `delayed`, `voided`                |

**None of them is an enum, on purpose.** The documentation explicitly advises against hard-coding the set of order statuses because it may change, and a closed enum would throw exactly where Scalapay warned it would. Compare against the constants, but handle unknown values instead of rejecting them. `Status::knownOrderStatuses()`, `knownOperationOutcomes()` and `knownCaptureStatuses()` list the known values of each axis, which makes a new one easy to spot and log:

```php
use Hval\Scalapay\Status;

$details = $scalapay->getOrderDetails($token);

if (in_array($details->getStatus(), Status::knownOrderStatuses(), true) === false) {
    // a status this library does not know yet: log it, do not reject it
}
```

Every response model also exposes `getRawPayload()`, because responses carry keys that appear in no schema.
