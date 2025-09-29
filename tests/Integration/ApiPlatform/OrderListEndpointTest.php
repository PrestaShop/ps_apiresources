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

class OrderListEndpointTest extends ApiTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        CustomerResetter::resetCustomers();
        OrderResetter::resetOrders();
        // Pre-create the API Client with the needed scopes
        self::createApiClient(['order_read', 'order_write', 'cart_write', 'cart_read']);
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
    }

    public function testListOrders(): void
    {
        // First create some orders to list
        $this->createTestOrder();
        $this->createTestOrder();

        $paginatedOrders = $this->listItems('/orders', ['order_read']);

        $this->assertArrayHasKey('totalItems', $paginatedOrders);
        $this->assertArrayHasKey('items', $paginatedOrders);
        $this->assertArrayHasKey('sortOrder', $paginatedOrders);
        $this->assertArrayHasKey('limit', $paginatedOrders);
        $this->assertArrayHasKey('filters', $paginatedOrders);

        $this->assertIsInt($paginatedOrders['totalItems']);
        $this->assertIsArray($paginatedOrders['items']);
        $this->assertGreaterThanOrEqual(2, $paginatedOrders['totalItems']);

        // Verify structure of order list items
        if (!empty($paginatedOrders['items'])) {
            $firstOrder = $paginatedOrders['items'][0];
            $this->assertArrayHasKey('orderId', $firstOrder);
            $this->assertArrayHasKey('reference', $firstOrder);
            $this->assertArrayHasKey('status', $firstOrder);
            $this->assertArrayHasKey('statusId', $firstOrder);
            $this->assertArrayHasKey('shopId', $firstOrder);
            $this->assertArrayHasKey('customerId', $firstOrder);
            $this->assertArrayHasKey('currencyIso', $firstOrder);
            $this->assertArrayHasKey('dateAdd', $firstOrder);
            $this->assertArrayHasKey('totalPaidTaxIncl', $firstOrder);
            $this->assertArrayHasKey('totalProductsTaxIncl', $firstOrder);
        }
    }

    public function testListOrdersWithStatusFilter(): void
    {
        // Create an order with specific status
        $orderId = $this->createTestOrder();

        // Update its status to 2 (Payment accepted)
        $this->partialUpdateItem('/orders/' . $orderId, [
            'statusId' => 2,
        ], ['order_write']);

        // Filter by status
        $filters = ['statusId' => 2];
        $paginatedOrders = $this->listItems('/orders', ['order_read'], $filters);

        $this->assertArrayHasKey('items', $paginatedOrders);

        // All returned orders should have status ID 2
        foreach ($paginatedOrders['items'] as $order) {
            $this->assertEquals(2, $order['statusId']);
        }
    }

    public function testListOrdersWithCustomerFilter(): void
    {
        // Create orders for specific customer
        $orderId1 = $this->createTestOrder(1);
        $orderId2 = $this->createTestOrder(1);

        // Filter by customer ID
        $filters = ['customerId' => 1];
        $paginatedOrders = $this->listItems('/orders', ['order_read'], $filters);

        $this->assertArrayHasKey('items', $paginatedOrders);
        $this->assertGreaterThanOrEqual(2, count($paginatedOrders['items']));

        // All returned orders should belong to customer ID 1
        foreach ($paginatedOrders['items'] as $order) {
            $this->assertEquals(1, $order['customerId']);
        }
    }

    public function testListOrdersWithDateFilter(): void
    {
        // Create test order
        $this->createTestOrder();

        $today = date('Y-m-d');
        $filters = [
            'dateFrom' => $today,
            'dateTo' => $today,
        ];

        $paginatedOrders = $this->listItems('/orders', ['order_read'], $filters);

        $this->assertArrayHasKey('items', $paginatedOrders);

        // All returned orders should be from today
        foreach ($paginatedOrders['items'] as $order) {
            $orderDate = date('Y-m-d', strtotime($order['dateAdd']));
            $this->assertEquals($today, $orderDate);
        }
    }

    public function testListOrdersWithReferenceSearch(): void
    {
        // Create test order
        $orderId = $this->createTestOrder();

        // Get the order to find its reference
        $order = $this->getItem('/orders/' . $orderId, ['order_read']);
        $reference = $order['reference'];

        // Search by reference
        $filters = ['q' => $reference];
        $paginatedOrders = $this->listItems('/orders', ['order_read'], $filters);

        $this->assertArrayHasKey('items', $paginatedOrders);
        $this->assertGreaterThanOrEqual(1, count($paginatedOrders['items']));

        // At least one order should match our reference
        $found = false;
        foreach ($paginatedOrders['items'] as $order) {
            if ($order['reference'] === $reference) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found, 'Order with reference should be found in search results');
    }

    public function testListOrdersPagination(): void
    {
        // Create several test orders
        for ($i = 0; $i < 5; $i++) {
            $this->createTestOrder();
        }

        // Test with small limit
        $filters = ['limit' => 2];
        $paginatedOrders = $this->listItems('/orders', ['order_read'], $filters);

        $this->assertArrayHasKey('items', $paginatedOrders);
        $this->assertArrayHasKey('totalItems', $paginatedOrders);
        $this->assertLessThanOrEqual(2, count($paginatedOrders['items']));
        $this->assertGreaterThanOrEqual(5, $paginatedOrders['totalItems']);
    }

    public function testListOrdersWithInvalidFilter(): void
    {
        // Test with invalid status ID - should not crash
        $filters = ['statusId' => 99999];
        $paginatedOrders = $this->listItems('/orders', ['order_read'], $filters);

        $this->assertArrayHasKey('items', $paginatedOrders);
        // Should return empty results, not error
        $this->assertIsArray($paginatedOrders['items']);
    }

    public function testListOrdersWithoutScopes(): void
    {
        // Test without proper scopes - should return 403
        $bearerToken = $this->getBearerToken([]);
        static::createClient()->request('GET', '/orders', ['auth_bearer' => $bearerToken]);
        self::assertResponseStatusCodeSame(403);
    }

    /**
     * Helper method to create a test order
     */
    private function createTestOrder(int $customerId = 1): int
    {
        // First create a cart with products
        $createdCart = $this->createItem('/carts', [
            'customerId' => $customerId,
        ], ['cart_write']);
        $cartId = $createdCart['cartId'];

        // Add a product to the cart
        $this->partialUpdateItem('/carts/' . $cartId . '/products', [
            'productId' => 1,
            'quantity' => 1,
        ], ['cart_write']);

        // Create order from cart
        $orderData = [
            'cartId' => $cartId,
            'employeeId' => 1,
            'paymentModuleName' => 'ps_checkpayment',
            'orderStateId' => 1,
            'orderMessage' => 'Test order created via API for list testing',
        ];

        $createdOrder = $this->createItem('/orders', $orderData, ['order_write']);

        return $createdOrder['orderId'];
    }
}