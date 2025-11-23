<?php
/**
 * Copyright since 2007 PrestaShop SA and Contributors
 * PrestaShop is an International Registered Trademark & Property of PrestaShop SA
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License version 3.0
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/AFL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * @author    PrestaShop SA and Contributors <contact@prestashop.com>
 * @copyright Since 2007 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License version 3.0
 */

namespace PrestaShop\Module\APIResources\ApiPlatform\Resources\Zone;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use PrestaShop\PrestaShop\Core\Domain\AttributeGroup\Exception\ZoneNotFoundException;
use PrestaShop\PrestaShop\Core\Domain\Zone\Command\BulkToggleZoneStatusCommand;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSUpdate;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    operations: [
        new CQRSUpdate(
            uriTemplate: '/zones/bulk-update-status',
            output: false,
            CQRSCommand: BulkToggleZoneStatusCommand::class,
            CQRSCommandMapping: [
                '[enabled]' => '[expectedStatus]',
            ],
            scopes: [
                'zone_write',
            ],
        ),
    ],
    exceptionToStatus: [
        ZoneNotFoundException::class => Response::HTTP_NOT_FOUND,
    ],
)]
class BulkUpdateStatusZone
{
    /**
     * @var int[]
     */
    #[ApiProperty(openapiContext: ['type' => 'array', 'items' => ['type' => 'integer'], 'example' => [1, 3]])]
    #[Assert\NotBlank]
    public array $zoneIds;

    public bool $enabled;
}
