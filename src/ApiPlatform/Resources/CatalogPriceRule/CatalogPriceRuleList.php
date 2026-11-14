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

namespace PrestaShop\Module\APIResources\ApiPlatform\Resources\CatalogPriceRule;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use PrestaShop\PrestaShop\Core\Search\Filters\CatalogPriceRuleFilters;
use PrestaShopBundle\ApiPlatform\Metadata\PaginatedList;

#[ApiResource(
    operations: [
        new PaginatedList(
            uriTemplate: '/catalog-price-rules',
            scopes: [
                'catalog_price_rule_read',
            ],
            ApiResourceMapping: self::MAPPING,
            gridDataFactory: 'prestashop.core.grid.data.factory.catalog_price_rule',
            filtersClass: CatalogPriceRuleFilters::class,
            filtersMapping: [
                '[catalogPriceRuleId]' => '[id_specific_price_rule]',
            ],
        ),
    ]
)]
class CatalogPriceRuleList
{
    #[ApiProperty(identifier: true)]
    public int $catalogPriceRuleId;

    public string $name;

    public int $fromQuantity;

    public const MAPPING = [
        '[id_specific_price_rule]' => '[catalogPriceRuleId]',
        '[from_quantity]' => '[fromQuantity]',
    ];
}
