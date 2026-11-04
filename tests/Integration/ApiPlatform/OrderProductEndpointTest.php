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

class OrderProductEndpointTest extends ApiTestCase
{
    private static int $orderId;
    private static int $updateDetailId;
    private static int $deleteDetailId;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::createApiClient(['order_write']);

        // Pick an order that has at least two products, so one can be deleted without removing the last one.
        self::$orderId = (int) \Db::getInstance()->getValue(
            'SELECT `id_order` FROM `' . _DB_PREFIX_ . 'order_detail`
             GROUP BY `id_order` HAVING COUNT(*) >= 2 ORDER BY `id_order` ASC'
        );
        $details = \Db::getInstance()->executeS(
            'SELECT `id_order_detail` FROM `' . _DB_PREFIX_ . 'order_detail`
             WHERE `id_order` = ' . self::$orderId . ' ORDER BY `id_order_detail` ASC'
        );
        self::$updateDetailId = (int) $details[0]['id_order_detail'];
        self::$deleteDetailId = (int) $details[1]['id_order_detail'];
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        DatabaseDump::restoreTables([
            'orders',
            'order_detail',
            'order_invoice',
            'order_cart_rule',
            'stock_available',
            'cart',
        ]);
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'update order product endpoint' => ['PUT', '/orders/1/products/1'];
        yield 'delete order product endpoint' => ['DELETE', '/orders/1/products/1'];
    }

    public function testUpdateProductInOrder(): void
    {
        $detail = \Db::getInstance()->getRow(
            'SELECT `product_quantity`, `unit_price_tax_incl`, `unit_price_tax_excl`
             FROM `' . _DB_PREFIX_ . 'order_detail` WHERE `id_order_detail` = ' . self::$updateDetailId
        );
        $newQuantity = (int) $detail['product_quantity'] + 1;

        $this->updateItem(
            '/orders/' . self::$orderId . '/products/' . self::$updateDetailId,
            [
                'priceTaxIncluded' => (string) $detail['unit_price_tax_incl'],
                'priceTaxExcluded' => (string) $detail['unit_price_tax_excl'],
                'quantity' => $newQuantity,
            ],
            ['order_write'],
            Response::HTTP_NO_CONTENT
        );

        $storedQuantity = (int) \Db::getInstance()->getValue(
            'SELECT `product_quantity` FROM `' . _DB_PREFIX_ . 'order_detail` WHERE `id_order_detail` = ' . self::$updateDetailId
        );
        $this->assertSame($newQuantity, $storedQuantity);
    }

    public function testDeleteProductFromOrder(): void
    {
        $this->deleteItem(
            '/orders/' . self::$orderId . '/products/' . self::$deleteDetailId,
            ['order_write']
        );

        $count = (int) \Db::getInstance()->getValue(
            'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'order_detail` WHERE `id_order_detail` = ' . self::$deleteDetailId
        );
        $this->assertSame(0, $count);
    }
}
