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

class ProductStatusEndpointTest extends ApiTestCase
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
        yield 'get status endpoint' => [
            'GET',
            '/products/1/status',
        ];

        yield 'bulk status endpoint' => [
            'PUT',
            '/products/bulk-status',
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

    public function testGetProductStatus(): int
    {
        $productId = $this->createProduct('product status get');

        $status = $this->getItem('/products/' . $productId . '/status', ['product_read']);
        $this->assertArrayHasKey('productId', $status);
        $this->assertArrayHasKey('enabled', $status);
        $this->assertEquals($productId, $status['productId']);
        $this->assertIsBool($status['enabled']);

        return $productId;
    }

    public function testBulkUpdateProductStatus(): void
    {
        $firstProductId = $this->createProduct('product bulk status one');
        $secondProductId = $this->createProduct('product bulk status two');

        // Enable both products
        $this->updateItem('/products/bulk-status', [
            'productIds' => [$firstProductId, $secondProductId],
            'enabled' => true,
        ], ['product_write'], Response::HTTP_NO_CONTENT);

        $this->assertTrue($this->getItem('/products/' . $firstProductId . '/status', ['product_read'])['enabled']);
        $this->assertTrue($this->getItem('/products/' . $secondProductId . '/status', ['product_read'])['enabled']);

        // Disable both products
        $this->updateItem('/products/bulk-status', [
            'productIds' => [$firstProductId, $secondProductId],
            'enabled' => false,
        ], ['product_write'], Response::HTTP_NO_CONTENT);

        $this->assertFalse($this->getItem('/products/' . $firstProductId . '/status', ['product_read'])['enabled']);
        $this->assertFalse($this->getItem('/products/' . $secondProductId . '/status', ['product_read'])['enabled']);
    }
}
