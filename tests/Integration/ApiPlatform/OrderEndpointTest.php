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

use PrestaShop\Module\APIResources\ApiPlatform\Resources\Order\Event\OrderTrackingUpdatedEvent;
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
            '/orders/1/cart-rules',
        ];
        yield 'update order note' => [
            'PATCH',
            '/orders/1/note',
        ];
        yield 'resend order email' => [
            'POST',
            '/orders/1/resend-email',
        ];
        yield 'cancel order products' => [
            'POST',
            '/orders/1/cancellations',
        ];
        yield 'issue order refund' => [
            'POST',
            '/orders/1/refunds',
        ];
        yield 'bulk update order status' => [
            'POST',
            '/orders/status-bulk',
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

    public function testPatchOrderStatusUsingCode(): void
    {
        $orderId = 1;
        $order = new \Order($orderId);
        $originalStatusId = (int) $order->current_state;

        $deliveredCode = 'PS_OS_DELIVERED';
        $deliveredId = (int) \Configuration::get($deliveredCode);
        if ($deliveredId === $originalStatusId) {
            $deliveredCode = 'PS_OS_CANCELED';
            $deliveredId = (int) \Configuration::get($deliveredCode);
        }

        $this->partialUpdateItem('/orders/' . $orderId . '/status', [
            'statusId' => $originalStatusId,
            'statusCode' => $deliveredCode,
        ], ['order_write'], Response::HTTP_NO_CONTENT);

        $updatedOrder = new \Order($orderId);
        $this->assertEquals($deliveredId, (int) $updatedOrder->current_state);

        // Restore original status to avoid side effects
        $this->partialUpdateItem('/orders/' . $orderId . '/status', [
            'statusId' => $originalStatusId,
        ], ['order_write'], Response::HTTP_NO_CONTENT);
    }

    public function testPatchStatusOrderNotFound(): void
    {
        $this->partialUpdateItem('/orders/999999/status', [
            'statusId' => 1,
        ], ['order_write'], Response::HTTP_NOT_FOUND);
    }

    public function testPatchTrackingOrderNotFound(): void
    {
        $this->partialUpdateItem('/orders/999999/tracking', [
            'number' => 'TRACK-001',
        ], ['order_write'], Response::HTTP_NOT_FOUND);
    }

    public function testPatchOrderTracking(): void
    {
        $orderId = 1;
        $order = new \Order($orderId);
        $originalCarrierId = (int) $order->id_carrier;

        $newCarrierId = $originalCarrierId === 1 ? 2 : 1;
        $carrier = new \Carrier($newCarrierId);
        if (!$carrier->id) {
            $this->markTestSkipped('Target carrier not available');
        }

        $trackingNumber = 'TRACK-123456';
        $event = null;
        static::getContainer()->get('event_dispatcher')->addListener(
            OrderTrackingUpdatedEvent::class,
            function (OrderTrackingUpdatedEvent $e) use (&$event) {
                $event = $e;
            }
        );

        $this->partialUpdateItem('/orders/' . $orderId . '/tracking', [
            'newCarrierId' => $newCarrierId,
            'trackingNumber' => $trackingNumber,
        ], ['order_write'], Response::HTTP_NO_CONTENT);

        $updatedOrder = new \Order($orderId);
        $this->assertEquals($newCarrierId, (int) $updatedOrder->id_carrier);
        $this->assertEquals($trackingNumber, $updatedOrder->shipping_number);

        $this->assertInstanceOf(OrderTrackingUpdatedEvent::class, $event);
        $this->assertEquals($orderId, $event->getOrderId());
        $this->assertEquals($trackingNumber, $event->getTrackingNumber());
    }

    public function testPatchOrderNote(): void
    {
        $note = 'Internal note';
        $this->partialUpdateItem('/orders/1/note', [
            'note' => $note,
        ], ['order_write'], Response::HTTP_NO_CONTENT);

        $order = new \Order(1);
        $this->assertEquals($note, $order->note);
    }

    public function testPatchOrderNoteNotFound(): void
    {
        $this->partialUpdateItem('/orders/999999/note', [
            'note' => 'irrelevant',
        ], ['order_write'], Response::HTTP_NOT_FOUND);
    }

    public function testPatchOrderCurrency(): void
    {
        $order = new \Order(1);
        $originalCurrency = (int) $order->id_currency;
        $newCurrency = $originalCurrency === 1 ? 2 : 1;

        $currency = new \Currency($newCurrency);
        if (!$currency->id) {
            $this->markTestSkipped('Target currency not available');
        }

        $this->partialUpdateItem('/orders/1/currency', [
            'currencyId' => $newCurrency,
        ], ['order_write'], Response::HTTP_NO_CONTENT);

        $updatedOrder = new \Order(1);
        $this->assertEquals($newCurrency, (int) $updatedOrder->id_currency);

        $this->partialUpdateItem('/orders/1/currency', [
            'currencyId' => $originalCurrency,
        ], ['order_write'], Response::HTTP_NO_CONTENT);
    }

    public function testPatchOrderCurrencyNotFound(): void
    {
        $this->partialUpdateItem('/orders/999999/currency', [
            'currencyId' => 1,
        ], ['order_write'], Response::HTTP_NOT_FOUND);
    }

    public function testPatchOrderAddresses(): void
    {
        $originalAddressId = $this->createTestAddress('original');
        $newDeliveryAddressId = $this->createTestAddress('delivery');
        $newInvoiceAddressId = $this->createTestAddress('invoice');

        $cart = new \Cart();
        $cart->id_customer = 1;
        $cart->id_lang = 1;
        $cart->id_currency = 1;
        $cart->id_shop = 1;
        $cart->id_address_delivery = $originalAddressId;
        $cart->id_address_invoice = $originalAddressId;
        $cart->id_carrier = 1;
        $customer = new \Customer(1);
        $cart->secure_key = $customer->secure_key;
        $cart->add();
        $cart->updateQty(1, 1);

        $created = $this->createItem('/orders', [
            'cartId' => (int) $cart->id,
            'employeeId' => 1,
            'orderMessage' => 'Address test order',
            'paymentModuleName' => 'ps_wirepayment',
            'orderStateId' => 2,
        ], ['order_write']);

        $orderId = (int) $created['orderId'];

        $this->partialUpdateItem('/orders/' . $orderId . '/delivery-address', [
            'addressId' => $newDeliveryAddressId,
        ], ['order_write'], Response::HTTP_NO_CONTENT);

        $this->partialUpdateItem('/orders/' . $orderId . '/invoice-address', [
            'addressId' => $newInvoiceAddressId,
        ], ['order_write'], Response::HTTP_NO_CONTENT);

        $order = $this->getItem('/orders/' . $orderId, ['order_read']);
        $this->assertEquals($newDeliveryAddressId, $order['deliveryAddressId']);
        $this->assertEquals($newInvoiceAddressId, $order['invoiceAddressId']);
    }

    public function testPatchDeliveryAddressOrderNotFound(): void
    {
        $this->partialUpdateItem('/orders/999999/delivery-address', [
            'addressId' => 1,
        ], ['order_write'], Response::HTTP_NOT_FOUND);
    }

    public function testPatchDeliveryAddressAddressNotFound(): void
    {
        $this->partialUpdateItem('/orders/1/delivery-address', [
            'addressId' => 999999,
        ], ['order_write'], Response::HTTP_NOT_FOUND);
    }

    public function testPatchInvoiceAddressOrderNotFound(): void
    {
        $this->partialUpdateItem('/orders/999999/invoice-address', [
            'addressId' => 1,
        ], ['order_write'], Response::HTTP_NOT_FOUND);
    }

    public function testPatchInvoiceAddressAddressNotFound(): void
    {
        $this->partialUpdateItem('/orders/1/invoice-address', [
            'addressId' => 999999,
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

        $this->createItem('/orders/1/cart-rules', [
            'cartRuleId' => $discount['discountId'],
            'amount' => '1.00',
        ], ['order_write'], Response::HTTP_NO_CONTENT);

        $orderAfter = $this->getItem('/orders/1', ['order_read']);
        $this->assertLessThan($totalBefore, (float) $orderAfter['totalPaidTaxIncl']);
    }

    public function testResendOrderEmail(): void
    {
        $orderId = 1;
        $row = \Db::getInstance()->getRow(
            'SELECT id_order_history, id_order_state FROM `' . _DB_PREFIX_ . "order_history` WHERE id_order = $orderId ORDER BY id_order_history DESC"
        );

        $this->createItem('/orders/' . $orderId . '/resend-email', [
            'statusId' => (int) $row['id_order_state'],
            'historyId' => (int) $row['id_order_history'],
        ], ['order_write'], Response::HTTP_NO_CONTENT);
    }

    public function testResendOrderEmailOrderNotFound(): void
    {
        $this->createItem('/orders/999999/resend-email', [
            'statusId' => 1,
            'historyId' => 1,
        ], ['order_write'], Response::HTTP_NOT_FOUND);
    }

    public function testCancelOrderProductUpdatesStockAndTotals(): void
    {
        $productId = 1;
        $quantity = 2;

        $stockBeforeOrder = \StockAvailable::getQuantityAvailableByProduct($productId);

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
        $cart->updateQty($quantity, $productId);

        $created = $this->createItem('/orders', [
            'cartId' => (int) $cart->id,
            'employeeId' => 1,
            'orderMessage' => 'Cancellation test order',
            'paymentModuleName' => 'ps_wirepayment',
            'orderStateId' => 2,
        ], ['order_write']);

        $orderId = (int) $created['orderId'];

        $stockAfterOrder = \StockAvailable::getQuantityAvailableByProduct($productId);
        $this->assertEquals($stockBeforeOrder - $quantity, $stockAfterOrder);

        $order = $this->getItem('/orders/' . $orderId, ['order_read']);
        $this->assertNotEmpty($order['items']);
        $orderDetailId = (int) $order['items'][0]['orderDetailId'];
        $totalBefore = (float) $order['totalPaidTaxIncl'];

        $this->createItem('/orders/' . $orderId . '/cancellations', [
            'items' => [
                $orderDetailId => 1,
            ],
        ], ['order_write'], Response::HTTP_NO_CONTENT);

        $stockAfterCancel = \StockAvailable::getQuantityAvailableByProduct($productId);
        $this->assertEquals($stockAfterOrder + 1, $stockAfterCancel);

        $orderAfter = $this->getItem('/orders/' . $orderId, ['order_read']);
        $this->assertLessThan($totalBefore, (float) $orderAfter['totalPaidTaxIncl']);
    }

    public function testIssueOrderRefund(): void
    {
        $productId = 1;
        $quantity = 1;

        $stockBeforeOrder = \StockAvailable::getQuantityAvailableByProduct($productId);

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
        $cart->updateQty($quantity, $productId);

        $created = $this->createItem('/orders', [
            'cartId' => (int) $cart->id,
            'employeeId' => 1,
            'orderMessage' => 'Refund test order',
            'paymentModuleName' => 'ps_wirepayment',
            'orderStateId' => 2,
        ], ['order_write']);

        $orderId = (int) $created['orderId'];
        $order = $this->getItem('/orders/' . $orderId, ['order_read']);
        $orderDetailId = (int) $order['items'][0]['orderDetailId'];

        $stockAfterOrder = \StockAvailable::getQuantityAvailableByProduct($productId);
        $this->assertEquals($stockBeforeOrder - $quantity, $stockAfterOrder);

        $this->createItem('/orders/' . $orderId . '/refunds', [
            'orderDetailRefunds' => [
                $orderDetailId => 1,
            ],
            'refundShippingCost' => false,
            'generateCreditSlip' => true,
            'generateVoucher' => false,
            'voucherRefundType' => 0,
        ], ['order_write'], Response::HTTP_NO_CONTENT);

        $stockAfterRefund = \StockAvailable::getQuantityAvailableByProduct($productId);
        $this->assertEquals($stockBeforeOrder, $stockAfterRefund);

        $orderSlips = \OrderSlip::getOrdersSlip($orderId);
        $this->assertNotEmpty($orderSlips);
    }

    public function testIssueOrderRefundOrderNotFound(): void
    {
        $this->createItem('/orders/999999/refunds', [
            'orderDetailRefunds' => [1 => 1],
            'refundShippingCost' => false,
            'generateCreditSlip' => true,
            'generateVoucher' => false,
            'voucherRefundType' => 0,
        ], ['order_write'], Response::HTTP_NOT_FOUND);
    }

    public function testBulkChangeOrderStatus(): void
    {
        // Create an additional order to update in the bulk request
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
            'orderMessage' => 'Bulk status test order',
            'paymentModuleName' => 'ps_wirepayment',
            'orderStateId' => 2,
        ], ['order_write']);

        $secondOrderId = (int) $created['orderId'];

        $this->createItem('/orders/status-bulk', [
            'orderIds' => [1, $secondOrderId],
            'statusId' => 2,
        ], ['order_write'], Response::HTTP_NO_CONTENT);

        $order1 = $this->getItem('/orders/1', ['order_read']);
        $order2 = $this->getItem('/orders/' . $secondOrderId, ['order_read']);
        $this->assertEquals(2, $order1['statusId']);
        $this->assertEquals(2, $order2['statusId']);
    }

    public function testBulkChangeOrderStatusInvalidIds(): void
    {
        $validationErrors = $this->createItem('/orders/status-bulk', [
            'orderIds' => ['foo', 0],
            'statusId' => 0,
        ], ['order_write'], Response::HTTP_UNPROCESSABLE_ENTITY);

        $this->assertIsArray($validationErrors);
        $this->assertValidationErrors([
            ['propertyPath' => 'orderIds[0]'],
            ['propertyPath' => 'orderIds[1]'],
            ['propertyPath' => 'statusId'],
        ], $validationErrors);
    }

    private function createTestAddress(string $alias): int
    {
        $address = new \Address();
        $address->id_country = 1;
        $address->alias = $alias;
        $address->firstname = 'John';
        $address->lastname = 'Doe';
        $address->address1 = $alias . ' street';
        $address->city = 'Paris';
        $address->postcode = '75000';
        $address->id_customer = 1;
        $address->phone = '0000000000';
        $address->add();

        return (int) $address->id;
    }
}
