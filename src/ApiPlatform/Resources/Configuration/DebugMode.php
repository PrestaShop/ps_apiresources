<?php
declare(strict_types=1);

namespace PrestaShop\Module\APIResources\ApiPlatform\Resources\Configuration;

use ApiPlatform\Metadata\ApiResource;
use PrestaShop\PrestaShop\Core\Domain\Configuration\Command\SwitchDebugModeCommand;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSUpdate;

#[ApiResource(
    operations: [
        new CQRSUpdate(
            uriTemplate: '/debug/toggle',
            CQRSCommand: SwitchDebugModeCommand::class,
            scopes: ['debug_mode_write']
        ),
    ],
)]
class DebugMode
{
    public string $enableDebugMode;
}
