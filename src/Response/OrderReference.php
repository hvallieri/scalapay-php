<?php declare(strict_types=1);

namespace Hval\Scalapay\Response;

use Hval\Scalapay\Http\Response;

/**
 * An entry of `GET /v2/payments/references`.
 *
 * That endpoint returns a top-level JSON array with no envelope, declares no required
 * field — not even `orderToken` — and caps results at 100 with no paging parameter and no
 * flag signalling truncation. See ScalapayClient::getOrdersByReference().
 *
 * Mind the naming: what the rest of the API calls `merchantReference` is
 * `merchantOrderReference` here.
 */
final class OrderReference extends AbstractResource
{
    /**
     * @return list<self>
     */
    public static function listFromResponse(Response $response): array
    {
        return array_map(
            static fn (array $item): self => new self($item),
            $response->toList()
        );
    }

    public function getOrderToken(): ?string
    {
        return $this->string('orderToken');
    }

    /**
     * The merchant reference. Named `merchantOrderReference` on this endpoint only.
     */
    public function getMerchantOrderReference(): ?string
    {
        return $this->string('merchantOrderReference');
    }

    /**
     * Writable only through `POST /v2/orders/{token}`, readable only here: it appears
     * neither in the order details nor in webhooks. Its purpose is undocumented.
     */
    public function getMerchantProcessorReference(): ?string
    {
        return $this->string('merchantProcessorReference');
    }

    /**
     * Creation timestamp, the only field of the surface declaring `format: date-time`.
     */
    public function getCreatedAt(): ?string
    {
        return $this->string('createdAt');
    }
}
