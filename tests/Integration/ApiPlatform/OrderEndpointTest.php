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

class OrderEndpointTest extends ApiTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        // Pre-create an API Client with needed scopes to reduce token creations
        self::createApiClient(['order_read', 'order_write']);
    }

    public function getProtectedEndpoints(): iterable
    {
        yield 'get order' => [
            'GET',
            '/orders/1',
        ];
        yield 'list orders' => [
            'GET',
            '/orders',
        ];
        yield 'create order' => [
            'POST',
            '/orders',
        ];
        yield 'add cart rule to order' => [
            'POST',
            '/order/1/cart-rules',
        ];
    }

    public function testGetOrder(): void
    {
        $order = $this->getItem('/orders/1', ['order_read']);
        $this->assertIsArray($order);
        $this->assertArrayHasKey('totalPaidTaxExcl', $order);
        $this->assertArrayHasKey('totalProductsTaxExcl', $order);
        $this->assertArrayHasKey('customerId', $order);
        $this->assertIsInt($order['customerId']);
        $this->assertArrayHasKey('deliveryAddressId', $order);
        $this->assertIsInt($order['deliveryAddressId']);
        $this->assertArrayHasKey('invoiceAddressId', $order);
        $this->assertIsInt($order['invoiceAddressId']);
        $this->assertIsArray($order['items']);
        $this->assertArrayHasKey('orderDetailId', $order['items'][0]);
    }

    public function testListOrdersContainsCustomerId(): void
    {
        $orders = $this->listItems('/orders', ['order_read']);
        $this->assertNotEmpty($orders['items']);
        $this->assertArrayHasKey('customerId', $orders['items'][0]);
    }

    public function testGetOrderNotFound(): void
    {
        $this->getItem('/orders/999999', ['order_read'], Response::HTTP_NOT_FOUND);
    }

    public function testCreateOrder(): void
    {
        $cart = new \Cart();
        $cart->id_customer = 1;
        $cart->id_lang = 1;
        $cart->id_currency = 1;
        $cart->id_shop = 1;
        $cart->id_address_delivery = 1;
        $cart->id_address_invoice = 1;
        $cart->id_carrier = 1;
        $customer = new \Customer(1);
        $cart->secure_key = $customer->secure_key;
        $cart->add();
        $cart->updateQty(1, 1);

        $created = $this->createItem('/orders', [
            'cartId' => (int) $cart->id,
            'employeeId' => 1,
            'orderMessage' => 'Test order',
            'paymentModuleName' => 'ps_wirepayment',
            'orderStateId' => 2,
        ], ['order_write']);

        $this->assertArrayHasKey('orderId', $created);
        $orderId = (int) $created['orderId'];
        $order = $this->getItem('/orders/' . $orderId, ['order_read']);
        $this->assertEquals($orderId, $order['orderId']);
    }

    public function testPatchStatusOrderNotFound(): void
    {
        $this->partialUpdateItem('/order/999999/status', [
            'statusId' => 1,
        ], ['order_write'], Response::HTTP_NOT_FOUND);
    }

    public function testPatchTrackingOrderNotFound(): void
    {
        $this->partialUpdateItem('/order/999999/tracking', [
            'number' => 'TRACK-001',
        ], ['order_write'], Response::HTTP_NOT_FOUND);
    }

    public function testAddCartRuleToOrder(): void
    {
        if (!class_exists(\PrestaShop\PrestaShop\Core\Domain\Discount\Command\AddDiscountCommand::class)) {
            $this->markTestSkipped('Discount domain is not available');
        }

        $discount = $this->createItem('/discount', [
            'type' => 'order_level',
            'names' => [
                'en-US' => 'Order voucher',
            ],
        ], ['discount_write']);

        $order = $this->getItem('/orders/1', ['order_read']);
        $totalBefore = (float) $order['totalPaidTaxIncl'];

        $this->createItem('/order/1/cart-rules', [
            'cartRuleId' => $discount['discountId'],
            'amount' => '1.00',
        ], ['order_write'], Response::HTTP_NO_CONTENT);

        $orderAfter = $this->getItem('/orders/1', ['order_read']);
        $this->assertLessThan($totalBefore, (float) $orderAfter['totalPaidTaxIncl']);
    }
}
