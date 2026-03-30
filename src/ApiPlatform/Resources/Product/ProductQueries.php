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
use PrestaShop\PrestaShop\Core\Domain\Product\AttributeGroup\Query\GetProductAttributeGroups;
use PrestaShop\PrestaShop\Core\Domain\Product\Exception\ProductNotFoundException;
use PrestaShop\PrestaShop\Core\Domain\Product\FeatureValue\Query\GetProductFeatureValues;
use PrestaShop\PrestaShop\Core\Domain\Product\Image\Query\GetShopProductImages;
use PrestaShop\PrestaShop\Core\Domain\Product\Query\GetProductIsEnabled;
use PrestaShop\PrestaShop\Core\Domain\Product\Query\GetRelatedProducts;
use PrestaShop\PrestaShop\Core\Domain\Product\Stock\Query\GetProductStockMovements;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSGet;
use Symfony\Component\HttpFoundation\Response;

#[ApiResource(
    operations: [
        new CQRSGet(
            uriTemplate: '/products/{productId}/attribute-groups',
            requirements: ['productId' => '\d+'],
            openapiContext: ['summary' => 'Get product attribute groups', 'description' => 'Retrieves attribute groups associated with a product for combinations'],
            CQRSQuery: GetProductAttributeGroups::class,
            scopes: [
                'product_read',
            ],
            CQRSQueryMapping: [
                '[_context][shopConstraint]' => '[shopConstraint]',
            ],
        ),
        new CQRSGet(
            uriTemplate: '/products/{productId}/feature-values',
            requirements: ['productId' => '\d+'],
            openapiContext: ['summary' => 'Get product feature values', 'description' => 'Retrieves feature values associated with a product'],
            CQRSQuery: GetProductFeatureValues::class,
            scopes: [
                'product_read',
            ],
            CQRSQueryMapping: [
                '[_context][shopId]' => '[shopId]',
            ],
        ),
        new CQRSGet(
            uriTemplate: '/products/{productId}/is-enableds',
            requirements: ['productId' => '\d+'],
            openapiContext: ['summary' => 'Get product enabled status', 'description' => 'Checks if a product is enabled or disabled'],
            CQRSQuery: GetProductIsEnabled::class,
            scopes: [
                'product_read',
            ],
        ),
        new CQRSGet(
            uriTemplate: '/products/{productId}/stock-movements',
            requirements: ['productId' => '\d+'],
            openapiContext: ['summary' => 'Get product stock movements', 'description' => 'Retrieves stock movement history for a product'],
            CQRSQuery: GetProductStockMovements::class,
            scopes: [
                'product_read',
            ],
            CQRSQueryMapping: [
                '[_context][shopId]' => '[shopId]',
            ],
        ),
        new CQRSGet(
            uriTemplate: '/products/{productId}/shop-images',
            requirements: ['productId' => '\d+'],
            openapiContext: ['summary' => 'Get product images for all shops', 'description' => 'Retrieves images associated with a product detailed for every shop'],
            CQRSQuery: GetShopProductImages::class,
            scopes: [
                'product_read',
            ],
        ),
        new CQRSGet(
            uriTemplate: '/products/{productId}/related-products',
            requirements: ['productId' => '\d+'],
            openapiContext: ['summary' => 'Get related products', 'description' => 'Retrieves products related to a given product'],
            CQRSQuery: GetRelatedProducts::class,
            scopes: [
                'product_read',
            ],
            CQRSQueryMapping: [
                '[_context][langId]' => '[languageId]',
            ],
        ),
        new CQRSGet(
            uriTemplate: '/products/{productId}/catalog-price-rules',
            requirements: ['productId' => '\d+'],
            openapiContext: ['summary' => 'Get catalog price rules for product', 'description' => 'Retrieves catalog price rules applicable to a product'],
            CQRSQuery: GetCatalogPriceRuleListForProduct::class,
            scopes: [
                'product_read',
            ],
            CQRSQueryMapping: [
                '[_context][langId]' => '[langId]',
            ],
        ),
    ],
    exceptionToStatus: [
        ProductNotFoundException::class => Response::HTTP_NOT_FOUND,
    ],
)]
class ProductQueries
{
    #[ApiProperty(identifier: true)]
    public int $productId;

    public int $offset;

    public int $limit;
}
