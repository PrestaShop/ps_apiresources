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

namespace PrestaShop\Module\APIResources\ApiPlatform\Resources\CatalogPriceRule;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use PrestaShop\Decimal\DecimalNumber;
use PrestaShop\PrestaShop\Core\Search\Filters\CatalogPriceRuleFilters;
use PrestaShopBundle\ApiPlatform\Metadata\PaginatedList;

#[ApiResource(
    operations: [
        new PaginatedList(
            uriTemplate: '/catalog-price-rules',
            scopes: ['catalog_price_rule_read'],
            ApiResourceMapping: self::MAPPING,
            gridDataFactory: 'prestashop.core.grid.data.factory.catalog_price_rule',
            filtersClass: CatalogPriceRuleFilters::class,
            filtersMapping: self::FILTERS_MAPPING,
            openapi: new OpenApiOperation(
                summary: 'List the catalog price rules.',
                description: 'Lists the catalog price rules, sorted by catalogPriceRuleId by default (orderBy '
                    . 'takes the API field names). The name filter matches partially; an unknown filter is ignored. The '
                    . 'names are in the language of the context (langId parameter); shopName is null for a shop '
                    . 'outside the context, currencyName, countryName and groupName are null when the rule applies '
                    . 'to every currency, country or group. A rule without start or end date returns a null dateFrom '
                    . 'or dateTo from PrestaShop 9.2.0, -0001-11-30 00:00:00 before.',
            ),
        ),
    ],
    normalizationContext: ['skip_null_values' => false],
)]
class CatalogPriceRuleList
{
    #[ApiProperty(identifier: true)]
    public int $catalogPriceRuleId;

    public string $name;

    public ?string $shopName;

    public ?string $currencyName;

    public ?string $countryName;

    public ?string $groupName;

    public int $fromQuantity;

    #[ApiProperty(openapiContext: ['enum' => ['amount', 'percentage']])]
    public string $reductionType;

    public DecimalNumber $reductionValue;

    public ?\DateTimeImmutable $dateFrom;

    public ?\DateTimeImmutable $dateTo;

    /**
     * Maps the columns returned by CatalogPriceRuleQueryBuilder to the API field names.
     */
    public const MAPPING = [
        '[id_specific_price_rule]' => '[catalogPriceRuleId]',
        '[shop]' => '[shopName]',
        '[currency]' => '[currencyName]',
        '[country]' => '[countryName]',
        '[group_name]' => '[groupName]',
        '[from_quantity]' => '[fromQuantity]',
        '[reduction_type]' => '[reductionType]',
        '[reduction]' => '[reductionValue]',
        '[date_from]' => '[dateFrom]',
        '[date_to]' => '[dateTo]',
    ];

    /**
     * Maps the API field names used in filters and orderBy back to the grid filter names.
     */
    public const FILTERS_MAPPING = [
        '[catalogPriceRuleId]' => '[id_specific_price_rule]',
        '[shopName]' => '[shop]',
        '[currencyName]' => '[currency]',
        '[countryName]' => '[country]',
        '[groupName]' => '[group_name]',
        '[fromQuantity]' => '[from_quantity]',
        '[reductionType]' => '[reduction_type]',
        '[reductionValue]' => '[reduction]',
        '[dateFrom]' => '[date_from]',
        '[dateTo]' => '[date_to]',
    ];
}
