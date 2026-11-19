<?php declare(strict_types=1);

namespace Hval\Scalapay\Response;

use Hval\Scalapay\Http\Response;
use Hval\Scalapay\Status;

/**
 * Result of `GET /v2/payments/{token}`.
 *
 * The specification declares no response schema for this endpoint, only examples, so
 * every accessor here is nullable and the raw payload matters more than usual.
 *
 * Reading this before capturing is strongly recommended by the documentation: only
 * authorized orders can be captured.
 */
final class OrderDetails extends AbstractResource
{
    public static function fromResponse(Response $response): self
    {
        return new self(self::payloadOf($response), $response->isIdempotentReplay());
    }

    public function getToken(): ?string
    {
        return $this->string('token');
    }

    /**
     * Order status, lower-case. Do not assume the value is one of the known constants:
     * the documentation warns the list may grow.
     */
    public function getStatus(): ?string
    {
        return $this->string('status');
    }

    /**
     * Capture status, lower-case and independent of the order status.
     */
    public function getCaptureStatus(): ?string
    {
        return $this->string('captureStatus');
    }

    /**
     * The merchant reference, wherever the API decided to put it.
     *
     * Observed in the sandbox on 2026-10-01: this endpoint returns the reference nested
     * inside `orderDetails`, leaving the top-level property absent, while the examples in
     * the specification show it at the top level. Reading only the top level reported null
     * on an order that plainly carried one, so both places are consulted.
     */
    public function getMerchantReference(): ?string
    {
        $topLevel = $this->string('merchantReference');

        if ($topLevel !== null) {
            return $topLevel;
        }

        $nested = $this->nested('orderDetails')['merchantReference'] ?? null;

        return is_scalar($nested) ? (string) $nested : null;
    }

    /**
     * Why eligibility was declined, when it was.
     *
     * Undocumented beyond a single example: there is no list of possible values. Two have
     * been observed so far — `DECLINED_FRAUD` in the official example and
     * `DECLINED_PAYMENT_AUTHORIZATION` in the sandbox on 2026-10-01 — so never treat this
     * as a closed set.
     */
    public function getLastEligibilityFeedback(): ?string
    {
        return $this->string('lastEligibilityFeedback');
    }

    /**
     * @return array<array-key, mixed>|null
     */
    public function getOrderDetails(): ?array
    {
        return $this->nested('orderDetails');
    }

    public function isAuthorized(): bool
    {
        return $this->getStatus() === Status::ORDER_AUTHORIZED;
    }

    public function isCharged(): bool
    {
        return $this->getStatus() === Status::ORDER_CHARGED;
    }

    public function isRefunded(): bool
    {
        return $this->getStatus() === Status::ORDER_REFUNDED;
    }

    public function isExpired(): bool
    {
        return $this->getStatus() === Status::ORDER_EXPIRED;
    }

    public function isCaptured(): bool
    {
        return $this->getCaptureStatus() === Status::CAPTURE_CAPTURED;
    }
}
