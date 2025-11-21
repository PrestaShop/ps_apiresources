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

declare(strict_types=1);

namespace PrestaShop\Module\APIResources\ApiPlatform\Resources\Zone;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use PrestaShop\PrestaShop\Core\Domain\Zone\Command\AddZoneCommand;
use PrestaShop\PrestaShop\Core\Domain\Zone\Command\DeleteZoneCommand;
use PrestaShop\PrestaShop\Core\Domain\Zone\Command\EditZoneCommand;
use PrestaShop\PrestaShop\Core\Domain\Zone\Command\ToggleZoneStatusCommand;
use PrestaShop\PrestaShop\Core\Domain\Zone\Exception\ZoneNotFoundException;
use PrestaShop\PrestaShop\Core\Domain\Zone\Query\GetZoneForEditing;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSCreate;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSDelete;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSGet;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSUpdate;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    operations: [
        new CQRSCreate(
            uriTemplate: '/zones',
            validationContext: ['groups' => ['Default', 'Create']],
            CQRSCommand: AddZoneCommand::class,
            scopes: ['zone_write'],
            CQRSCommandMapping: self::COMMAND_MAPPING,
        ),
        new CQRSDelete(
            uriTemplate: '/zones/{zoneId}',
            requirements: ['zoneId' => '\d+'],
            output: false,
            CQRSCommand: DeleteZoneCommand::class,
            scopes: ['zone_write']
        ),
        new CQRSGet(
            uriTemplate: '/zones/{zoneId}',
            requirements: ['zoneId' => '\d+'],
            CQRSQuery: GetZoneForEditing::class,
            scopes: ['zone_read'],
            CQRSQueryMapping: self::QUERY_MAPPING,
        ),
        new CQRSUpdate(
            uriTemplate: '/zones/{zoneId}/toggle-status',
            requirements: ['zoneId' => '\d+'],
            output: false,
            allowEmptyBody: true,
            CQRSCommand: ToggleZoneStatusCommand::class,
            scopes: ['zone_write'],
        ),
        new CQRSUpdate(
            uriTemplate: '/zones/{zoneId}',
            requirements: ['zoneId' => '\d+'],
            read: false,
            CQRSCommand: EditZoneCommand::class,
            CQRSQuery: GetZoneForEditing::class,
            scopes: ['zone_write'],
            CQRSCommandMapping: self::COMMAND_MAPPING,
            CQRSQueryMapping: self::QUERY_MAPPING,
        ),
    ],
    normalizationContext: ['skip_null_values' => false],
    exceptionToStatus: [
        ZoneNotFoundException::class => Response::HTTP_NOT_FOUND,
    ],
)]
class Zone
{
    #[ApiProperty(identifier: true)]
    public int $zoneId;

    #[Assert\NotBlank(groups: ['Create'])]
    #[Assert\Length(min: 1, max: 64)]
    public string $name;

    #[Assert\NotNull(groups: ['Create'])]
    public bool $enabled;

    #[ApiProperty(openapiContext: ['type' => 'array', 'items' => ['type' => 'integer'], 'example' => [1, 3]])]
    #[Assert\NotBlank(allowNull: true)]
    public array $shopIds;

    public const COMMAND_MAPPING = [
        '[shopIds]' => '[shopAssociation]',
    ];

    public const QUERY_MAPPING = [
        '[associatedShops]' => '[shopIds]',
    ];
}
