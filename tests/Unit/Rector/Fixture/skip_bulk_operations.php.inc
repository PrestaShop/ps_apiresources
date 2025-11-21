<?php

namespace PrestaShop\Module\APIResources\ApiPlatform\Resources\Category;

use ApiPlatform\Metadata\ApiResource;

#[ApiResource(
    operations: [
        new CQRSDelete(
            uriTemplate: '/categories/bulk-delete',
        ),
        new CQRSUpdate(
            uriTemplate: '/categories/bulk-toggle-status',
        ),
    ]
)]
class BulkCategories
{
}

?>
