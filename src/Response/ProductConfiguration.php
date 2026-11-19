<?php declare(strict_types=1);

namespace Hval\Scalapay\Response;

use Hval\Scalapay\Model\Amount;
use Hval\Scalapay\Model\Frequency;

/**
 * One `type`/`product` combination enabled on the merchant account, as returned by
 * `GET /v3/configurations`. See MerchantConfiguration.
 *
 * The limits arrive as JSON numbers here, not as strings like everywhere else in the API.
 */
final class ProductConfiguration extends AbstractResource
{
    /**
     * Reported by the configuration but rejected by `POST /v2/orders`, which is why it has
     * no counterpart on OrderRequest.
     */
    public const TYPE_OFFLINE_LINK = 'offline-link';

    public function getType(): ?string
    {
        return $this->string('type');
    }

    public function getProduct(): ?string
    {
        return $this->string('product');
    }

    /**
     * Present for every product, including those that must not send a frequency when
     * creating an order: it describes the product, it is not a value to echo back.
     */
    public function getFrequency(): ?Frequency
    {
        $frequency = $this->nested('frequency');

        return $frequency === null ? null : Frequency::fromArray($frequency);
    }

    public function isStackable(): ?bool
    {
        return $this->boolean('isStackable');
    }

    public function hasSplitFee(): ?bool
    {
        $value = $this->setting('splitFee');

        return is_bool($value) ? $value : null;
    }

    public function getMinimumAmount(): ?Amount
    {
        return $this->limit('minimumAmount');
    }

    public function getMaximumAmount(): ?Amount
    {
        return $this->limit('maximumAmount');
    }

    /**
     * Always empty in the sandbox, so the format of its entries has never been observed.
     *
     * @return list<string>
     */
    public function getBlockedUserCountries(): array
    {
        $countries = $this->setting('blockedUserCountries');

        if (is_array($countries) === false) {
            return [];
        }

        return array_values(array_map(static fn (int|float|string|bool $value): string => (string) $value, array_filter($countries, is_scalar(...))));
    }

    /**
     * Whether the amount falls within the limits of this combination, both bounds included.
     *
     * Inclusive bounds are an assumption, since the endpoint is undocumented. A missing
     * limit leaves that side unbounded; an amount in another currency is never accepted.
     */
    public function accepts(Amount $amount): bool
    {
        $minimum = $this->getMinimumAmount();
        $maximum = $this->getMaximumAmount();

        foreach ([$minimum, $maximum] as $limit) {
            if ($limit !== null && $limit->getCurrency() !== $amount->getCurrency()) {
                return false;
            }
        }

        return ($minimum === null || $amount->compareTo($minimum) >= 0)
            && ($maximum === null || $amount->compareTo($maximum) <= 0);
    }

    private function setting(string $key): mixed
    {
        return $this->nested('configuration')[$key] ?? null;
    }

    private function limit(string $key): ?Amount
    {
        $limit = $this->setting($key);

        return is_array($limit) ? Amount::fromArrayOrNull($limit) : null;
    }
}
