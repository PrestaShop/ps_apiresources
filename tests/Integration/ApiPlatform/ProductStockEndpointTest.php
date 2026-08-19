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

use PrestaShop\PrestaShop\Core\Domain\Product\Stock\ValueObject\OutOfStockType;
use PrestaShop\PrestaShop\Core\Domain\Product\ValueObject\ProductType;
use Symfony\Component\HttpFoundation\Response;
use Tests\Resources\Resetter\ProductResetter;

class ProductStockEndpointTest extends ApiTestCase
{
    public static function setUpBeforeClass(): void
    {
        if (self::isVersionUnder('9.2.0')) {
            static::markTestSkipped('The product stock endpoint requires PrestaShop 9.2.0 (older cores cannot create stock movements for API clients)');

            return;
        }

        parent::setUpBeforeClass();
        ProductResetter::resetProducts();
        self::createApiClient(['product_write', 'product_read']);
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        ProductResetter::resetProducts();
    }

    public static function getProtectedEndpoints(): iterable
    {
        // Data providers are resolved when PHPUnit builds the test suite, before setUpBeforeClass
        // gets a chance to skip the class, and an empty provider is reported as an error. So the
        // endpoint is yielded unconditionally; on cores < 9.2.0 the whole class is skipped anyway
        // and this data set is never executed.
        yield 'update product stock endpoint' => [
            'PUT',
            '/products/1/stock',
        ];
    }

    public function testUpdateProductStock(): int
    {
        $product = $this->createItem('/products', [
            'type' => ProductType::TYPE_STANDARD,
            'names' => [
                'en-US' => 'product with stock',
                'fr-FR' => 'produit avec stock',
            ],
        ], ['product_write']);
        $this->assertArrayHasKey('productId', $product);
        $productId = $product['productId'];

        $updatedStock = $this->updateItem(sprintf('/products/%d/stock', $productId), [
            'deltaQuantity' => 10,
            'location' => 'shelf A',
        ], ['product_write']);

        $this->assertEquals(
            [
                'productId' => $productId,
                'quantity' => 10,
                'outOfStockType' => OutOfStockType::OUT_OF_STOCK_DEFAULT,
                'location' => 'shelf A',
            ],
            $updatedStock
        );

        return $productId;
    }

    /**
     * @depends testUpdateProductStock
     */
    public function testDecreaseProductStock(int $productId): void
    {
        $updatedStock = $this->updateItem(sprintf('/products/%d/stock', $productId), [
            'deltaQuantity' => -4,
            'outOfStockType' => OutOfStockType::OUT_OF_STOCK_AVAILABLE,
        ], ['product_write']);

        $this->assertEquals(
            [
                'productId' => $productId,
                'quantity' => 6,
                'outOfStockType' => OutOfStockType::OUT_OF_STOCK_AVAILABLE,
                'location' => 'shelf A',
            ],
            $updatedStock
        );
    }

    public function testUpdateStockForUnknownProduct(): void
    {
        $this->updateItem('/products/99999999/stock', [
            'deltaQuantity' => 10,
        ], ['product_write'], Response::HTTP_NOT_FOUND);
    }

    /**
     * @depends testUpdateProductStock
     */
    public function testInvalidProductStock(int $productId): void
    {
        // The out of stock type only accepts the values 0, 1 and 2
        $this->updateItem(sprintf('/products/%d/stock', $productId), [
            'outOfStockType' => 99,
        ], ['product_write'], Response::HTTP_UNPROCESSABLE_ENTITY);

        // A zero product id is invalid (it passes the URI requirements but fails the domain constraint)
        $this->updateItem('/products/0/stock', [
            'deltaQuantity' => 10,
        ], ['product_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}
