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

namespace PsApiResourcesTest\Integration\ApiPlatform;

use PrestaShop\PrestaShop\Core\Domain\Product\ValueObject\ProductType;
use Symfony\Component\HttpFoundation\Response;
use Tests\Resources\DatabaseDump;
use Tests\Resources\Resetter\ProductResetter;

class ProductPositionsEndpointTest extends ApiTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        ProductResetter::resetProducts();
        self::resetCategoryTables();
        // Pre-create the API Client with the needed scopes, this way we reduce the number of created API Clients
        self::createApiClient(['product_write', 'product_read', 'category_write']);
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        ProductResetter::resetProducts();
        self::resetCategoryTables();
    }

    protected static function resetCategoryTables(): void
    {
        DatabaseDump::restoreTables([
            'category',
            'category_lang',
            'category_group',
            'category_shop',
            'category_product',
        ]);
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'update positions endpoint' => [
            'PUT',
            '/products/positions',
        ];
    }

    public function testUpdateProductsPositions(): void
    {
        // A fresh empty category so the associated product gets a deterministic position (0)
        $category = $this->createItem('/categories', [
            'names' => ['en-US' => 'Positions category'],
            'linkRewrites' => ['en-US' => 'positions-category'],
            'isActive' => true,
            'parentCategoryId' => 2,
            'shopIds' => [1],
        ], ['category_write']);
        $this->assertArrayHasKey('categoryId', $category);
        $categoryId = $category['categoryId'];

        $product = $this->createItem('/products', [
            'type' => ProductType::TYPE_STANDARD,
            'names' => ['en-US' => 'product to reorder'],
        ], ['product_write']);
        $this->assertArrayHasKey('productId', $product);
        $productId = $product['productId'];

        // Associate the product to the fresh category: it becomes the first product (position 0)
        $this->createItem('/products/' . $productId . '/assign-to-categories', [
            'categoryId' => $categoryId,
        ], ['product_write'], Response::HTTP_NO_CONTENT);

        // Reorder the products of the category (single product staying at position 0)
        $this->updateItem('/products/positions', [
            'categoryId' => $categoryId,
            'positions' => [
                ['rowId' => $productId, 'oldPosition' => 0, 'newPosition' => 0],
            ],
        ], ['product_write'], Response::HTTP_NO_CONTENT);
    }
}
