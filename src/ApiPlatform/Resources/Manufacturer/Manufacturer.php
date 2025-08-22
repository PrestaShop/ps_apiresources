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

namespace PrestaShop\Module\APIResources\ApiPlatform\Resources\Manufacturer;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use PrestaShop\PrestaShop\Core\Domain\Manufacturer\Command\AddManufacturerCommand;
use PrestaShop\PrestaShop\Core\Domain\Manufacturer\Command\DeleteManufacturerCommand;
use PrestaShop\PrestaShop\Core\Domain\Manufacturer\Command\EditManufacturerCommand;
use PrestaShop\PrestaShop\Core\Domain\Manufacturer\Exception\ManufacturerException;
use PrestaShop\PrestaShop\Core\Domain\Manufacturer\Exception\ManufacturerNotFoundException;
use PrestaShop\PrestaShop\Core\Domain\Manufacturer\Query\GetManufacturerForEditing;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSCreate;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSDelete;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSGet;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSPartialUpdate;
use PrestaShopBundle\ApiPlatform\Metadata\LocalizedValue;
use Symfony\Component\HttpFoundation\Response;

#[ApiResource(
    operations: [
        // GET /manufacturer/{manufacturerId}
        new CQRSGet(
            uriTemplate: '/manufacturer/{manufacturerId}',
            requirements: ['manufacturerId' => '\d+'],
            CQRSQuery: GetManufacturerForEditing::class,
            scopes: ['manufacturer_read'],
            CQRSQueryMapping: self::QUERY_MAPPING,
        ),
        // POST /manufacturer
        new CQRSCreate(
            uriTemplate: '/manufacturer',
            CQRSCommand: AddManufacturerCommand::class,
            CQRSQuery: GetManufacturerForEditing::class,
            scopes: ['manufacturer_write'],
            CQRSQueryMapping: self::QUERY_MAPPING,
            CQRSCommandMapping: self::COMMAND_MAPPING,
        ),
        new CQRSPartialUpdate(
            uriTemplate: '/manufacturer/{manufacturerId}',
            requirements: ['manufacturerId' => '\d+'],
            CQRSCommand: EditManufacturerCommand::class,
            CQRSQuery: GetManufacturerForEditing::class,
            scopes: ['manufacturer_write'],
            CQRSQueryMapping: self::QUERY_MAPPING,
            CQRSCommandMapping: self::COMMAND_MAPPING,
        ),
        new CQRSDelete(
            uriTemplate: '/manufacturer/{manufacturerId}',
            requirements: ['manufacturerId' => '\d+'],
            CQRSCommand: DeleteManufacturerCommand::class,
            scopes: ['manufacturer_write'],
        ),
    ],
    exceptionToStatus: [
        ManufacturerException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        ManufacturerNotFoundException::class => Response::HTTP_NOT_FOUND,
    ],
)]
class Manufacturer
{
    #[ApiProperty(identifier: true)]
    public int $manufacturerId;

    public string $name;

    #[ApiProperty(
        required: false,
        openapiContext: ['nullable' => true]
    )]
    public ?array $logoImage = null;

    #[LocalizedValue]
    public array $shortDescriptions;

    #[LocalizedValue]
    public array $descriptions;

    #[LocalizedValue]
    public array $metaTitles;

    #[LocalizedValue]
    public array $metaDescriptions;

    #[LocalizedValue]
    public array $metaKeywords;

    #[ApiProperty(openapiContext: ['type' => 'array', 'items' => ['type' => 'integer']])]
    public array $shopIds;

    public bool $enabled;

    public const QUERY_MAPPING = [
        '[manufacturerId]' => '[manufacturerId]',
        '[name]' => '[name]',
        '[logoImage]' => '[logoImage]',
        '[localizedShortDescriptions]' => '[shortDescriptions]',
        '[localizedMetaTitles]' => '[metaTitles]',
        '[localizedDescriptions]' => '[descriptions]',
        '[localizedMetaDescriptions]' => '[metaDescriptions]',
        '[localizedMetaKeywords]' => '[metaKeywords]',
        '[enabled]' => '[enabled]',
        '[associatedShops]' => '[shopIds]',
    ];

    public const COMMAND_MAPPING = [
        '[manufacturerId]' => '[manufacturerId]',
        '[name]' => '[name]',
        '[logoImage]' => '[logoImage]',
        '[shortDescriptions]' => '[localizedShortDescriptions]',
        '[metaTitles]' => '[localizedMetaTitles]',
        '[descriptions]' => '[localizedDescriptions]',
        '[metaDescriptions]' => '[localizedMetaDescriptions]',
        '[enabled]' => '[enabled]',
        '[shopIds]' => '[shopAssociation]',
    ];
}
