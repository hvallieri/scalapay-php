<?php declare(strict_types=1);

namespace Hval\Scalapay\Model;

interface ModelInterface
{
    /**
     * @param array<array-key, mixed> $data
     *
     * @return self
     */
    public static function fromArray(array $data): self;

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array;
}
