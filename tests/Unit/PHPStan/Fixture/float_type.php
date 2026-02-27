<?php
declare(strict_types=1);

namespace PrestaShop\Module\APIResources\ApiPlatform\Resources\Stub;

use ApiPlatform\Metadata\ApiResource;

#[ApiResource]
class FloatTypeResource
{
    public int $id;
    public float $rate;
    public ?float $optionalRate;
}
