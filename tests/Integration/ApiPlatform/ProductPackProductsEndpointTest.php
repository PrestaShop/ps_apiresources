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

class ProductPackProductsEndpointTest extends ApiTestCase
{
    private static int $packId;
    private static int $itemProductId;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::createApiClient(['product_write']);

        self::$packId = (int) \Db::getInstance()->getValue(
            'SELECT `id_product` FROM `' . _DB_PREFIX_ . 'product` WHERE `product_type` = "pack" ORDER BY `id_product` ASC'
        );
        self::$itemProductId = (int) \Db::getInstance()->getValue(
            'SELECT `id_product` FROM `' . _DB_PREFIX_ . 'product` WHERE `product_type` = "standard" ORDER BY `id_product` ASC'
        );
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        DatabaseDump::restoreTables(['pack', 'product']);
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'set pack products endpoint' => ['PUT', '/products/1/pack-products'];
        yield 'remove all pack products endpoint' => ['DELETE', '/products/1/pack-products'];
    }

    private function countPackItems(): int
    {
        return (int) \Db::getInstance()->getValue(
            'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'pack` WHERE `id_product_pack` = ' . self::$packId
        );
    }

    public function testSetPackProducts(): int
    {
        $this->updateItem(
            '/products/' . self::$packId . '/pack-products',
            ['products' => [
                ['product_id' => self::$itemProductId, 'quantity' => 2],
            ]],
            ['product_write'],
            Response::HTTP_NO_CONTENT
        );

        $count = (int) \Db::getInstance()->getValue(
            'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'pack`
             WHERE `id_product_pack` = ' . self::$packId . ' AND `id_product_item` = ' . self::$itemProductId
        );
        $this->assertSame(1, $count);

        return self::$packId;
    }

    /**
     * @depends testSetPackProducts
     */
    public function testRemoveAllPackProducts(int $packId): void
    {
        $this->deleteItem('/products/' . $packId . '/pack-products', ['product_write']);

        $this->assertSame(0, $this->countPackItems());
    }
}
