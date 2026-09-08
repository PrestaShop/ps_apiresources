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

class OrderDetailsEndpointTest extends ApiTestCase
{
    private static int $orderId;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::createApiClient(['order_read']);

        // Orders cannot be created through a simple command, so reuse a demo order.
        self::$orderId = (int) \Db::getInstance()->getValue(
            'SELECT `id_order` FROM `' . _DB_PREFIX_ . 'orders` ORDER BY `id_order` ASC'
        );
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'get order details endpoint' => ['GET', '/orders/1/details'];
    }

    public function testGetOrderDetails(): void
    {
        $order = $this->getItem('/orders/' . self::$orderId . '/details', ['order_read']);

        $this->assertArrayHasKey('orderId', $order);
        $this->assertSame(self::$orderId, $order['orderId']);
        $this->assertArrayHasKey('reference', $order);
        $this->assertArrayHasKey('valid', $order);
        $this->assertArrayHasKey('customer', $order);
        $this->assertArrayHasKey('shippingAddress', $order);
        $this->assertArrayHasKey('invoiceAddress', $order);
        $this->assertArrayHasKey('products', $order);
        $this->assertArrayHasKey('history', $order);
        $this->assertArrayHasKey('prices', $order);
        $this->assertArrayHasKey('payments', $order);
        $this->assertArrayHasKey('shipping', $order);
        $this->assertArrayHasKey('createdAt', $order);
    }

    public function testGetNonExistentOrderDetails(): void
    {
        $this->requestApi('GET', '/orders/999999/details', null, ['order_read'], Response::HTTP_NOT_FOUND);
    }
}
