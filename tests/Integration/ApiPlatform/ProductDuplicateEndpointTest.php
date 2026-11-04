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
use Tests\Resources\Resetter\ProductResetter;

class ProductDuplicateEndpointTest extends ApiTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        ProductResetter::resetProducts();
        // Pre-create the API Client with the needed scopes, this way we reduce the number of created API Clients
        self::createApiClient(['product_write', 'product_read']);
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        ProductResetter::resetProducts();
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'duplicate endpoint' => [
            'POST',
            '/products/1/duplicate',
        ];

        yield 'bulk duplicate endpoint' => [
            'PUT',
            '/products/bulk-duplicate',
        ];
    }

    private function createProduct(string $name): int
    {
        $product = $this->createItem('/products', [
            'type' => ProductType::TYPE_STANDARD,
            'names' => [
                'en-US' => $name,
            ],
        ], ['product_write']);
        $this->assertArrayHasKey('productId', $product);

        return $product['productId'];
    }

    public function testDuplicateProduct(): void
    {
        $productId = $this->createProduct('product to duplicate');
        $productsNumber = $this->countItems('/products', ['product_read']);

        $duplicatedProduct = $this->createItem(
            '/products/' . $productId . '/duplicate',
            [],
            ['product_write']
        );

        $this->assertArrayHasKey('productId', $duplicatedProduct);
        // The duplicate is a brand new product, not the source one
        $this->assertNotEquals($productId, $duplicatedProduct['productId']);
        $this->assertEquals($productsNumber + 1, $this->countItems('/products', ['product_read']));
    }

    public function testBulkDuplicateProducts(): void
    {
        $firstProductId = $this->createProduct('bulk duplicate one');
        $secondProductId = $this->createProduct('bulk duplicate two');
        $productsNumber = $this->countItems('/products', ['product_read']);

        $this->updateItem('/products/bulk-duplicate', [
            'productIds' => [$firstProductId, $secondProductId],
        ], ['product_write'], Response::HTTP_NO_CONTENT);

        $this->assertEquals($productsNumber + 2, $this->countItems('/products', ['product_read']));
    }
}
