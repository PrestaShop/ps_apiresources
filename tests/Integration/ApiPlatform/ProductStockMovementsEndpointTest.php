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
use Tests\Resources\Resetter\ProductResetter;

class ProductStockMovementsEndpointTest extends ApiTestCase
{
    public static function setUpBeforeClass(): void
    {
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
        yield 'get product stock movements endpoint' => [
            'GET',
            '/products/1/stock-movements',
        ];
    }

    public function testGetProductStockMovements(): array
    {
        $product = $this->createItem('/products', [
            'type' => ProductType::TYPE_STANDARD,
            'names' => [
                'en-US' => 'product with movements',
                'fr-FR' => 'produit avec mouvements',
            ],
        ], ['product_write']);
        $this->assertArrayHasKey('productId', $product);
        $productId = $product['productId'];

        // Each stock update creates one "edition" stock movement
        foreach ([10, -4, 7] as $deltaQuantity) {
            $this->updateItem(sprintf('/products/%d/stock', $productId), [
                'deltaQuantity' => $deltaQuantity,
            ], ['product_write']);
        }

        $movements = $this->getItem(sprintf('/products/%d/stock-movements', $productId), ['product_read']);
        $this->assertCount(3, $movements);

        // The movements are returned latest first; their ids and dates are generated
        // so they are extracted from the response and injected into the expected data
        $expectedMovements = [];
        foreach ([7, -4, 10] as $index => $deltaQuantity) {
            $expectedMovements[] = [
                'type' => 'edition',
                'edition' => true,
                'fromOrders' => false,
                'stockMovementIds' => $movements[$index]['stockMovementIds'] ?? null,
                'stockIds' => $movements[$index]['stockIds'] ?? null,
                'orderIds' => [],
                // The Admin API client is not an employee, so the movements are not linked to one;
                // the core handler still concatenates the empty first and last names, hence the ' '
                'employeeIds' => [],
                'employeeName' => ' ',
                'deltaQuantity' => $deltaQuantity,
                'dates' => [
                    'add' => $movements[$index]['dates']['add'] ?? null,
                ],
            ];
        }
        $this->assertEquals($expectedMovements, $movements);

        return [
            'productId' => $productId,
            'movements' => $movements,
        ];
    }

    /**
     * @depends testGetProductStockMovements
     */
    public function testGetProductStockMovementsPagination(array $fixtures): void
    {
        $productId = $fixtures['productId'];
        $movements = $fixtures['movements'];

        $this->assertEquals(
            array_slice($movements, 0, 2),
            $this->getItem(sprintf('/products/%d/stock-movements?limit=2', $productId), ['product_read'])
        );

        $this->assertEquals(
            array_slice($movements, 1, 2),
            $this->getItem(sprintf('/products/%d/stock-movements?offset=1&limit=2', $productId), ['product_read'])
        );
    }

    public function testGetStockMovementsForUnknownProduct(): void
    {
        $this->getItem('/products/99999999/stock-movements', ['product_read'], Response::HTTP_NOT_FOUND);
    }
}
