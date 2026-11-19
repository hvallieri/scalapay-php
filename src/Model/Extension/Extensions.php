<?php declare(strict_types=1);

namespace Hval\Scalapay\Model\Extension;

use Hval\Scalapay\Model\ModelInterface;

/**
 * The `extensions` block of an order.
 *
 * The official specification is far narrower than the official SDK suggests: only
 * `industry.travel` and `type.link.notification` are documented. Fields the SDK exposes
 * but the specification omits (`risk`, `pluginDetails`, `checkoutAttemptDeclineCount`,
 * `shippingLat`/`shippingLng`, the detailed travel reservation blocks) are undocumented
 * and are not modelled here; use additional() if a merchant contract requires them.
 */
final class Extensions implements ModelInterface
{
    /**
     * @param TravelExtension|null $travel
     * @param LinkNotification|null $linkNotification
     * @param array<string, mixed> $additional undocumented extension properties, sent as they are
     */
    public function __construct(
        private readonly ?TravelExtension $travel = null,
        private readonly ?LinkNotification $linkNotification = null,
        private readonly array $additional = [],
    ) {
    }

    public static function fromArray(array $data): self
    {
        $travel = $data['industry']['travel'] ?? null;
        $notification = $data['type']['link']['notification'] ?? null;

        $additional = $data;
        unset($additional['industry'], $additional['type']);

        return new self(
            is_array($travel) ? TravelExtension::fromArray($travel) : null,
            is_array($notification) ? LinkNotification::fromArray($notification) : null,
            $additional
        );
    }

    public function getTravel(): ?TravelExtension
    {
        return $this->travel;
    }

    public function getLinkNotification(): ?LinkNotification
    {
        return $this->linkNotification;
    }

    /**
     * @return array<string, mixed>
     */
    public function getAdditional(): array
    {
        return $this->additional;
    }

    public function isEmpty(): bool
    {
        return $this->travel === null && $this->linkNotification === null && $this->additional === [];
    }

    public function toArray(): array
    {
        $payload = $this->additional;

        if ($this->travel !== null) {
            $payload['industry']['travel'] = $this->travel->toArray();
        }

        if ($this->linkNotification !== null) {
            $payload['type']['link']['notification'] = $this->linkNotification->toArray();
        }

        return $payload;
    }
}
