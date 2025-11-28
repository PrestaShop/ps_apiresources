<?php

namespace PrestaShop\Module\APIResources\ApiPlatform\Resources\Product;

use ApiPlatform\Metadata\ApiResource;

#[ApiResource]
class Product
{
    public int $productId;
    public bool $isActive;
    public string $name;
}

?>
-----
<?php

namespace PrestaShop\Module\APIResources\ApiPlatform\Resources\Product;

use ApiPlatform\Metadata\ApiResource;

#[ApiResource]
class Product
{
    public int $productId;
    public bool $enabled;
    public string $name;
}

?>
