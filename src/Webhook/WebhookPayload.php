<?php declare(strict_types=1);

namespace Hval\Scalapay\Webhook;

use Hval\Scalapay\Status;

/**
 * A webhook notification.
 *
 * Four keys are always present: `totalAmount`, `status`, `orderToken` and
 * `merchantReference`. Depending on the status further keys may arrive — `created`
 * carries a whole `orderDetails` object — so the raw payload is always kept.
 *
 * Webhooks are the authoritative channel on payment outcomes, and a notification may
 * arrive for an operation this library never performed: refunds and pay-by-link orders
 * can also be issued from the Partner Portal.
 */
final class WebhookPayload
{
    /**
     * @param array<array-key, mixed> $payload
     * @param string $rawBody
     */
    public function __construct(
        private readonly array $payload,
        private readonly string $rawBody,
    ) {
    }

    public function getOrderToken(): ?string
    {
        return $this->stringOrNull('orderToken');
    }

    public function getMerchantReference(): ?string
    {
        return $this->stringOrNull('merchantReference');
    }

    /**
     * Current payment status, lower-case.
     *
     * Deliberately a string and not an enum: the documentation explicitly advises against
     * hard-coding the expected set because it may change. Compare against the Status
     * constants, but handle unknown values instead of rejecting them.
     */
    public function getStatus(): ?string
    {
        return $this->stringOrNull('status');
    }

    /**
     * The total amount exactly as it appears in the raw JSON.
     *
     * The top-level `totalAmount` of a webhook is the one place in the whole API where an
     * amount is a JSON number rather than a string, so decoding it yields a float and
     * loses trailing zeros: `40.70` becomes `40.7`. This accessor reads the literal from
     * the raw body instead, preserving the digits as sent.
     *
     * Beware: this value carries no currency. The currency only appears inside
     * `orderDetails`, which only the `created` notification includes.
     *
     * Limitation: the literal is the first `"totalAmount"` followed by a number anywhere
     * in the body. Nested ones are objects today (`orderDetails.totalAmount`) and are
     * skipped; should a nested numeric one ever precede the top-level key, it would be
     * read instead.
     */
    public function getTotalAmountAsString(): ?string
    {
        if (preg_match('/"totalAmount"\s*:\s*(-?\d+(?:\.\d+)?)/', $this->rawBody, $matches) === 1) {
            return $matches[1];
        }

        $value = $this->payload['totalAmount'] ?? null;

        return is_scalar($value) ? (string) $value : null;
    }

    public function getTotalAmount(): int|float|null
    {
        $value = $this->payload['totalAmount'] ?? null;

        return is_int($value) || is_float($value) ? $value : null;
    }

    /**
     * Present on `created` notifications only. It echoes the order creation body.
     *
     * @return array<array-key, mixed>|null
     */
    public function getOrderDetails(): ?array
    {
        $value = $this->payload['orderDetails'] ?? null;

        return is_array($value) ? $value : null;
    }

    /**
     * @return array<array-key, mixed>
     */
    public function getRawPayload(): array
    {
        return $this->payload;
    }

    public function getRawBody(): string
    {
        return $this->rawBody;
    }

    public function isCreated(): bool
    {
        return $this->getStatus() === Status::ORDER_CREATED;
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

    private function stringOrNull(string $key): ?string
    {
        $value = $this->payload[$key] ?? null;

        return is_scalar($value) ? (string) $value : null;
    }
}
