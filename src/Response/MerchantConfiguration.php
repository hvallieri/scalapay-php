<?php declare(strict_types=1);

namespace Hval\Scalapay\Response;

use Hval\Scalapay\Http\Response;
use Hval\Scalapay\Model\Amount;

/**
 * Result of `GET /v3/configurations`: the `type`/`product` combinations enabled on the
 * merchant account, each with its frequency and amount limits.
 *
 * **Undocumented endpoint.** It is absent from the official API reference, yet the
 * official SDK calls it and the sandbox answered it on 2026-11-18. Scalapay makes no
 * stability commitment for it.
 *
 * The values reported here are not all valid for order creation: type `offline-link`
 * appears in the configuration but `POST /v2/orders` rejects it.
 */
final class MerchantConfiguration extends AbstractResource
{
    public static function fromResponse(Response $response): self
    {
        return new self(self::payloadOf($response), $response->isIdempotentReplay());
    }

    /**
     * @return list<ProductConfiguration>
     */
    public function all(): array
    {
        return array_map(
            static fn (array $entry): ProductConfiguration => new ProductConfiguration($entry),
            array_values(array_filter($this->getRawPayload(), is_array(...)))
        );
    }

    public function find(string $type, string $product): ?ProductConfiguration
    {
        foreach ($this->all() as $configuration) {
            if ($type === $configuration->getType() && $product === $configuration->getProduct()) {
                return $configuration;
            }
        }

        return null;
    }

    /**
     * Whether the combination is enabled and the amount falls within its limits — the
     * check to run before offering Scalapay at checkout. See ProductConfiguration::accepts().
     */
    public function supports(string $type, string $product, Amount $amount): bool
    {
        return $this->find($type, $product)?->accepts($amount) === true;
    }
}
