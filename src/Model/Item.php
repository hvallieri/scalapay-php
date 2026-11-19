<?php declare(strict_types=1);

namespace Hval\Scalapay\Model;

/**
 * An order line item. `category`, `name`, `price`, `quantity` and `sku` are required.
 * The optional fields exposed here are documented in the specification but missing from
 * the official SDK.
 */
final class Item implements ModelInterface
{
    /**
     * @param list<string> $subcategory
     */
    public function __construct(
        private readonly string $name,
        private readonly string $sku,
        private readonly string $category,
        private readonly Amount $price,
        private readonly int $quantity = 1,
        private readonly array $subcategory = [],
        private readonly ?string $brand = null,
        private readonly ?string $gtin = null,
        private readonly ?string $pageUrl = null,
        private readonly ?string $imageUrl = null,
    ) {
    }

    public static function fromArray(array $data): self
    {
        $price = $data['price'] ?? null;
        $subcategory = $data['subcategory'] ?? [];

        return new self(
            self::stringValue($data['name'] ?? null),
            self::stringValue($data['sku'] ?? null),
            self::stringValue($data['category'] ?? null),
            is_array($price) ? Amount::fromArray($price) : new Amount('0.00'),
            is_numeric($data['quantity'] ?? null) ? (int) $data['quantity'] : 1,
            is_array($subcategory) ? array_values(array_map(static fn (int|float|string|bool $value): string => (string) $value, array_filter($subcategory, is_scalar(...)))) : [],
            self::stringOrNull($data['brand'] ?? null),
            self::stringOrNull($data['gtin'] ?? null),
            self::stringOrNull($data['pageUrl'] ?? null),
            self::stringOrNull($data['imageUrl'] ?? null)
        );
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getSku(): string
    {
        return $this->sku;
    }

    public function getCategory(): string
    {
        return $this->category;
    }

    public function getPrice(): Amount
    {
        return $this->price;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    /**
     * @return list<string>
     */
    public function getSubcategory(): array
    {
        return $this->subcategory;
    }

    public function getBrand(): ?string
    {
        return $this->brand;
    }

    public function getGtin(): ?string
    {
        return $this->gtin;
    }

    public function getPageUrl(): ?string
    {
        return $this->pageUrl;
    }

    public function getImageUrl(): ?string
    {
        return $this->imageUrl;
    }

    public function toArray(): array
    {
        $payload = [
            'name' => $this->name,
            'sku' => $this->sku,
            'category' => $this->category,
            'price' => $this->price->toArray(),
            'quantity' => $this->quantity,
        ];

        if ($this->subcategory !== []) {
            $payload['subcategory'] = $this->subcategory;
        }

        foreach (['brand' => $this->brand, 'gtin' => $this->gtin, 'pageUrl' => $this->pageUrl, 'imageUrl' => $this->imageUrl] as $key => $value) {
            if ($value !== null) {
                $payload[$key] = $value;
            }
        }

        return $payload;
    }

    private static function stringValue(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }

    private static function stringOrNull(mixed $value): ?string
    {
        return is_scalar($value) ? (string) $value : null;
    }
}
