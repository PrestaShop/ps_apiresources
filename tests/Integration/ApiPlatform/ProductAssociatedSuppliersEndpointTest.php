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

use Tests\Resources\DatabaseDump;

class ProductAssociatedSuppliersEndpointTest extends ApiTestCase
{
    private static int $productId;
    private static int $supplierId;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::createApiClient(['product_read']);

        self::$productId = (int) \Db::getInstance()->getValue(
            'SELECT `id_product` FROM `' . _DB_PREFIX_ . 'product` WHERE `product_type` = "standard" ORDER BY `id_product` ASC'
        );
        self::$supplierId = (int) \Db::getInstance()->getValue(
            'SELECT `id_supplier` FROM `' . _DB_PREFIX_ . 'supplier` ORDER BY `id_supplier` ASC'
        );

        // Seed an association so the endpoint returns a non-empty list.
        $productSupplier = new \ProductSupplier();
        $productSupplier->id_product = self::$productId;
        $productSupplier->id_product_attribute = 0;
        $productSupplier->id_supplier = self::$supplierId;
        $productSupplier->id_currency = 1;
        $productSupplier->product_supplier_reference = 'seed-ref';
        $productSupplier->product_supplier_price_te = 0;
        $productSupplier->save();
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        DatabaseDump::restoreTables(['product_supplier', 'product']);
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'get associated suppliers endpoint' => ['GET', '/products/1/associated-suppliers'];
    }

    public function testGetProductAssociatedSuppliers(): void
    {
        $result = $this->getItem('/products/' . self::$productId . '/associated-suppliers', ['product_read']);

        $this->assertSame(self::$productId, $result['productId']);
        $this->assertArrayHasKey('defaultSupplierId', $result);
        $this->assertContains(self::$supplierId, array_map('intval', $result['supplierIds']));
    }
}
