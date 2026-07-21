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

namespace PrestaShop\Module\APIResources\ApiPlatform\Resources\Product;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use PrestaShop\PrestaShop\Core\Domain\CatalogPriceRule\Query\GetCatalogPriceRuleListForProduct;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSGet;

#[ApiResource(
    operations: [
        new CQRSGet(
            uriTemplate: '/products/{productId}/catalog-price-rules',
            requirements: ['productId' => '\d+'],
            CQRSQuery: GetCatalogPriceRuleListForProduct::class,
            scopes: [
                'catalog_price_rule_read',
            ],
            CQRSQueryMapping: self::QUERY_MAPPING,
        ),
    ],
)]
class ProductCatalogPriceRules
{
    #[ApiProperty(identifier: true)]
    public int $productId;

    #[ApiProperty(openapiContext: [
        'type' => 'array',
        'items' => [
            'type' => 'object',
            'properties' => [
                'catalogPriceRuleId' => ['type' => 'integer'],
                'catalogPriceRuleName' => ['type' => 'string'],
                'fromQuantity' => ['type' => 'integer'],
                'reductionType' => ['type' => 'string'],
                'reduction' => ['type' => 'string'],
                'taxIncluded' => ['type' => 'boolean'],
                'dateStart' => ['type' => 'string', 'format' => 'date-time'],
                'dateEnd' => ['type' => 'string', 'format' => 'date-time'],
                'shopName' => ['type' => 'string', 'nullable' => true],
                'currencyName' => ['type' => 'string', 'nullable' => true],
                'countryName' => ['type' => 'string', 'nullable' => true],
                'groupName' => ['type' => 'string', 'nullable' => true],
                'currencyIso' => ['type' => 'string', 'nullable' => true],
            ],
        ],
    ])]
    public array $catalogPriceRules;

    public int $totalCount;

    public const QUERY_MAPPING = [
        '[_context][langId]' => '[langId]',
        '[productId]' => '[productId]',
    ];
}
