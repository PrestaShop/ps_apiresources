<?php

namespace PrestaShop\Module\APIResources\ApiPlatform\Resources\Order;

use ApiPlatform\Metadata\ApiResource;

#[ApiResource(
    operations: [
        new CQRSGet(
            uriTemplate: '/orders/{orderId}/preview',
        ),
    ]
)]
class OrderPreview
{
}

?>
