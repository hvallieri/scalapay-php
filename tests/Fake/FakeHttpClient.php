<?php declare(strict_types=1);

namespace Hval\Scalapay\Tests\Fake;

use Nyholm\Psr7\Response;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * A PSR-18 client that replays queued responses and records the requests it received.
 */
final class FakeHttpClient implements ClientInterface
{
    /** @var list<ResponseInterface> */
    private array $queue = [];

    /** @var list<RequestInterface> */
    private array $requests = [];

    /**
     * @param array<string, string> $headers
     */
    public function queue(int $statusCode, string $body, array $headers = []): self
    {
        $this->queue[] = new Response($statusCode, $headers, $body);

        return $this;
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $this->requests[] = $request;

        return array_shift($this->queue) ?? new Response(200, [], '{}');
    }

    public function getLastRequest(): ?RequestInterface
    {
        return $this->requests === [] ? null : $this->requests[count($this->requests) - 1];
    }

    /**
     * @return array<array-key, mixed>
     */
    public function getLastRequestBody(): array
    {
        $request = $this->getLastRequest();

        if ($request === null) {
            return [];
        }

        $decoded = json_decode((string) $request->getBody(), true);

        return is_array($decoded) ? $decoded : [];
    }

    public function countRequests(): int
    {
        return count($this->requests);
    }
}
