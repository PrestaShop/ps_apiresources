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

class ProductStockEndpointTest extends ApiTestCase
{
    private static int $productId;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::createApiClient(['product_write']);

        // A simple product (no combinations) so the product-level stock is unambiguous
        self::$productId = (int) \Db::getInstance()->getValue(
            'SELECT p.`id_product` FROM `' . _DB_PREFIX_ . 'product` p
             WHERE NOT EXISTS (
                 SELECT 1 FROM `' . _DB_PREFIX_ . 'product_attribute` pa WHERE pa.`id_product` = p.`id_product`
             )
             ORDER BY p.`id_product` ASC'
        );
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        DatabaseDump::restoreTables(['stock_available']);
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'update stock endpoint' => ['PUT', '/products/1/stocks'];
    }

    public function testUpdateProductStock(): void
    {
        $initial = (int) \StockAvailable::getQuantityAvailableByProduct(self::$productId);

        $this->updateItem(
            '/products/' . self::$productId . '/stocks',
            ['deltaQuantity' => 5],
            ['product_write'],
            Response::HTTP_NO_CONTENT
        );

        $this->assertSame($initial + 5, (int) \StockAvailable::getQuantityAvailableByProduct(self::$productId));
    }

    public function testUpdateStockOnMissingProductReturnsNotFound(): void
    {
        $this->updateItem(
            '/products/999999/stocks',
            ['deltaQuantity' => 5],
            ['product_write'],
            Response::HTTP_NOT_FOUND
        );
    }
}
