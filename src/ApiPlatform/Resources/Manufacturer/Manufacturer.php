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
 * @author    Pascal Cescon <pascal.cescon@gmail.com>
 * @copyright Since 2007 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License version 3.0
 */

declare(strict_types=1);

namespace PrestaShop\Module\APIResources\ApiPlatform\Resources\Manufacturer;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use PrestaShop\PrestaShop\Core\Domain\Manufacturer\Command\AddManufacturerCommand;
use PrestaShop\PrestaShop\Core\Domain\Manufacturer\Command\DeleteManufacturerCommand;
use PrestaShop\PrestaShop\Core\Domain\Manufacturer\Command\EditManufacturerCommand;
use PrestaShop\PrestaShop\Core\Domain\Manufacturer\Command\ToggleManufacturerStatusCommand;
use PrestaShop\PrestaShop\Core\Domain\Manufacturer\Exception\DeleteManufacturerException;
use PrestaShop\PrestaShop\Core\Domain\Manufacturer\Exception\ManufacturerConstraintException;
use PrestaShop\PrestaShop\Core\Domain\Manufacturer\Exception\ManufacturerNotFoundException;
use PrestaShop\PrestaShop\Core\Domain\Manufacturer\Exception\UpdateManufacturerException;
use PrestaShop\PrestaShop\Core\Domain\Manufacturer\Query\GetManufacturerForEditing;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSCreate;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSDelete;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSGet;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSPartialUpdate;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSUpdate;
use PrestaShopBundle\ApiPlatform\Metadata\LocalizedValue;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    operations: [
        new CQRSGet(
            uriTemplate: '/manufacturers/{manufacturerId}',
            requirements: ['manufacturerId' => '\d+'],
            CQRSQuery: GetManufacturerForEditing::class,
            scopes: ['manufacturer_read'],
            CQRSQueryMapping: self::QUERY_MAPPING,
        ),
        new CQRSCreate(
            uriTemplate: '/manufacturers',
            CQRSCommand: AddManufacturerCommand::class,
            CQRSQuery: GetManufacturerForEditing::class,
            scopes: ['manufacturer_write'],
            CQRSQueryMapping: self::QUERY_MAPPING,
            CQRSCommandMapping: self::COMMAND_MAPPING,
        ),
        new CQRSPartialUpdate(
            uriTemplate: '/manufacturers/{manufacturerId}',
            requirements: ['manufacturerId' => '\d+'],
            CQRSCommand: EditManufacturerCommand::class,
            CQRSQuery: GetManufacturerForEditing::class,
            scopes: ['manufacturer_write'],
            CQRSQueryMapping: self::QUERY_MAPPING,
            CQRSCommandMapping: self::EDIT_COMMAND_MAPPING,
        ),
        new CQRSDelete(
            uriTemplate: '/manufacturers/{manufacturerId}',
            requirements: ['manufacturerId' => '\d+'],
            CQRSCommand: DeleteManufacturerCommand::class,
            scopes: ['manufacturer_write'],
        ),
        new CQRSUpdate(
            uriTemplate: '/manufacturers/{manufacturerId}/toggle-status',
            requirements: ['manufacturerId' => '\d+'],
            output: false,
            CQRSCommand: ToggleManufacturerStatusCommand::class,
            scopes: ['manufacturer_write'],
        ),
    ],
    exceptionToStatus: [
        ManufacturerConstraintException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        ManufacturerNotFoundException::class => Response::HTTP_NOT_FOUND,
        UpdateManufacturerException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        DeleteManufacturerException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
    ],
)]
class Manufacturer
{
    #[ApiProperty(identifier: true)]
    public int $manufacturerId;

    #[Assert\NotBlank]
    public string $name;

    #[LocalizedValue]
    public array $shortDescriptions;

    #[LocalizedValue]
    public array $descriptions;

    #[LocalizedValue]
    public array $metaTitles;

    #[LocalizedValue]
    public array $metaDescriptions;

    public bool $enabled;

    /**
     * @var int[]
     */
    public array $shopIds;

    public ?array $logoImage;

    public const QUERY_MAPPING = [
        '[isEnabled]' => '[enabled]',
        '[localizedShortDescriptions]' => '[shortDescriptions]',
        '[localizedDescriptions]' => '[descriptions]',
        '[localizedMetaTitles]' => '[metaTitles]',
        '[localizedMetaDescriptions]' => '[metaDescriptions]',
        '[associatedShops]' => '[shopIds]',
    ];

    public const COMMAND_MAPPING = [
        '[shortDescriptions]' => '[localizedShortDescriptions]',
        '[descriptions]' => '[localizedDescriptions]',
        '[metaTitles]' => '[localizedMetaTitles]',
        '[metaDescriptions]' => '[localizedMetaDescriptions]',
        '[shopIds]' => '[shopAssociation]',
    ];

    public const EDIT_COMMAND_MAPPING = [
        '[shortDescriptions]' => '[localizedShortDescriptions]',
        '[descriptions]' => '[localizedDescriptions]',
        '[metaTitles]' => '[localizedMetaTitles]',
        '[metaDescriptions]' => '[localizedMetaDescriptions]',
        '[shopIds]' => '[associatedShops]',
    ];
}
