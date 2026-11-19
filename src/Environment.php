<?php declare(strict_types=1);

namespace Hval\Scalapay;

/**
 * API key and base URI always change together: the documentation lists using the key
 * of one environment against the URI of the other among the causes of failure.
 */
enum Environment: string
{
    case SANDBOX = 'sandbox';
    case PRODUCTION = 'production';

    public const SANDBOX_BASE_URI = 'https://integration.api.scalapay.com';
    public const PRODUCTION_BASE_URI = 'https://api.scalapay.com';

    /**
     * Public test key documented in reference/api-simulator, valid for online calls
     * against the sandbox.
     */
    public const SANDBOX_TEST_API_KEY = 'qhtfs87hjnc12kkos';

    public function baseUri(): string
    {
        return match ($this) {
            self::SANDBOX => self::SANDBOX_BASE_URI,
            self::PRODUCTION => self::PRODUCTION_BASE_URI,
        };
    }

    public function isProduction(): bool
    {
        return $this === self::PRODUCTION;
    }
}
