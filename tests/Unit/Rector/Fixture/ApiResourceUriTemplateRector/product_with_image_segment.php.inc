<?php

namespace PrestaShop\Module\APIResources\ApiPlatform\Resources\Product;

use ApiPlatform\Metadata\ApiResource;

#[ApiResource(
    operations: [
        new CQRSGet(
            uriTemplate: '/product/{productId}/image',
        ),
        new CQRSPartialUpdate(
            uriTemplate: '/product/{productId}/status',
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
            uriTemplate: '/products/{productId}/images',
        ),
        new CQRSPartialUpdate(
            uriTemplate: '/products/{productId}/status',
        ),
    ]
)]
class Product
{
}

?>
