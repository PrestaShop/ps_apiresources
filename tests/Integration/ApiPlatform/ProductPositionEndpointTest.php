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

namespace PsApiResourcesTest\Integration\ApiPlatform;

use PrestaShop\PrestaShop\Core\Domain\Product\ValueObject\ProductType;
use Symfony\Component\HttpFoundation\Response;
use Tests\Resources\DatabaseDump;
use Tests\Resources\Resetter\ProductResetter;

class ProductPositionEndpointTest extends ApiTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::resetTables();
        self::createApiClient(['product_read', 'product_write', 'category_write']);
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        self::resetTables();
    }

    protected static function resetTables(): void
    {
        ProductResetter::resetProducts();
        DatabaseDump::restoreTables([
            'category',
            'category_lang',
            'category_group',
            'category_shop',
        ]);
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'update product positions endpoint' => ['PATCH', '/products/categories/2/positions'];
    }

    public function testUpdatePositions(): void
    {
        $category = $this->createItem('/categories', [
            'names' => [
                'en-US' => 'Sorted products',
                'fr-FR' => 'Produits tries',
            ],
            'linkRewrites' => [
                'en-US' => 'sorted-products',
                'fr-FR' => 'produits-tries',
            ],
            'isActive' => true,
            'parentCategoryId' => 2,
            'shopIds' => [1],
        ], ['category_write']);
        $this->assertArrayHasKey('categoryId', $category);
        $categoryId = $category['categoryId'];

        // The products are added at the end of the category
        $productIds = [];
        foreach (['first', 'second', 'third'] as $name) {
            $product = $this->createItem('/products', [
                'type' => ProductType::TYPE_STANDARD,
                'names' => [
                    'en-US' => $name . ' sorted product',
                    'fr-FR' => $name . ' produit trie',
                ],
            ], ['product_write']);
            $this->assertArrayHasKey('productId', $product);
            $productIds[$name] = $product['productId'];
            $this->updateItem('/products/' . $product['productId'] . '/categories', [
                'categoryIds' => [$categoryId],
                'defaultCategoryId' => $categoryId,
            ], ['product_write'], Response::HTTP_NO_CONTENT);
        }
        $this->assertSame([$productIds['first'], $productIds['second'], $productIds['third']], $this->getSortedProductIds($categoryId));

        // The third product moves to the first position, the others are shifted
        $this->assertNull($this->partialUpdateItem('/products/categories/' . $categoryId . '/positions', [
            'positions' => [
                ['productId' => $productIds['third'], 'newPosition' => 1],
            ],
        ], ['product_write'], Response::HTTP_NO_CONTENT));
        $this->assertSame([$productIds['third'], $productIds['first'], $productIds['second']], $this->getSortedProductIds($categoryId));

        // Several products at once
        $this->assertNull($this->partialUpdateItem('/products/categories/' . $categoryId . '/positions', [
            'positions' => [
                ['productId' => $productIds['first'], 'newPosition' => 3],
                ['productId' => $productIds['second'], 'newPosition' => 1],
            ],
        ], ['product_write'], Response::HTTP_NO_CONTENT));
        $this->assertSame([$productIds['second'], $productIds['third'], $productIds['first']], $this->getSortedProductIds($categoryId));

        // A product that is not in the category is refused, nothing changes
        $this->partialUpdateItem('/products/categories/' . $categoryId . '/positions', [
            'positions' => [
                ['productId' => 1, 'newPosition' => 1],
            ],
        ], ['product_write'], Response::HTTP_BAD_REQUEST);
        $this->assertSame([$productIds['second'], $productIds['third'], $productIds['first']], $this->getSortedProductIds($categoryId));
    }

    /**
     * The products of the category in the order of their position.
     *
     * @return int[]
     */
    private function getSortedProductIds(int $categoryId): array
    {
        $products = $this->listItems('/products?orderBy=position&sortOrder=asc', ['product_read'], ['id_category' => $categoryId]);
        $this->assertEquals(3, $products['totalItems']);

        return array_column($products['items'], 'productId');
    }
}
