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
use PrestaShop\Decimal\DecimalNumber;
use PrestaShop\PrestaShop\Adapter\Product\Grid\Data\Factory\ProductGridDataFactoryDecorator;
use PrestaShop\PrestaShop\Core\Domain\Product\Exception\ProductNotFoundException;
use PrestaShop\PrestaShop\Core\Domain\Shop\Exception\ShopAssociationNotFound;
use PrestaShop\PrestaShop\Core\Search\Filters\ProductFilters;
use PrestaShopBundle\ApiPlatform\Metadata\PaginatedList;
use PrestaShopBundle\ApiPlatform\Provider\QueryListProvider;
use Symfony\Component\HttpFoundation\Response;

#[ApiResource(
    operations: [
        new PaginatedList(
            uriTemplate: '/products',
            provider: QueryListProvider::class,
            scopes: ['product_read'],
            ApiResourceMapping: [
                '[id_product]' => '[productId]',
                '[price_tax_excluded_decimal_value]' => '[priceTaxExcluded]',
                '[price_tax_included_decimal_value]' => '[priceTaxIncluded]',
                '[active]' => '[enabled]',
            ],
            gridDataFactory: ProductGridDataFactoryDecorator::class,
            filtersClass: ProductFilters::class,
            filtersMapping: [
                '[productId]' => '[id_product]',
                '[priceTaxExcluded]' => '[final_price_tax_excluded]',
                '[priceTaxIncluded]' => '[final_price_tax_included]',
                '[enabled]' => '[active]',
            ],
        ),
    ],
    exceptionToStatus: [
        ProductNotFoundException::class => Response::HTTP_NOT_FOUND,
        ShopAssociationNotFound::class => Response::HTTP_NOT_FOUND,
    ],
)]
class ProductList
{
    #[ApiProperty(identifier: true)]
    public int $productId;

    public string $type;

    public bool $enabled;

    public string $name;

    public int $quantity;

    public DecimalNumber $priceTaxExcluded;

    public DecimalNumber $priceTaxIncluded;

    public string $category;
}
