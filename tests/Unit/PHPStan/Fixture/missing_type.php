<?php
declare(strict_types=1);

namespace PrestaShop\Module\APIResources\ApiPlatform\Resources\Stub;

use ApiPlatform\Metadata\ApiResource;

#[ApiResource]
class MissingTypeResource
{
    public int $id;
    public $name;
}
