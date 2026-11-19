<?php declare(strict_types=1);

namespace Hval\Scalapay;

use Hval\Scalapay\Exception\ScalapayException;

final class Config
{
    private readonly string $baseUri;

    public function __construct(
        private readonly string $apiKey,
        private readonly Environment $environment = Environment::SANDBOX,
        ?string $baseUri = null,
        private readonly string $userAgent = 'hval/scalapay-php',
    ) {
        if (trim($this->apiKey) === '') {
            throw new ScalapayException('The Scalapay API key cannot be empty.');
        }

        $this->baseUri = rtrim($baseUri ?? $this->environment->baseUri(), '/');
    }

    public static function sandbox(string $apiKey): self
    {
        return new self($apiKey, Environment::SANDBOX);
    }

    public static function production(string $apiKey): self
    {
        return new self($apiKey, Environment::PRODUCTION);
    }

    /**
     * Sandbox with the public test key: allows exercising the API without a merchant
     * account. Orders created this way are not private.
     */
    public static function sandboxWithTestKey(): self
    {
        return new self(Environment::SANDBOX_TEST_API_KEY, Environment::SANDBOX);
    }

    public function getApiKey(): string
    {
        return $this->apiKey;
    }

    public function getEnvironment(): Environment
    {
        return $this->environment;
    }

    public function getBaseUri(): string
    {
        return $this->baseUri;
    }

    public function getUserAgent(): string
    {
        return $this->userAgent;
    }

    public function uriFor(string $path): string
    {
        return $this->baseUri . '/' . ltrim($path, '/');
    }
}
