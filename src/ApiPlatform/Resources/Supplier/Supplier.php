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

namespace PrestaShop\Module\APIResources\ApiPlatform\Resources\Supplier;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use PrestaShop\PrestaShop\Core\Domain\Supplier\Command\AddSupplierCommand;
use PrestaShop\PrestaShop\Core\Domain\Supplier\Command\DeleteSupplierCommand;
use PrestaShop\PrestaShop\Core\Domain\Supplier\Command\DeleteSupplierLogoImageCommand;
use PrestaShop\PrestaShop\Core\Domain\Supplier\Command\EditSupplierCommand;
use PrestaShop\PrestaShop\Core\Domain\Supplier\Command\ToggleSupplierStatusCommand;
use PrestaShop\PrestaShop\Core\Domain\Supplier\Exception\SupplierNotFoundException;
use PrestaShop\PrestaShop\Core\Domain\Supplier\Query\GetSupplierForEditing;
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
        new CQRSCreate(
            uriTemplate: '/suppliers',
            validationContext: ['groups' => ['Default', 'Create']],
            CQRSCommand: AddSupplierCommand::class,
            scopes: ['supplier_write'],
            CQRSCommandMapping: self::COMMAND_MAPPING,
        ),
        new CQRSDelete(
            uriTemplate: '/suppliers/{supplierId}',
            requirements: ['supplierId' => '\d+'],
            output: false,
            CQRSCommand: DeleteSupplierCommand::class,
            scopes: ['supplier_write']
        ),
        new CQRSDelete(
            uriTemplate: '/suppliers/{supplierId}/logo',
            requirements: ['supplierId' => '\d+'],
            output: false,
            CQRSCommand: DeleteSupplierLogoImageCommand::class,
            scopes: ['supplier_write']
        ),
        new CQRSGet(
            uriTemplate: '/suppliers/{supplierId}',
            requirements: ['supplierId' => '\d+'],
            CQRSQuery: GetSupplierForEditing::class,
            scopes: ['supplier_read'],
            CQRSQueryMapping: self::QUERY_MAPPING,
        ),
        new CQRSPartialUpdate(
            uriTemplate: '/suppliers/{supplierId}',
            requirements: ['supplierId' => '\d+'],
            read: false,
            CQRSCommand: EditSupplierCommand::class,
            CQRSCommandMapping: self::COMMAND_MAPPING,
            CQRSQuery: GetSupplierForEditing::class,
            CQRSQueryMapping: self::QUERY_MAPPING,
            scopes: ['supplier_write'],
        ),
        new CQRSUpdate(
            uriTemplate: '/suppliers/{supplierId}/toggle-status',
            requirements: ['supplierId' => '\d+'],
            output: false,
            allowEmptyBody: true,
            CQRSCommand: ToggleSupplierStatusCommand::class,
            scopes: ['supplier_write'],
        ),
    ],
    normalizationContext: ['skip_null_values' => false],
    exceptionToStatus: [
        SupplierNotFoundException::class => Response::HTTP_NOT_FOUND,
    ],
)]
class Supplier
{
    #[ApiProperty(identifier: true)]
    public int $supplierId;

    #[Assert\NotBlank(groups: ['Create'])]
    #[Assert\Length(min: 1, max: 64)]
    public string $name;

    public string $address;

    public ?string $address2;

    public ?string $postCode;

    public string $city;

    public ?int $stateId;

    public int $countryId;

    public ?string $phone;

    public ?string $mobilePhone;

    public ?string $dni;

    #[Assert\NotNull(groups: ['Create'])]
    public bool $enabled;

    #[LocalizedValue]
    public array $descriptions;

    #[LocalizedValue]
    public array $metaTitles;

    #[LocalizedValue]
    public array $metaDescriptions;

    #[ApiProperty(openapiContext: ['type' => 'array', 'items' => ['type' => 'integer'], 'example' => [1, 3]])]
    #[Assert\NotBlank(allowNull: true)]
    public array $shopIds;

    public ?array $logoImage;

    public const COMMAND_MAPPING = [
        '[descriptions]' => '[localizedDescriptions]',
        '[metaTitles]' => '[localizedMetaTitles]',
        '[metaDescriptions]' => '[localizedMetaDescriptions]',
        '[shopIds]' => '[shopAssociation]',
    ];

    public const QUERY_MAPPING = [
        '[localizedDescriptions]' => '[descriptions]',
        '[localizedMetaTitles]' => '[metaTitles]',
        '[localizedMetaDescriptions]' => '[metaDescriptions]',
        '[associatedShops]' => '[shopIds]',
    ];
}
