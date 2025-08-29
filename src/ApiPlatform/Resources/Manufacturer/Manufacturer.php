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
use PrestaShop\PrestaShop\Core\ConstraintValidator\Constraints\DefaultLanguage;
use PrestaShop\PrestaShop\Core\ConstraintValidator\Constraints\TypedRegex;
use PrestaShop\PrestaShop\Core\Domain\Manufacturer\Command\AddManufacturerCommand;
use PrestaShop\PrestaShop\Core\Domain\Manufacturer\Command\DeleteManufacturerCommand;
use PrestaShop\PrestaShop\Core\Domain\Manufacturer\Command\EditManufacturerCommand;
use PrestaShop\PrestaShop\Core\Domain\Manufacturer\Exception\ManufacturerConstraintException;
use PrestaShop\PrestaShop\Core\Domain\Manufacturer\Exception\ManufacturerException;
use PrestaShop\PrestaShop\Core\Domain\Manufacturer\Exception\ManufacturerNotFoundException;
use PrestaShop\PrestaShop\Core\Domain\Manufacturer\Query\GetManufacturerForEditing;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSCreate;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSDelete;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSGet;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSPartialUpdate;
use PrestaShopBundle\ApiPlatform\Metadata\LocalizedValue;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints as Assert;

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
        ManufacturerNotFoundException::class => Response::HTTP_NOT_FOUND,
        ManufacturerConstraintException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        ManufacturerException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
    ],
)]
class Manufacturer
{
    #[ApiProperty(identifier: true)]
    public int $manufacturerId;

    #[Assert\NotBlank(groups: ['create'])]
    #[TypedRegex(['type' => TypedRegex::TYPE_CATALOG_NAME])]
    public string $name;

    #[ApiProperty(
        required: false,
        openapiContext: ['nullable' => true]
    )]
    public ?array $logoImage = null;

    #[LocalizedValue]
    #[DefaultLanguage(groups: ['Create'], fieldName: 'shortDescriptions')]
    #[DefaultLanguage(groups: ['Update'], fieldName: 'shortDescriptions', allowNull: true)]
    public array $shortDescriptions;

    #[LocalizedValue]
    #[DefaultLanguage(groups: ['Create'], fieldName: 'descriptions')]
    #[DefaultLanguage(groups: ['Update'], fieldName: 'descriptions', allowNull: true)]
    public array $descriptions;

    #[LocalizedValue]
    #[DefaultLanguage(groups: ['Create'], fieldName: 'metaTitles')]
    #[DefaultLanguage(groups: ['Update'], fieldName: 'metaTitles', allowNull: true)]
    #[Assert\All(constraints: [
        new TypedRegex([
            'type' => TypedRegex::TYPE_GENERIC_NAME,
        ]),
    ])]
    public array $metaTitles;

    #[LocalizedValue]
    #[DefaultLanguage(groups: ['Create'], fieldName: 'metaDescriptions')]
    #[DefaultLanguage(groups: ['Update'], fieldName: 'metaDescriptions', allowNull: true)]
    #[Assert\All(constraints: [
        new TypedRegex([
            'type' => TypedRegex::TYPE_GENERIC_NAME,
        ]),
    ])]
    public array $metaDescriptions;

    #[LocalizedValue]
    public array $metaKeywords;

    #[ApiProperty(openapiContext: ['type' => 'array', 'items' => ['type' => 'integer']])]
    #[Assert\NotBlank(allowNull: true)]
    public array $shopIds;

    #[Assert\Type(['type' => 'bool'])]
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
