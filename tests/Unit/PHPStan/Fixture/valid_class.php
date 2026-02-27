<?php
declare(strict_types=1);

namespace PrestaShop\Module\APIResources\ApiPlatform\Resources\Stub;

use ApiPlatform\Metadata\ApiResource;
use PrestaShop\Decimal\DecimalNumber;

#[ApiResource]
class ValidResource
{
    public int $id;
    public string $name;
    public bool $enabled;
    public array $items;
    public ?int $optionalId;
    public ?string $optionalName;
    public ?bool $optionalBool;
    public ?array $optionalArray;
    public DecimalNumber $price;
    public ?DecimalNumber $optionalPrice;
    public \DateTimeImmutable $createdAt;
    public ?\DateTimeImmutable $updatedAt;
}
