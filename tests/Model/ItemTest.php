<?php declare(strict_types=1);

namespace Hval\Scalapay\Tests\Model;

use Hval\Scalapay\Model\Amount;
use Hval\Scalapay\Model\Item;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(Item::class)]
#[UsesClass(Amount::class)]
final class ItemTest extends TestCase
{
    public function testOnlyTheRequiredFieldsAreSentByDefault(): void
    {
        $item = new Item('T-Shirt', '12341234', 'clothes', new Amount('10.00'));

        self::assertSame([
            'name' => 'T-Shirt',
            'sku' => '12341234',
            'category' => 'clothes',
            'price' => ['amount' => '10.00', 'currency' => 'EUR'],
            'quantity' => 1,
        ], $item->toArray());
        self::assertSame([], $item->getSubcategory());
        self::assertNull($item->getBrand());
        self::assertNull($item->getGtin());
        self::assertNull($item->getPageUrl());
        self::assertNull($item->getImageUrl());
    }

    public function testEveryOptionalFieldIsExposedAndSent(): void
    {
        $price = new Amount('10.00');
        $item = new Item(
            'T-Shirt',
            '12341234',
            'clothes',
            $price,
            2,
            ['shirts', 'long-sleeve'],
            'TopChoice',
            '123458791330',
            'https://shop.example/t-shirt',
            'https://shop.example/t-shirt.jpg',
        );

        self::assertSame('T-Shirt', $item->getName());
        self::assertSame('12341234', $item->getSku());
        self::assertSame('clothes', $item->getCategory());
        self::assertSame($price, $item->getPrice());
        self::assertSame(2, $item->getQuantity());
        self::assertSame(['shirts', 'long-sleeve'], $item->getSubcategory());
        self::assertSame('TopChoice', $item->getBrand());
        self::assertSame('123458791330', $item->getGtin());
        self::assertSame('https://shop.example/t-shirt', $item->getPageUrl());
        self::assertSame('https://shop.example/t-shirt.jpg', $item->getImageUrl());

        $payload = $item->toArray();

        self::assertSame(['shirts', 'long-sleeve'], $payload['subcategory']);
        self::assertSame('TopChoice', $payload['brand']);
        self::assertSame('123458791330', $payload['gtin']);
        self::assertSame('https://shop.example/t-shirt', $payload['pageUrl']);
        self::assertSame('https://shop.example/t-shirt.jpg', $payload['imageUrl']);
    }

    public function testFromArraySurvivesTheRoundTrip(): void
    {
        $payload = [
            'name' => 'T-Shirt',
            'sku' => '12341234',
            'category' => 'clothes',
            'price' => ['amount' => '10.00', 'currency' => 'EUR'],
            'quantity' => 2,
            'subcategory' => ['shirts', 'long-sleeve'],
            'brand' => 'TopChoice',
            'gtin' => '123458791330',
            'pageUrl' => 'https://shop.example/t-shirt',
            'imageUrl' => 'https://shop.example/t-shirt.jpg',
        ];

        self::assertSame($payload, Item::fromArray($payload)->toArray());
    }

    public function testFromArrayFallsBackToDefaultsOnAnEmptyPayload(): void
    {
        self::assertSame([
            'name' => '',
            'sku' => '',
            'category' => '',
            'price' => ['amount' => '0.00', 'currency' => 'EUR'],
            'quantity' => 1,
        ], Item::fromArray([])->toArray());
    }

    public function testFromArrayReadsANumericStringQuantityAndIgnoresAnyOtherString(): void
    {
        self::assertSame(2, Item::fromArray(['quantity' => '2'])->getQuantity());
        self::assertSame(1, Item::fromArray(['quantity' => 'many'])->getQuantity());
    }

    public function testFromArrayKeepsOnlyScalarSubcategories(): void
    {
        self::assertSame(['shirts', '3'], Item::fromArray(['subcategory' => ['shirts', ['nested'], 3]])->getSubcategory());
        self::assertSame([], Item::fromArray(['subcategory' => 'shirts'])->getSubcategory());
    }

    /**
     * No validation beyond the API's: a zero or negative quantity is sent as it is.
     */
    public function testTheQuantityIsNotValidated(): void
    {
        self::assertSame(0, (new Item('T-Shirt', 'SKU', 'clothes', new Amount('10.00'), 0))->toArray()['quantity']);
        self::assertSame(-1, (new Item('T-Shirt', 'SKU', 'clothes', new Amount('10.00'), -1))->toArray()['quantity']);
    }
}
