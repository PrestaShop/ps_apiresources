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

namespace PrestaShop\Module\APIResources\ApiPlatform\Resources\Address;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use PrestaShop\PrestaShop\Core\Search\Filters\ManufacturerAddressFilters;
use PrestaShopBundle\ApiPlatform\Metadata\PaginatedList;
use PrestaShopBundle\ApiPlatform\Provider\QueryListProvider;

/**
 * Built on the data factory of the manufacturer address grid without its decorator, which replaces the
 * missing manufacturer name with "--" for display.
 */
#[ApiResource(
    operations: [
        new PaginatedList(
            uriTemplate: '/addresses/manufacturers',
            provider: QueryListProvider::class,
            scopes: ['address_read'],
            ApiResourceMapping: [
                '[id_address]' => '[addressId]',
                '[name]' => '[manufacturerName]',
                '[firstname]' => '[firstName]',
                '[lastname]' => '[lastName]',
                '[country]' => '[countryName]',
            ],
            gridDataFactory: 'prestashop.core.grid.data.factory.manufacturer_address',
            filtersClass: ManufacturerAddressFilters::class,
            filtersMapping: [
                '[addressId]' => '[id_address]',
                '[manufacturerName]' => '[name]',
                '[firstName]' => '[firstname]',
                '[lastName]' => '[lastname]',
                // The grid filters the country by identifier, not by name
                '[countryId]' => '[country]',
            ],
        ),
    ],
    normalizationContext: ['skip_null_values' => false],
)]
class ManufacturerAddressList
{
    #[ApiProperty(identifier: true)]
    public int $addressId;

    // Null when the manufacturer of the address no longer exists
    public ?string $manufacturerName;

    public string $firstName;

    public string $lastName;

    public string $postcode;

    public string $city;

    public ?string $countryName;
}
