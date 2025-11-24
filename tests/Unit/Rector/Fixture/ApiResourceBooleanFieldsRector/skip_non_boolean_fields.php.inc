<?php

namespace PrestaShop\Module\APIResources\ApiPlatform\Resources\Product;

use ApiPlatform\Metadata\ApiResource;

#[ApiResource]
class Product
{
    public int $productId;
    public string $active;
    public int $isEnabled;
    public bool $enabled;
}

?>
