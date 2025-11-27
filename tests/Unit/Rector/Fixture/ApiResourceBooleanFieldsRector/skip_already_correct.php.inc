<?php

namespace PrestaShop\Module\APIResources\ApiPlatform\Resources\Store;

use ApiPlatform\Metadata\ApiResource;

#[ApiResource]
class Store
{
    public int $storeId;
    public bool $enabled;
    public string $name;
}

?>
