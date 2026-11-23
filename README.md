# hval/scalapay-php

Unofficial PHP library for the [Scalapay](https://developers.scalapay.com/) Buy Now Pay Later payment gateway: orders, capture, refund, void and webhooks.

## Requirements

- PHP >= 8.2
- Any PSR-18 compatible HTTP client (e.g. `guzzlehttp/guzzle`, `symfony/http-client`)
- A PSR-7 / PSR-17 implementation (e.g. `nyholm/psr7`, `guzzlehttp/psr7`)

## Installation

```bash
composer require hval/scalapay-php
```

Install a PSR-18 client if you don't already have one:

```bash
# Guzzle
composer require guzzlehttp/guzzle

# Symfony HttpClient
composer require symfony/http-client nyholm/psr7
```

## Quick Start

### 1. Instantiate the client

The third argument is a PSR-17 factory providing both `RequestFactoryInterface` and `StreamFactoryInterface`. Both factories below satisfy that.

**With Guzzle:**

```php
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\HttpFactory;
use Hval\Scalapay\Config;
use Hval\Scalapay\ScalapayClient;

$factory = new HttpFactory();

$scalapay = new ScalapayClient(Config::sandbox('your-api-key'), new Client(['timeout' => 30, 'connect_timeout' => 5]), $factory);
```

PSR-18 has no notion of timeouts: set them on your HTTP client, otherwise a call can hang indefinitely. A Guzzle client created without options never times out.

**With Symfony HttpClient:**

```php
use Hval\Scalapay\Config;
use Hval\Scalapay\ScalapayClient;
use Nyholm\Psr7\Factory\Psr17Factory;
use Symfony\Component\HttpClient\Psr18Client;

$factory = new Psr17Factory();

$scalapay = new ScalapayClient(Config::sandbox('your-api-key'), new Psr18Client(), $factory);
```

Use `Config::production('your-api-key')` when going live. The API key and the base URI always change together, which is why they are configured as one object: using one environment's key against the other's URI is a documented cause of authentication failure.

### 2. Create an order

```php
use Hval\Scalapay\Model\Address;
use Hval\Scalapay\Model\Amount;
use Hval\Scalapay\Model\Consumer;
use Hval\Scalapay\Model\Item;
use Hval\Scalapay\Model\Merchant;
use Hval\Scalapay\Model\OrderRequest;

$order = new OrderRequest(
    totalAmount: new Amount('40.70'),
    consumer: new Consumer('Mario', 'Rossi', 'mario.rossi@example.com', '+393001234567'),
    merchant: new Merchant('https://shop.example.com/success', 'https://shop.example.com/cancel'),
    shipping: new Address('Mario Rossi', 'Via Puglia, 58', 'Castelfranco Veneto', '31033', 'IT', '+393001234567'),
    items: [
        new Item('T-Shirt', '12341234', 'clothes', new Amount('10.00')),
        new Item('Jeans', '12341235', 'clothes', new Amount('20.00'), quantity: 1),
    ],
    merchantReference: 'order-1234',
    taxAmount: new Amount('3.70'),
    shippingAmount: new Amount('10.00'),
);

$created = $scalapay->createOrder($order);

$token = $created->getToken();               // store this: you will need it for every later call
$checkoutUrl = $created->getCheckoutUrl();   // redirect the customer here
```

Amounts are strings, deliberately: the API declares them as such, and converting to float would introduce rounding errors and drop trailing zeros. Use `Amount::fromMinorUnits(4070)` if your own storage is in cents (two decimal places, as for the euro). An amount that is not a decimal number is rejected on construction.

Note that `billing` is optional while `shipping` is required — the opposite of what intuition suggests.

### 3. Check the status before capturing

Only authorized orders can be captured, so reading the status between the customer's return and the capture is strongly recommended.

```php
$details = $scalapay->getOrderDetails($token);

if ($details->isAuthorized() === false) {
    // do not capture: the payment was not authorized
}
```

`isCharged()`, `isRefunded()` and `isExpired()` read the order status as well; `isCaptured()` reads the capture status. When a payment is declined, `getLastEligibilityFeedback()` tells why (e.g. `DECLINED_PAYMENT_AUTHORIZATION`).

`getStatus()` and `getCaptureStatus()` are two independent axes and must not be compared with one another. Both are strings, not enums: see [Status values](docs/payments.md#status-values).

### 4. Capture the payment

```php
$result = $scalapay->capture($token);
```

The first instalment is charged and the order becomes `charged`. Partial capture, delayed capture, void and refund are covered in [Payments](docs/payments.md).

### 5. Handle webhooks

```php
use Hval\Scalapay\Exception\WebhookSignatureException;
use Hval\Scalapay\Webhook\WebhookHandler;

$handler = new WebhookHandler('your-api-key');

try {
    $payload = $handler->handle(file_get_contents('php://input'), getallheaders());
} catch (WebhookSignatureException $exception) {
    http_response_code(401);

    return;
}

// Persist first, respond immediately, process afterwards.
$queue->push($payload->getRawBody());

http_response_code(200);
```

Read [Webhooks](docs/webhooks.md) before going live: the response status, retries, ordering and duplicates all need care.

## Documentation

- [Orders](docs/orders.md) — contract-dependent values, pay-by-link and travel orders, merchant configuration, countries and currency, searching and updating references
- [Payments](docs/payments.md) — capture, delay, void, refund, idempotency and status values
- [Webhooks](docs/webhooks.md) — reading notifications, delivery and signature
- [Errors](docs/errors.md) — exception hierarchy, API errors and validation errors

## Sandbox testing

Scalapay publishes a shared sandbox API key, so you can exercise the API without a merchant account:

```php
use Hval\Scalapay\Config;

$scalapay = new ScalapayClient(Config::sandboxWithTestKey(), new Client(), $factory);
```

Orders created with it are not private. Test cards for the checkout: `5200828282828210` succeeds, `4000000000000341` fails; any CVV and any future expiry date work. Note that completing a checkout requires signing in as a Scalapay test user, so the flow cannot be fully automated. In our tests the checkout could not be completed with the shared key: the full flow needs your own sandbox account.

## Out of scope

The Instore API (`/v1/instore/*`, `/v2/instore/paybylink/*`) and the Reporting API (`/v1/reporting/*`) are not covered. Instore authenticates with a per-terminal device token rather than the merchant API key, so it would need a separate client.

The Scalapay widget is client-side JavaScript and is not generated here. It authenticates with the **merchant token**, a public identifier from the Partner Portal — not the API key, which must never reach the browser.

## Running Tests

```bash
composer install
vendor/bin/phpunit
```

Continuous integration runs the same suite on PHP 8.2, 8.3, 8.4 and 8.5.

## Credits

- [Hermann Vallieri](https://github.com/hvallieri)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
