<?php declare(strict_types=1);

namespace Hval\Scalapay\Response;

use Hval\Scalapay\Http\Response;

/**
 * Result of `POST /v2/payments/{token}/void`.
 *
 * Unlike `/delay` and `/capture` this endpoint returns no status field: a successful void
 * is inferred from the 200 and from `voidedAt`.
 */
final class VoidResult extends AbstractResource
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

    public function getVoidedAt(): ?string
    {
        return $this->string('voidedAt');
    }
}
