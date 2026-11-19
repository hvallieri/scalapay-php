<?php declare(strict_types=1);

namespace Hval\Scalapay\Tests\Model;

use Hval\Scalapay\Model\Address;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(Address::class)]
final class AddressTest extends TestCase
{
    public function testItExposesAndSerialisesAllSixFields(): void
    {
        $address = new Address('Mario Rossi', 'Via Roma 1', 'Milano', '20100', 'IT', '+393001234567');

        self::assertSame('Mario Rossi', $address->getName());
        self::assertSame('Via Roma 1', $address->getLine1());
        self::assertSame('Milano', $address->getSuburb());
        self::assertSame('20100', $address->getPostcode());
        self::assertSame('IT', $address->getCountryCode());
        self::assertSame('+393001234567', $address->getPhoneNumber());
        self::assertSame([
            'name' => 'Mario Rossi',
            'line1' => 'Via Roma 1',
            'suburb' => 'Milano',
            'postcode' => '20100',
            'countryCode' => 'IT',
            'phoneNumber' => '+393001234567',
        ], $address->toArray());
    }

    public function testToArrayOmitsTheFieldsThatWereNotGiven(): void
    {
        $address = new Address('Mario Rossi', countryCode: 'IT');

        self::assertSame(['name' => 'Mario Rossi', 'countryCode' => 'IT'], $address->toArray());
    }

    public function testAnEmptyAddressHasNoFieldAtAll(): void
    {
        $address = Address::empty();

        self::assertNull($address->getName());
        self::assertNull($address->getLine1());
        self::assertNull($address->getSuburb());
        self::assertNull($address->getPostcode());
        self::assertNull($address->getCountryCode());
        self::assertNull($address->getPhoneNumber());
        self::assertSame([], $address->toArray());
    }

    public function testFromArraySurvivesTheRoundTrip(): void
    {
        $payload = [
            'name' => 'Mario Rossi',
            'line1' => 'Via Roma 1',
            'suburb' => 'Milano',
            'postcode' => '20100',
            'countryCode' => 'IT',
            'phoneNumber' => '+393001234567',
        ];

        self::assertSame($payload, Address::fromArray($payload)->toArray());
    }

    public function testFromArrayCastsScalarsAndDropsTheRest(): void
    {
        $address = Address::fromArray(['name' => ['Mario'], 'postcode' => 20100, 'countryCode' => 'IT']);

        self::assertNull($address->getName());
        self::assertSame('20100', $address->getPostcode());
        self::assertSame(['postcode' => '20100', 'countryCode' => 'IT'], $address->toArray());
    }
}
