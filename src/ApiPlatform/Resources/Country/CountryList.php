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

namespace PrestaShop\Module\APIResources\ApiPlatform\Resources\Country;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use PrestaShop\PrestaShop\Core\Domain\Country\Exception\CountryNotFoundException;
use PrestaShop\PrestaShop\Core\Search\Filters\CountryFilters;
use PrestaShopBundle\ApiPlatform\Metadata\PaginatedList;
use PrestaShopBundle\ApiPlatform\Provider\QueryListProvider;
use Symfony\Component\HttpFoundation\Response;

#[ApiResource(
    operations: [
        new PaginatedList(
            uriTemplate: '/countries',
            provider: QueryListProvider::class,
            scopes: ['country_read'],
            ApiResourceMapping: self::MAPPING,
            gridDataFactory: 'prestashop.core.grid.data.factory.country',
            filtersClass: CountryFilters::class,
            filtersMapping: self::FILTERS_MAPPING,
        ),
    ],
    exceptionToStatus: [
        CountryNotFoundException::class => Response::HTTP_NOT_FOUND,
    ],
)]
class CountryList
{
    #[ApiProperty(identifier: true)]
    public int $countryId;

    public string $name;

    public string $isoCode;

    public int $callPrefix;

    public string $zoneName;

    public bool $enabled;

    /**
     * Maps the columns returned by CountryQueryBuilder to the API field names.
     */
    public const MAPPING = [
        '[id_country]' => '[countryId]',
        '[name]' => '[name]',
        '[iso_code]' => '[isoCode]',
        '[call_prefix]' => '[callPrefix]',
        '[zone_name]' => '[zoneName]',
        '[active]' => '[enabled]',
    ];

    /**
     * Maps the API field names used in filters and orderBy back to the grid filter names.
     */
    public const FILTERS_MAPPING = [
        '[countryId]' => '[id_country]',
        '[isoCode]' => '[iso_code]',
        '[callPrefix]' => '[call_prefix]',
        '[zoneName]' => '[zone_name]',
        '[enabled]' => '[active]',
    ];
}
