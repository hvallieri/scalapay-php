<?php declare(strict_types=1);

namespace Hval\Scalapay\Response;

use Hval\Scalapay\Exception\ScalapayException;
use Hval\Scalapay\Http\Response;

/**
 * Result of `POST /v2/orders`.
 *
 * Only `token` is required by the schema; `expires` and `checkoutUrl` are optional and
 * therefore nullable. For `pay-now-standalone` orders the checkout URI points at the
 * standalone path instead.
 */
final class OrderCreated extends AbstractResource
{
    public static function fromResponse(Response $response): self
    {
        $payload = self::payloadOf($response);

        if (isset($payload['token']) === false) {
            throw new ScalapayException('The Scalapay API did not return an order token.');
        }

        return new self($payload, $response->isIdempotentReplay());
    }

    public function getToken(): string
    {
        return (string) $this->string('token');
    }

    public function getCheckoutUrl(): ?string
    {
        return $this->string('checkoutUrl');
    }

    /**
     * Order expiry as returned by the API, in the `2024-11-08T13:06:17.858Z` form.
     */
    public function getExpires(): ?string
    {
        return $this->string('expires');
    }
}
