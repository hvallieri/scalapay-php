# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.1] - 2026-11-23

### Fixed

- `Amount` rejects strings that are not decimal numbers on construction, instead of failing later in `compareTo()` or at the API

## [1.0.0] - 2026-11-19

First release.

### Added

- `ScalapayClient` for the Order and Refund APIs: `createOrder()`, `getOrderDetails()`, `getOrdersByReference()`, `updateOrderReferences()`, `delayCapture()`, `capture()`, `voidOrder()` and `refund()`, plus `getMerchantConfiguration()` for the undocumented `GET /v3/configurations`
- `Config` and the `Environment` enum, binding the API key and the base URI together; `Config::sandboxWithTestKey()` uses the published sandbox key
- PSR-18 transport with PSR-17 factories, and `Idempotency-Key` support on every write operation; replayed responses are detectable through `isIdempotentReplay()`
- Request models: `OrderRequest`, `Amount` (a decimal string, with `compareTo()`), `Address`, `Consumer`, `Item`, `Discount`, `Merchant`, `Frequency`, and extensions for travel dates and pay-by-link notifications
- Response models preserving the raw payload; `MerchantConfiguration::supports()` checks an amount against the account limits
- `Status`, with the known values of the three status axes as constants rather than enums
- An exception hierarchy rooted in `ScalapayException`: every HTTP error extends `ApiException` (error code, error id, retryability, raw body), with dedicated exceptions for 400, 401, 409 and 422, plus `TransportException` and `WebhookSignatureException`
- `WebhookHandler`, verifying the HMAC-SHA256 signature over the raw request body with an opt-in timestamp tolerance, and `WebhookPayload`
- CI on PHP 8.2 to 8.5 and a release workflow
