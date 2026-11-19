<?php declare(strict_types=1);

namespace Hval\Scalapay\Tests\Model;

use Hval\Scalapay\Model\Consumer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(Consumer::class)]
final class ConsumerTest extends TestCase
{
    public function testItExposesAndSerialisesTheFourRequiredFields(): void
    {
        $consumer = new Consumer('Mario', 'Rossi', 'test@scalapay.com', '+393001234567');

        self::assertSame('Mario', $consumer->getGivenNames());
        self::assertSame('Rossi', $consumer->getSurname());
        self::assertSame('test@scalapay.com', $consumer->getEmail());
        self::assertSame('+393001234567', $consumer->getPhoneNumber());
        self::assertSame([
            'givenNames' => 'Mario',
            'surname' => 'Rossi',
            'email' => 'test@scalapay.com',
            'phoneNumber' => '+393001234567',
        ], $consumer->toArray());
    }

    public function testFromArraySurvivesTheRoundTrip(): void
    {
        $payload = ['givenNames' => 'Mario', 'surname' => 'Rossi', 'email' => 'test@scalapay.com', 'phoneNumber' => '+393001234567'];

        self::assertSame($payload, Consumer::fromArray($payload)->toArray());
    }

    /**
     * No validation beyond the API's: a missing or malformed field becomes an empty string
     * and the API is left to reject it.
     */
    public function testFromArrayTurnsMissingOrNonScalarFieldsIntoEmptyStrings(): void
    {
        $consumer = Consumer::fromArray(['givenNames' => ['Mario'], 'phoneNumber' => 3001234567]);

        self::assertSame('', $consumer->getGivenNames());
        self::assertSame('', $consumer->getSurname());
        self::assertSame('', $consumer->getEmail());
        self::assertSame('3001234567', $consumer->getPhoneNumber());
    }
}
