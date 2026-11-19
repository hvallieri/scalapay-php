<?php declare(strict_types=1);

namespace Hval\Scalapay\Response;

use Hval\Scalapay\Http\Response;
use Hval\Scalapay\Model\Amount;

/**
 * Result of `POST /v2/payments/{token}/refund`.
 *
 * Note that a refund may also be issued manually from the Scalapay Partner Portal, so a
 * `refunded` webhook does not imply this library ever called the endpoint.
 */
final class RefundResult extends AbstractResource
{
    public static function fromResponse(Response $response): self
    {
        return new self(self::payloadOf($response), $response->isIdempotentReplay());
    }

    public function getToken(): ?string
    {
        return $this->string('token');
    }

    public function getAmount(): ?Amount
    {
        return Amount::fromArrayOrNull($this->nested('amount'));
    }

    public function getMerchantReference(): ?string
    {
        return $this->string('merchantReference');
    }

    public function getMerchantRefundReference(): ?string
    {
        return $this->string('merchantRefundReference');
    }

    public function getRefundToken(): ?string
    {
        return $this->string('refundToken');
    }

    public function getRefundedAt(): ?string
    {
        return $this->string('refundedAt');
    }
}
