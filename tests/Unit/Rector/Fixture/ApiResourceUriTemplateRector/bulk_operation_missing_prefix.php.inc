<?php

namespace PrestaShop\Module\APIResources\ApiPlatform\Resources\Product;

use ApiPlatform\Metadata\ApiResource;
use PrestaShop\PrestaShop\Core\Domain\Product\Command\BulkDeleteProductCommand;

#[ApiResource(
    operations: [
        new CQRSDelete(
            uriTemplate: '/products/delete',
            CQRSCommand: BulkDeleteProductCommand::class
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
use PrestaShop\PrestaShop\Core\Domain\Product\Command\BulkDeleteProductCommand;

#[ApiResource(
    operations: [
        new CQRSDelete(
            uriTemplate: '/products/bulk-delete',
            CQRSCommand: BulkDeleteProductCommand::class
        ),
    ]
)]
class Product
{
}

?>
