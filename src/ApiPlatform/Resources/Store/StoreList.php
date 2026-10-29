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

namespace PrestaShop\Module\APIResources\ApiPlatform\Resources\Store;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use PrestaShop\PrestaShop\Core\Search\Filters\StoreFilters;
use PrestaShopBundle\ApiPlatform\Metadata\PaginatedList;
use PrestaShopBundle\ApiPlatform\Provider\QueryListProvider;

/**
 * Built on the data factory of the store grid without its decorator, which replaces the empty state, phone
 * and fax with "--" for display.
 */
#[ApiResource(
    operations: [
        new PaginatedList(
            uriTemplate: '/stores',
            provider: QueryListProvider::class,
            scopes: ['store_read'],
            ApiResourceMapping: [
                '[id_store]' => '[storeId]',
                '[state]' => '[stateName]',
                '[country]' => '[countryName]',
                '[active]' => '[enabled]',
            ],
            gridDataFactory: 'prestashop.core.grid.data.factory.store',
            filtersClass: StoreFilters::class,
            filtersMapping: [
                '[storeId]' => '[id_store]',
                '[stateName]' => '[state]',
                '[countryName]' => '[country]',
                '[enabled]' => '[active]',
            ],
        ),
    ],
    normalizationContext: ['skip_null_values' => false],
)]
class StoreList
{
    #[ApiProperty(identifier: true)]
    public int $storeId;

    public string $name;

    public string $address;

    public string $city;

    public string $postcode;

    public ?string $stateName;

    public ?string $countryName;

    public string $phone;

    public string $fax;

    public bool $enabled;
}
