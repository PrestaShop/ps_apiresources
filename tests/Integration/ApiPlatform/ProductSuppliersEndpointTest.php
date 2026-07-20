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

use Symfony\Component\HttpFoundation\Response;
use Tests\Resources\DatabaseDump;

class ProductSuppliersEndpointTest extends ApiTestCase
{
    private static int $productId;
    private static int $supplierId;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::createApiClient(['product_write']);

        self::$productId = (int) \Db::getInstance()->getValue(
            'SELECT `id_product` FROM `' . _DB_PREFIX_ . 'product` WHERE `product_type` = "standard" ORDER BY `id_product` ASC'
        );
        self::$supplierId = (int) \Db::getInstance()->getValue(
            'SELECT `id_supplier` FROM `' . _DB_PREFIX_ . 'supplier` ORDER BY `id_supplier` ASC'
        );
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        DatabaseDump::restoreTables(['product_supplier', 'product']);
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'set suppliers endpoint' => ['PUT', '/products/1/suppliers'];
        yield 'remove all suppliers endpoint' => ['DELETE', '/products/1/suppliers'];
        yield 'set default supplier endpoint' => ['PUT', '/products/1/default-suppliers'];
    }

    public function testSetProductSuppliers(): int
    {
        $this->updateItem(
            '/products/' . self::$productId . '/suppliers',
            ['supplierIds' => [self::$supplierId]],
            ['product_write'],
            Response::HTTP_NO_CONTENT
        );

        $count = (int) \Db::getInstance()->getValue(
            'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'product_supplier`
             WHERE `id_product` = ' . self::$productId . ' AND `id_supplier` = ' . self::$supplierId
        );
        $this->assertGreaterThanOrEqual(1, $count);

        return self::$productId;
    }

    /**
     * @depends testSetProductSuppliers
     */
    public function testSetProductDefaultSupplier(int $productId): int
    {
        $this->updateItem(
            '/products/' . $productId . '/default-suppliers',
            ['defaultSupplierId' => self::$supplierId],
            ['product_write'],
            Response::HTTP_NO_CONTENT
        );

        $defaultSupplier = (int) \Db::getInstance()->getValue(
            'SELECT `id_supplier` FROM `' . _DB_PREFIX_ . 'product` WHERE `id_product` = ' . $productId
        );
        $this->assertSame(self::$supplierId, $defaultSupplier);

        return $productId;
    }

    /**
     * @depends testSetProductDefaultSupplier
     */
    public function testRemoveAllProductSuppliers(int $productId): void
    {
        $this->deleteItem('/products/' . $productId . '/suppliers', ['product_write']);

        $count = (int) \Db::getInstance()->getValue(
            'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'product_supplier` WHERE `id_product` = ' . $productId
        );
        $this->assertSame(0, $count);
    }
}
