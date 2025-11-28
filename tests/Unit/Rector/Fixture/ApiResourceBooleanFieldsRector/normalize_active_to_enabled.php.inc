<?php

namespace PrestaShop\Module\APIResources\ApiPlatform\Resources\Category;

use ApiPlatform\Metadata\ApiResource;

#[ApiResource]
class Category
{
    public int $categoryId;
    public bool $active;
    public string $name;
}

?>
-----
<?php

namespace PrestaShop\Module\APIResources\ApiPlatform\Resources\Category;

use ApiPlatform\Metadata\ApiResource;

#[ApiResource]
class Category
{
    public int $categoryId;
    public bool $enabled;
    public string $name;
}

?>
