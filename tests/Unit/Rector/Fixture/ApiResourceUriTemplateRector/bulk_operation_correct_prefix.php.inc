<?php

namespace PrestaShop\Module\APIResources\ApiPlatform\Resources\Category;

use ApiPlatform\Metadata\ApiResource;
use PrestaShop\PrestaShop\Core\Domain\Category\Command\BulkUpdateCategoryStatusCommand;

#[ApiResource(
    operations: [
        new CQRSPatch(
            uriTemplate: '/categories/bulk-toggle-status',
            CQRSCommand: BulkUpdateCategoryStatusCommand::class
        ),
    ]
)]
class Category
{
}
