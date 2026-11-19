<?php declare(strict_types=1);

namespace Hval\Scalapay\Http;

use Hval\Scalapay\Config;
use Hval\Scalapay\Exception\ApiException;
use Hval\Scalapay\Exception\AuthenticationException;
use Hval\Scalapay\Exception\ConflictException;
use Hval\Scalapay\Exception\InvalidRequestException;
use Hval\Scalapay\Exception\TransportException;
use Hval\Scalapay\Exception\UnprocessableEntityException;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

final class Transport
{
    public const HEADER_IDEMPOTENCY_KEY = 'Idempotency-Key';
    public const HEADER_REQUEST_IDEMPOTENCY = 'Request-Idempotency';

    /**
     * @param Config $config
     * @param ClientInterface $httpClient
     * @param RequestFactoryInterface&StreamFactoryInterface $httpFactory a PSR-17 factory providing both
     */
    public function __construct(
        private readonly Config $config,
        private readonly ClientInterface $httpClient,
        private readonly RequestFactoryInterface&StreamFactoryInterface $httpFactory,
    ) {
    }

    /**
     * @param string $path
     * @param array<string, scalar|null> $query
     *
     * @return Response
     */
    public function get(string $path, array $query = []): Response
    {
        return $this->send('GET', $this->appendQuery($path, $query), null, null);
    }

    /**
     * @param string $path
     * @param array<string, mixed>|null $body
     * @param string|null $idempotencyKey
     *
     * @return Response
     */
    public function post(string $path, ?array $body = null, ?string $idempotencyKey = null): Response
    {
        return $this->send('POST', $path, $body, $idempotencyKey);
    }

    /**
     * @param string $method
     * @param string $path
     * @param array<string, mixed>|null $body
     * @param string|null $idempotencyKey
     *
     * @return Response
     */
    private function send(string $method, string $path, ?array $body, ?string $idempotencyKey): Response
    {
        $request = $this->httpFactory
            ->createRequest($method, $this->config->uriFor($path))
            ->withHeader('Authorization', 'Bearer ' . $this->config->getApiKey())
            ->withHeader('Accept', 'application/json')
            ->withHeader('User-Agent', $this->config->getUserAgent())
        ;

        if ($idempotencyKey !== null && $idempotencyKey !== '') {
            $request = $request->withHeader(self::HEADER_IDEMPOTENCY_KEY, $idempotencyKey);
        }

        if ($body !== null) {
            try {
                // An empty PHP array encodes as `[]`, but every request body of the API is
                // a JSON object: an empty one must stay `{}`.
                $encoded = json_encode(
                    $body === [] ? new \stdClass() : $body,
                    JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
                );
            } catch (\JsonException $exception) {
                throw new TransportException('Unable to encode the request body as JSON.', 0, $exception);
            }

            $request = $request
                ->withHeader('Content-Type', 'application/json')
                ->withBody($this->httpFactory->createStream($encoded))
            ;
        }

        try {
            $response = $this->httpClient->sendRequest($request);
        } catch (ClientExceptionInterface $exception) {
            throw new TransportException(sprintf('The request to %s failed: %s', $path, $exception->getMessage()), 0, $exception);
        }

        $rawBody = (string) $response->getBody();
        $statusCode = $response->getStatusCode();

        $normalised = new Response($statusCode, $this->normaliseHeaders($response->getHeaders()), $this->decode($rawBody, $statusCode), $rawBody);

        if ($statusCode < 200 || $statusCode > 299) {
            throw $this->mapError($normalised);
        }

        return $normalised;
    }

    private function decode(string $rawBody, int $statusCode): mixed
    {
        if (trim($rawBody) === '') {
            return null;
        }

        try {
            return json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            // An undecodable error body must not mask the HTTP status: the raw shape
            // remains available through getRawBody().
            if ($statusCode < 200 || $statusCode > 299) {
                return null;
            }

            throw new TransportException('The Scalapay API returned a body that is not valid JSON.', 0, $exception);
        }
    }

    private function mapError(Response $response): ApiException
    {
        $decoded = $response->getDecoded();
        $payload = is_array($decoded) ? $decoded : [];

        // A 401 answers with the bare JSON string "Unauthorized": keep it as the message
        // instead of discarding it.
        if (is_string($decoded) === true) {
            $payload = ['message' => $decoded];
        }

        $statusCode = $response->getStatusCode();
        $rawBody = $response->getRawBody();

        return match ($statusCode) {
            400 => InvalidRequestException::fromResponse($statusCode, $payload, $rawBody),
            401, 403 => AuthenticationException::fromResponse($statusCode, $payload, $rawBody),
            409 => ConflictException::fromResponse($statusCode, $payload, $rawBody),
            422 => UnprocessableEntityException::fromResponse($statusCode, $payload, $rawBody),
            default => ApiException::fromResponse($statusCode, $payload, $rawBody),
        };
    }

    /**
     * @param string $path
     * @param array<string, scalar|null> $query
     *
     * @return string
     */
    private function appendQuery(string $path, array $query): string
    {
        $filtered = array_filter($query, static fn ($value): bool => $value !== null && $value !== '');

        if ($filtered === []) {
            return $path;
        }

        return $path . '?' . http_build_query($filtered, '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * @param array<string, list<string>> $headers
     *
     * @return array<string, list<string>>
     */
    private function normaliseHeaders(array $headers): array
    {
        $normalised = [];

        foreach ($headers as $name => $values) {
            $normalised[strtolower($name)] = array_values(
                array_map(static fn (int|float|string|bool $value): string => (string) $value, $values)
            );
        }

        return $normalised;
    }
}
