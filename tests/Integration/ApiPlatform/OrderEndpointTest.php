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

use Tests\Resources\Resetter\CustomerResetter;
use Tests\Resources\Resetter\OrderResetter;

class OrderEndpointTest extends ApiTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        CustomerResetter::resetCustomers();
        OrderResetter::resetOrders();
        // Pre-create the API Client with the needed scopes
        self::createApiClient(['order_write', 'order_read', 'cart_write', 'cart_read']);
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        OrderResetter::resetOrders();
        CustomerResetter::resetCustomers();
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'get order list endpoint' => [
            'GET',
            '/orders',
        ];

        yield 'get order endpoint' => [
            'GET',
            '/orders/1',
        ];

        yield 'create order endpoint' => [
            'POST',
            '/orders',
        ];

        yield 'update order status endpoint' => [
            'PATCH',
            '/orders/1',
        ];
    }

    public function testCreateOrderFromCart(): int
    {
        // First create a cart with products
        $createdCart = $this->createItem('/carts', [
            'customerId' => 1,
        ], ['cart_write']);
        $cartId = $createdCart['cartId'];

        // Add a product to the cart
        $this->partialUpdateItem('/carts/' . $cartId . '/products', [
            'productId' => 1,
            'quantity' => 1,
        ], ['cart_write']);

        $ordersNumber = $this->countItems('/orders', ['order_read']);

        $orderData = [
            'cartId' => $cartId,
            'employeeId' => 1,
            'paymentModuleName' => 'ps_checkpayment',
            'orderStateId' => 1,
            'orderMessage' => 'Test order created via API',
        ];

        $createdOrder = $this->createItem('/orders', $orderData, ['order_write']);

        $newOrdersNumber = $this->countItems('/orders', ['order_read']);
        self::assertEquals($ordersNumber + 1, $newOrdersNumber);

        $this->assertArrayHasKey('orderId', $createdOrder);
        $this->assertArrayHasKey('reference', $createdOrder);
        $this->assertArrayHasKey('statusId', $createdOrder);
        $this->assertArrayHasKey('customerId', $createdOrder);

        $orderId = $createdOrder['orderId'];
        $this->assertGreaterThan(0, $orderId);
        $this->assertEquals(1, $createdOrder['statusId']);
        $this->assertEquals(1, $createdOrder['customerId']);

        return $orderId;
    }

    /**
     * @depends testCreateOrderFromCart
     */
    public function testGetOrder(int $orderId): int
    {
        $order = $this->getItem('/orders/' . $orderId, ['order_read']);

        $this->assertArrayHasKey('orderId', $order);
        $this->assertArrayHasKey('reference', $order);
        $this->assertArrayHasKey('statusId', $order);
        $this->assertArrayHasKey('status', $order);
        $this->assertArrayHasKey('customerId', $order);
        $this->assertArrayHasKey('totalPaidTaxIncl', $order);
        $this->assertArrayHasKey('totalPaidTaxExcl', $order);
        $this->assertArrayHasKey('items', $order);
        $this->assertArrayHasKey('dateAdd', $order);

        $this->assertEquals($orderId, $order['orderId']);
        $this->assertIsString($order['reference']);
        $this->assertIsArray($order['items']);
        $this->assertIsFloat($order['totalPaidTaxIncl']);

        return $orderId;
    }

    /**
     * @depends testGetOrder
     */
    public function testUpdateOrderStatus(int $orderId): int
    {
        // Get the current order to check initial status
        $order = $this->getItem('/orders/' . $orderId, ['order_read']);
        $initialStatusId = $order['statusId'];

        // Update order status to "Payment accepted" (status ID 2)
        $updatedOrder = $this->partialUpdateItem('/orders/' . $orderId, [
            'statusId' => 2,
        ], ['order_write']);

        $this->assertArrayHasKey('orderId', $updatedOrder);
        $this->assertArrayHasKey('statusId', $updatedOrder);
        $this->assertEquals($orderId, $updatedOrder['orderId']);
        $this->assertEquals(2, $updatedOrder['statusId']);
        $this->assertNotEquals($initialStatusId, $updatedOrder['statusId']);

        // Verify the status change persisted
        $verifyOrder = $this->getItem('/orders/' . $orderId, ['order_read']);
        $this->assertEquals(2, $verifyOrder['statusId']);

        return $orderId;
    }

    /**
     * @depends testUpdateOrderStatus
     */
    public function testListOrders(int $orderId): void
    {
        $paginatedOrders = $this->listItems('/orders', ['order_read']);

        $this->assertArrayHasKey('totalItems', $paginatedOrders);
        $this->assertArrayHasKey('items', $paginatedOrders);
        $this->assertGreaterThan(0, $paginatedOrders['totalItems']);

        // Check that our created order is in the list
        $orderFound = false;
        foreach ($paginatedOrders['items'] as $order) {
            if ($order['orderId'] === $orderId) {
                $orderFound = true;
                $this->assertArrayHasKey('reference', $order);
                $this->assertArrayHasKey('statusId', $order);
                $this->assertArrayHasKey('status', $order);
                $this->assertArrayHasKey('customerId', $order);
                $this->assertArrayHasKey('dateAdd', $order);
                $this->assertArrayHasKey('totalPaidTaxIncl', $order);
                break;
            }
        }

        $this->assertTrue($orderFound, 'Created order should be found in the list');
    }

    /**
     * @depends testListOrders
     */
    public function testListOrdersWithFilters(): void
    {
        // Test filtering by status
        $filters = ['status_id' => 2];
        $paginatedOrders = $this->listItems('/orders', ['order_read'], $filters);

        $this->assertArrayHasKey('items', $paginatedOrders);
        foreach ($paginatedOrders['items'] as $order) {
            $this->assertEquals(2, $order['statusId']);
        }

        // Test filtering by date range
        $today = date('Y-m-d');
        $filters = ['date_from' => $today, 'date_to' => $today];
        $paginatedOrders = $this->listItems('/orders', ['order_read'], $filters);

        $this->assertArrayHasKey('items', $paginatedOrders);
        foreach ($paginatedOrders['items'] as $order) {
            $orderDate = date('Y-m-d', strtotime($order['dateAdd']));
            $this->assertEquals($today, $orderDate);
        }
    }

    public function testCreateOrderWithInvalidCart(): void
    {
        $orderData = [
            'cartId' => 99999,
            'employeeId' => 1,
            'paymentModuleName' => 'ps_checkpayment',
            'orderStateId' => 1,
        ];

        $this->createItem('/orders', $orderData, ['order_write'], 422);
    }

    public function testCreateOrderWithMissingData(): void
    {
        $orderData = [
            'cartId' => 1,
            // Missing required fields like employeeId, paymentModuleName, orderStateId
        ];

        $response = $this->createItem('/orders', $orderData, ['order_write'], 422);

        $this->assertArrayHasKey('violations', $response);
        $this->assertNotEmpty($response['violations']);
    }

    public function testGetNonExistentOrder(): void
    {
        $this->getItem('/orders/99999', ['order_read'], 404);
    }

    public function testUpdateOrderStatusWithInvalidStatus(): void
    {
        // First create an order
        $createdCart = $this->createItem('/carts', [
            'customerId' => 1,
        ], ['cart_write']);
        $cartId = $createdCart['cartId'];

        $this->partialUpdateItem('/carts/' . $cartId . '/products', [
            'productId' => 1,
            'quantity' => 1,
        ], ['cart_write']);

        $createdOrder = $this->createItem('/orders', [
            'cartId' => $cartId,
            'employeeId' => 1,
            'paymentModuleName' => 'ps_checkpayment',
            'orderStateId' => 1,
        ], ['order_write']);

        $orderId = $createdOrder['orderId'];

        // Try to update with invalid status
        $this->partialUpdateItem('/orders/' . $orderId, [
            'statusId' => 99999,
        ], ['order_write'], 422);
    }

    public function testUpdateNonExistentOrder(): void
    {
        $this->partialUpdateItem('/orders/99999', [
            'statusId' => 2,
        ], ['order_write'], 404);
    }
}
