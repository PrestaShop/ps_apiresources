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
use Doctrine\DBAL\Exception\InvalidFieldNameException;
use PrestaShop\PrestaShop\Core\Search\Filters\CountryFilters;
use PrestaShopBundle\ApiPlatform\Metadata\PaginatedList;
use PrestaShopBundle\ApiPlatform\Provider\QueryListProvider;
use Symfony\Component\HttpFoundation\Response;

#[ApiResource(
    operations: [
        new PaginatedList(
            uriTemplate: '/countries',
            scopes: ['country_read'],
            ApiResourceMapping: [
                '[id_country]' => '[countryId]',
                '[iso_code]' => '[isoCode]',
                '[call_prefix]' => '[callPrefix]',
                '[zone_name]' => '[zoneName]',
                '[active]' => '[enabled]',
            ],
            gridDataFactory: 'prestashop.core.grid.data.factory.country',
            provider: QueryListProvider::class,
            filtersClass: CountryFilters::class,
            filtersMapping: [
                '[countryId]' => '[id_country]',
                '[isoCode]' => '[iso_code]',
                '[callPrefix]' => '[call_prefix]',
                '[zoneName]' => '[zone_name]',
                '[enabled]' => '[active]',
            ],
        ),
    ],
    normalizationContext: ['skip_null_values' => false],
    exceptionToStatus: [
        InvalidFieldNameException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
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
}
