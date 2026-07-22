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

use PrestaShop\PrestaShop\Core\Domain\Product\Stock\Command\UpdateProductStockAvailableCommand;
use PrestaShop\PrestaShop\Core\Domain\Product\Stock\QueryResult\StockMovement;
use PrestaShop\PrestaShop\Core\Domain\Shop\ValueObject\ShopConstraint;
use Symfony\Component\HttpFoundation\Response;

class ProductStockMovementsEndpointTest extends ApiTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::createApiClient(['product_read']);
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'list product stock movements endpoint' => ['GET', '/products/1/stock-movements'];
    }

    public function testListProductStockMovements(): void
    {
        $productId = (int) \Db::getInstance()->getValue(
            'SELECT `id_product` FROM `' . _DB_PREFIX_ . 'product` ORDER BY `id_product` ASC'
        );

        // StockManager::saveMovement() forwards Context::getContext()->employee->id to
        // StockMvt::setIdEmployee(), which is typed int — a null employee context throws a
        // TypeError. Pin the default admin employee before seeding the movement.
        \Context::getContext()->employee = new \Employee(1);

        // Ensure the product has a known stock movement to assert against.
        $command = new UpdateProductStockAvailableCommand($productId, ShopConstraint::shop(1));
        $command->setDeltaQuantity(7);
        static::createClient()->getContainer()->get('prestashop.core.command_bus')->handle($command);

        $result = $this->getItem('/products/' . $productId . '/stock-movements', ['product_read']);

        $this->assertIsArray($result);
        $this->assertNotEmpty($result, 'Expected at least one stock movement after applying a delta');

        // Movements are returned most recent first, so the delta we just applied is at [0].
        $movement = $result[0];
        $this->assertSame(StockMovement::EDITION_TYPE, $movement['type']);
        $this->assertTrue($movement['edition']);
        $this->assertFalse($movement['fromOrders']);
        $this->assertSame(7, $movement['deltaQuantity']);
        $this->assertIsArray($movement['stockMovementIds']);
        $this->assertNotEmpty($movement['stockMovementIds']);
        $this->assertIsInt($movement['stockMovementIds'][0]);
        $this->assertIsArray($movement['stockIds']);
        $this->assertNotEmpty($movement['stockIds']);
        $this->assertIsInt($movement['stockIds'][0]);
        $this->assertIsArray($movement['orderIds']);
        $this->assertIsArray($movement['employeeIds']);
        $this->assertIsArray($movement['dates']);
        $this->assertArrayHasKey('add', $movement['dates']);
    }

    public function testListProductStockMovementsReturns404ForUnknownProduct(): void
    {
        $unknownProductId = 1 + (int) \Db::getInstance()->getValue(
            'SELECT MAX(`id_product`) FROM `' . _DB_PREFIX_ . 'product`'
        );

        $this->getItem('/products/' . $unknownProductId . '/stock-movements', ['product_read'], Response::HTTP_NOT_FOUND);
    }
}
