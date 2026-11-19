<?php declare(strict_types=1);

namespace Hval\Scalapay\Response;

use Hval\Scalapay\Http\Response;

/**
 * Base for every API resource.
 *
 * Across the whole surface only `token` is ever guaranteed, so every other accessor is
 * nullable. The raw payload is always kept: responses carry keys that are not declared
 * in any schema, and discarding them would lose information.
 */
abstract class AbstractResource
{
    /**
     * @param array<array-key, mixed> $payload
     */
    public function __construct(
        private readonly array $payload,
        private readonly bool $idempotentReplay = false,
    ) {
    }

    /**
     * Whether the response was served from the idempotency cache rather than executed.
     */
    public function isIdempotentReplay(): bool
    {
        return $this->idempotentReplay;
    }

    /**
     * @return array<array-key, mixed>
     */
    public function getRawPayload(): array
    {
        return $this->payload;
    }

    protected function string(string $key): ?string
    {
        $value = $this->payload[$key] ?? null;

        return is_scalar($value) ? (string) $value : null;
    }

    protected function boolean(string $key): ?bool
    {
        $value = $this->payload[$key] ?? null;

        return is_bool($value) ? $value : null;
    }

    /**
     * @param string $key
     *
     * @return array<array-key, mixed>|null
     */
    protected function nested(string $key): ?array
    {
        $value = $this->payload[$key] ?? null;

        return is_array($value) ? $value : null;
    }

    /**
     * @param Response $response
     *
     * @return array<array-key, mixed>
     */
    protected static function payloadOf(Response $response): array
    {
        return $response->toArray();
    }
}
