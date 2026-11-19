# Errors

All exceptions extend `ScalapayException`, which can be used as a catch-all.

| Exception                      | When                                                               |
|--------------------------------|--------------------------------------------------------------------|
| `InvalidRequestException`      | 400 — malformed or rejected request                                |
| `AuthenticationException`      | 401 — invalid API key, or wrong key for the URI                    |
| `ConflictException`            | 409 — another operation is in progress; retryable                  |
| `UnprocessableEntityException` | 422 — invalid token                                                |
| `ApiException`                 | any other non-2XX response                                         |
| `TransportException`           | network failure, undecodable response, or unencodable request body |
| `WebhookSignatureException`    | webhook verification failed                                        |

## API errors

Every HTTP error extends `ApiException`, which exposes what you need to act on and to report:

```php
use Hval\Scalapay\Exception\ApiException;

try {
    $scalapay->capture($token);
} catch (ApiException $exception) {
    $exception->getErrorCode();   // e.g. 'conflicting_operation_in_progress'
    $exception->getErrorId();     // quote this to Scalapay support
    $exception->getHttpStatus();
    $exception->isRetryable();    // true only for 409
    $exception->getRawBody();
}
```

Handle errors by `errorCode` and HTTP status, never by message: the documentation warns messages change over time. Treat `errorCode` as opaque — official examples show it holding both a semantic code and the HTTP status repeated as a string.

## Validation errors

`InvalidRequestException` additionally unpacks validation failures:

```php
use Hval\Scalapay\Exception\InvalidRequestException;

try {
    $scalapay->createOrder($order);
} catch (InvalidRequestException $exception) {
    $exception->getInvalidFields();     // ['totalAmount.amount']
    $exception->getValidationErrors();  // the full per-field detail
}
```
