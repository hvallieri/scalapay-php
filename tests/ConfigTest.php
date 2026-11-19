<?php declare(strict_types=1);

namespace Hval\Scalapay\Tests;

use Hval\Scalapay\Config;
use Hval\Scalapay\Environment;
use Hval\Scalapay\Exception\ScalapayException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(Config::class)]
#[UsesClass(Environment::class)]
final class ConfigTest extends TestCase
{
    public function testItDefaultsToTheSandboxEnvironmentAndItsBaseUri(): void
    {
        $config = new Config('a-key');

        self::assertSame('a-key', $config->getApiKey());
        self::assertSame(Environment::SANDBOX, $config->getEnvironment());
        self::assertSame(Environment::SANDBOX_BASE_URI, $config->getBaseUri());
        self::assertSame('hval/scalapay-php', $config->getUserAgent());
    }

    public function testItRejectsAnEmptyApiKey(): void
    {
        $this->expectException(ScalapayException::class);

        new Config('');
    }

    public function testItRejectsAnApiKeyMadeOfWhitespaceOnly(): void
    {
        $this->expectException(ScalapayException::class);

        new Config("  \t\n ");
    }

    public function testItKeepsAnApiKeyThatMerelySurroundsItselfWithSpaces(): void
    {
        $config = new Config(' a-key ');

        self::assertSame(' a-key ', $config->getApiKey());
    }

    public function testTheSandboxFactoryPairsTheKeyWithTheSandboxUri(): void
    {
        $config = Config::sandbox('a-key');

        self::assertSame(Environment::SANDBOX, $config->getEnvironment());
        self::assertSame(Environment::SANDBOX_BASE_URI, $config->getBaseUri());
        self::assertFalse($config->getEnvironment()->isProduction());
    }

    public function testTheProductionFactoryPairsTheKeyWithTheProductionUri(): void
    {
        $config = Config::production('a-key');

        self::assertSame(Environment::PRODUCTION, $config->getEnvironment());
        self::assertSame(Environment::PRODUCTION_BASE_URI, $config->getBaseUri());
        self::assertTrue($config->getEnvironment()->isProduction());
    }

    public function testTheTestKeyFactoryUsesThePublicKeyAgainstTheSandbox(): void
    {
        $config = Config::sandboxWithTestKey();

        self::assertSame(Environment::SANDBOX_TEST_API_KEY, $config->getApiKey());
        self::assertSame(Environment::SANDBOX, $config->getEnvironment());
    }

    public function testAnExplicitBaseUriOverridesTheEnvironmentOne(): void
    {
        $config = new Config('a-key', Environment::PRODUCTION, 'http://localhost:8080');

        self::assertSame(Environment::PRODUCTION, $config->getEnvironment());
        self::assertSame('http://localhost:8080', $config->getBaseUri());
    }

    public function testItStripsTrailingSlashesFromTheBaseUri(): void
    {
        $config = new Config('a-key', Environment::SANDBOX, 'http://localhost:8080///');

        self::assertSame('http://localhost:8080', $config->getBaseUri());
    }

    public function testUriForJoinsThePathWithASingleSlashHoweverItIsWritten(): void
    {
        $config = new Config('a-key', Environment::SANDBOX, 'http://localhost');

        self::assertSame('http://localhost/v2/orders', $config->uriFor('/v2/orders'));
        self::assertSame('http://localhost/v2/orders', $config->uriFor('v2/orders'));
        self::assertSame('http://localhost/v2/orders', $config->uriFor('///v2/orders'));
    }

    public function testUriForKeepsTheQueryStringUntouched(): void
    {
        $config = new Config('a-key', Environment::SANDBOX, 'http://localhost');

        self::assertSame('http://localhost/v2/orders?merchantReference=a%2Fb', $config->uriFor('/v2/orders?merchantReference=a%2Fb'));
    }

    public function testACustomUserAgentIsKept(): void
    {
        $config = new Config('a-key', Environment::SANDBOX, null, 'acme-shop/2.1');

        self::assertSame('acme-shop/2.1', $config->getUserAgent());
    }
}
