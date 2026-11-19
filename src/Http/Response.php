<?php declare(strict_types=1);

namespace Hval\Scalapay\Http;

/**
 * A decoded API response.
 *
 * The body is not always an object: `GET /v2/payments/references` returns a top-level
 * array and a 401 returns a bare string. The decoded value is therefore `mixed`, and
 * the map form is obtained through toArray().
 */
final class Response
{
    /**
     * @param int $statusCode
     * @param array<string, list<string>> $headers header names already lower-cased
     * @param mixed $decoded
     * @param string $rawBody
     */
    public function __construct(
        private readonly int $statusCode,
        private readonly array $headers,
        private readonly mixed $decoded,
        private readonly string $rawBody,
    ) {
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getDecoded(): mixed
    {
        return $this->decoded;
    }

    /**
     * @return array<array-key, mixed>
     */
    public function toArray(): array
    {
        return is_array($this->decoded) ? $this->decoded : [];
    }

    /**
     * @return list<array<array-key, mixed>>
     */
    public function toList(): array
    {
        if (is_array($this->decoded) === false) {
            return [];
        }

        return array_values(array_filter($this->decoded, is_array(...)));
    }

    public function getRawBody(): string
    {
        return $this->rawBody;
    }

    public function getHeader(string $name): ?string
    {
        $values = $this->headers[strtolower($name)] ?? null;

        if ($values === null || $values === []) {
            return null;
        }

        return $values[0];
    }

    /**
     * True when the `Request-Idempotency` header is present, meaning the response was
     * served from the idempotency cache instead of being executed again. Lets callers
     * tell an operation that really happened from a replayed one.
     */
    public function isIdempotentReplay(): bool
    {
        return $this->getHeader('Request-Idempotency') === 'true';
    }
}
