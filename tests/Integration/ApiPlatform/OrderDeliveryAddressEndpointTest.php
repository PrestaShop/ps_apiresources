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

class OrderDeliveryAddressEndpointTest extends ApiTestCase
{
    private static int $orderId;
    private static int $invoiceAddressId;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::createApiClient(['order_write']);

        $row = \Db::getInstance()->getRow(
            'SELECT `id_order`, `id_address_invoice` FROM `' . _DB_PREFIX_ . 'orders`
             WHERE `id_address_delivery` > 0 AND `id_address_invoice` > 0
             ORDER BY `id_order` ASC'
        );
        self::$orderId = (int) $row['id_order'];
        self::$invoiceAddressId = (int) $row['id_address_invoice'];
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        DatabaseDump::restoreTables(['orders', 'order_detail', 'order_invoice', 'order_cart_rule', 'cart']);
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'change delivery address endpoint' => ['PUT', '/orders/1/delivery-addresses'];
    }

    public function testChangeOrderDeliveryAddress(): void
    {
        // Reuse a valid customer address already attached to the order
        $this->updateItem(
            '/orders/' . self::$orderId . '/delivery-addresses',
            ['addressId' => self::$invoiceAddressId],
            ['order_write'],
            Response::HTTP_NO_CONTENT
        );

        $order = new \Order(self::$orderId);
        $this->assertSame(self::$invoiceAddressId, (int) $order->id_address_delivery);
    }

    public function testChangeDeliveryAddressOnMissingOrderReturnsNotFound(): void
    {
        $this->updateItem(
            '/orders/999999/delivery-addresses',
            ['addressId' => self::$invoiceAddressId],
            ['order_write'],
            Response::HTTP_NOT_FOUND
        );
    }
}
