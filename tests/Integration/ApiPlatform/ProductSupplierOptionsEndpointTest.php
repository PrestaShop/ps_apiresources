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

use PrestaShop\PrestaShop\Core\Domain\Product\Supplier\Command\SetSuppliersCommand;
use PrestaShop\PrestaShop\Core\Domain\Product\Supplier\Command\UpdateProductSuppliersCommand;
use Symfony\Component\HttpFoundation\Response;

class ProductSupplierOptionsEndpointTest extends ApiTestCase
{
    private const SEEDED_REFERENCE = 'INT-TEST-SUPPLIER-REF';
    private const SEEDED_PRICE = '12.345';
    private const SEEDED_CURRENCY_ID = 1;

    private static int $seededProductId;
    private static int $seededSupplierId;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::createApiClient(['product_read']);

        // A standard (non-combinations) product is required, otherwise the query handler
        // short-circuits productSuppliers to an empty array (see GetProductSupplierOptionsHandler).
        self::$seededProductId = (int) \Db::getInstance()->getValue(
            'SELECT `id_product` FROM `' . _DB_PREFIX_ . "product` WHERE `product_type` = 'standard' ORDER BY `id_product` ASC"
        );
        self::$seededSupplierId = (int) \Db::getInstance()->getValue(
            'SELECT `id_supplier` FROM `' . _DB_PREFIX_ . 'supplier` ORDER BY `id_supplier` ASC'
        );

        if (self::$seededProductId === 0 || self::$seededSupplierId === 0) {
            self::fail('Demo data must ship at least one product and one supplier for this test.');
        }

        $commandBus = static::createClient()->getContainer()->get('prestashop.core.command_bus');
        $commandBus->handle(new SetSuppliersCommand(self::$seededProductId, [self::$seededSupplierId]));
        $commandBus->handle(new UpdateProductSuppliersCommand(self::$seededProductId, [[
            'supplier_id' => self::$seededSupplierId,
            'currency_id' => self::SEEDED_CURRENCY_ID,
            'reference' => self::SEEDED_REFERENCE,
            'price_tax_excluded' => self::SEEDED_PRICE,
        ]]));
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'get product supplier options endpoint' => ['GET', '/products/1/supplier-options'];
    }

    public function testGetProductSupplierOptions(): void
    {
        $result = $this->getItem('/products/' . self::$seededProductId . '/supplier-options', ['product_read']);

        $this->assertSame(self::$seededProductId, $result['productId']);
        $this->assertSame(self::$seededSupplierId, $result['defaultSupplierId']);
        $this->assertSame([self::$seededSupplierId], $result['supplierIds']);

        $this->assertIsArray($result['productSuppliers']);
        $this->assertCount(1, $result['productSuppliers']);

        $productSupplier = $result['productSuppliers'][0];
        $this->assertIsInt($productSupplier['productSupplierId']);
        $this->assertGreaterThan(0, $productSupplier['productSupplierId']);
        $this->assertSame(self::$seededProductId, $productSupplier['productId']);
        $this->assertSame(self::$seededSupplierId, $productSupplier['supplierId']);
        $this->assertIsString($productSupplier['supplierName']);
        $this->assertNotSame('', $productSupplier['supplierName']);
        $this->assertSame(self::SEEDED_REFERENCE, $productSupplier['reference']);
        $this->assertIsString($productSupplier['priceTaxExcluded']);
        $this->assertSame((float) self::SEEDED_PRICE, (float) $productSupplier['priceTaxExcluded']);
        $this->assertSame(self::SEEDED_CURRENCY_ID, $productSupplier['currencyId']);
        $this->assertSame(0, $productSupplier['combinationId']);
    }

    public function testGetUnknownProductReturnsNotFound(): void
    {
        $this->requestApi(
            'GET',
            '/products/999999/supplier-options',
            null,
            ['product_read'],
            Response::HTTP_NOT_FOUND
        );
    }
}
