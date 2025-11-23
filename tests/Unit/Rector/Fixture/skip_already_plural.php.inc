<?php

namespace PrestaShop\Module\APIResources\ApiPlatform\Resources\Product;

use ApiPlatform\Metadata\ApiResource;

#[ApiResource(
    operations: [
        new CQRSDelete(
            uriTemplate: '/products/batch',
        ),
    ]
)]
class BulkProducts
{
}

?>
