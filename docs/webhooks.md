# Webhooks

The [quick start](../README.md#5-handle-webhooks) shows how to verify a notification and respond. With a PSR-7 server request, use `$handler->handleRequest($request)` instead of `handle()`.

## Reading the notification

The payload exposes the notification fields:

```php
$payload->getOrderToken();
$payload->getMerchantReference();
$payload->getStatus();               // 'created', 'authorized', 'charged', 'refunded' or 'expired'
$payload->getTotalAmountAsString();  // '40.70'

if ($payload->isCharged() === true) {
    // …
}
```

The other predicates are `isCreated()`, `isAuthorized()`, `isRefunded()` and `isExpired()`.

The top-level `totalAmount` is a JSON number with no currency. `getTotalAmountAsString()` reads it from the raw body exactly as sent, while `getTotalAmount()` returns the decoded number, where `40.70` becomes `40.7`. Only `created` notifications carry `getOrderDetails()`, the echo of the order creation body.

## Delivery

Four things about webhooks are easy to get wrong, and all four are documented behaviour:

- **Reply with exactly `200`.** The documentation names that status, not the 2XX class. A `204 No Content`, the instinctive choice for an empty response, may well be read as a failed delivery and trigger retries.
- **Reply fast and process later.** Retries use exponential backoff, and no maximum response time is documented.
- **Do not assume ordering.** The documentation warns explicitly that retried notifications may arrive out of sequence: a `charged` can precede its `authorized`. A handler must not be a state machine that rejects out-of-order transitions. When the order matters, reconcile with `getOrderDetails()` rather than trusting the last notification.
- **Be idempotent.** The same event can arrive more than once, and the payload carries no delivery id: deduplicate on `orderToken` plus `status`.

## Signature

The signature is computed over the **raw request body**. Never pass a decoded and re-encoded array: signing depends on the exact serialisation, so re-encoding would invalidate it.

Scalapay documents no timestamp tolerance and no replay protection. This library offers one as an opt-in, disabled by default because it is our choice and not the API's:

```php
use Hval\Scalapay\Webhook\WebhookHandler;

$handler = new WebhookHandler('your-api-key', toleranceSeconds: 300);
```

The webhook URI itself is registered in the Partner Portal, under *Developer*; there is no API for it. No separate webhook secret exists — the signature uses your API key.
