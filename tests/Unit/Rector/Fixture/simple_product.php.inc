<?php

namespace PrestaShop\Module\APIResources\ApiPlatform\Resources\Product;

use ApiPlatform\Metadata\ApiResource;

#[ApiResource(
    operations: [
        new CQRSGet(
            uriTemplate: '/product/{productId}',
        ),
        new CQRSCreate(
            uriTemplate: '/product',
        ),
    ]
)]
class Product
{
}

?>
-----
<?php

namespace PrestaShop\Module\APIResources\ApiPlatform\Resources\Product;

use ApiPlatform\Metadata\ApiResource;

#[ApiResource(
    operations: [
        new CQRSGet(
            uriTemplate: '/products/{productId}',
        ),
        new CQRSCreate(
            uriTemplate: '/products',
        ),
    ]
)]
class Product
{
}

?>
