<?php declare(strict_types=1);

namespace Hval\Scalapay\Response;

use Hval\Scalapay\Http\Response;

/**
 * Result of `POST /v2/orders/{token}`.
 *
 * A reference is returned only when it was supplied in the request. Because omitting a
 * field clears it, an absent value here means "cleared", not "unchanged": the response
 * cannot distinguish the two, so do not use it to infer the stored state.
 */
final class OrderUpdated extends AbstractResource
{
    public static function fromResponse(Response $response): self
    {
        return new self(self::payloadOf($response), $response->isIdempotentReplay());
    }

    public function getToken(): ?string
    {
        return $this->string('token');
    }

    public function getMerchantReference(): ?string
    {
        return $this->string('merchantReference');
    }

    public function getMerchantProcessorReference(): ?string
    {
        return $this->string('merchantProcessorReference');
    }
}
