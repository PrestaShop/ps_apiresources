<?php

namespace PrestaShop\Module\APIResources\ApiPlatform\Resources\Address;

use ApiPlatform\Metadata\ApiResource;
use PrestaShop\PrestaShop\Core\Domain\Address\Command\DeleteAddressCommand;

#[ApiResource(
    operations: [
        new CQRSDelete(
            uriTemplate: '/addresses/{addressId}',
            CQRSCommand: DeleteAddressCommand::class
        ),
    ]
)]
class Address
{
}
