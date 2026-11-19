<?php declare(strict_types=1);

namespace Hval\Scalapay\Response;

use Hval\Scalapay\Http\Response;
use Hval\Scalapay\Model\Amount;

/**
 * Result of `/delay` and `/capture`.
 *
 * The `status` here is the operation outcome, upper-case (`AUTHORIZED`, `APPROVED`), and
 * must not be compared with the lower-case order status. See Hval\Scalapay\Status.
 *
 * The two endpoints share this result but not all of its fields: `capturedAt` is
 * documented for `/capture` only, `authorizationExpiryAt` for `/delay` only.
 */
final class OperationResult extends AbstractResource
{
    public static function fromResponse(Response $response): self
    {
        return new self(self::payloadOf($response), $response->isIdempotentReplay());
    }

    public function getToken(): ?string
    {
        return $this->string('token');
    }

    public function getStatus(): ?string
    {
        return $this->string('status');
    }

    /**
     * Documented by both endpoints. On `/capture` it is the amount captured, which a
     * partial capture makes different from the order total.
     */
    public function getTotalAmount(): ?Amount
    {
        return Amount::fromArrayOrNull($this->nested('totalAmount'));
    }

    /**
     * When the capture was processed. `/capture` only.
     */
    public function getCapturedAt(): ?string
    {
        return $this->string('capturedAt');
    }

    /**
     * When the authorization lapses if no capture follows. `/delay` only.
     */
    public function getAuthorizationExpiryAt(): ?string
    {
        return $this->string('authorizationExpiryAt');
    }

    /**
     * Echo of the order details, when the endpoint returns them.
     *
     * @return array<array-key, mixed>|null
     */
    public function getOrderDetails(): ?array
    {
        return $this->nested('orderDetails');
    }
}
