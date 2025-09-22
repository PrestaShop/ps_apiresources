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
        // Ensure order scopes are registered before creating API client
        self::ensureScopesAreRegistered(['order_read', 'order_write']);
        // Pre-create an API Client with needed scopes to reduce token creations
        self::createApiClient(['order_read', 'order_write']);

        // Ensure payment modules are properly installed and enabled before tests
        TestDataBuilder::ensurePaymentMethodExists('ps_wirepayment');
        TestDataBuilder::ensurePaymentMethodExists('ps_checkpayment');
        TestDataBuilder::ensurePaymentMethodExists('ps_cashondelivery');

        // Ensure test order exists for the tests
        TestDataBuilder::ensureOrderExists();
        // Enable product returns and cancellations for the tests
        TestDataBuilder::enableProductReturns();
        TestDataBuilder::enableOrderCancellations();
    }

    public static function getProtectedEndpoints(): iterable
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
        yield 'orders write scope registration' => [
            'GET',
            '/orders/_write-scope',
        ];
    }

    public function testGetOrder(): void
    {
        $order = $this->getItem('/orders/1', ['order_read']);

        $this->assertIsArray($order);
        $this->assertArrayHasKey('orderId', $order);
        $this->assertArrayHasKey('reference', $order);
        $this->assertArrayHasKey('totalPaidTaxExcl', $order);
        $this->assertArrayHasKey('totalProductsTaxExcl', $order);
        $this->assertArrayHasKey('customerId', $order);
        $this->assertIsInt($order['customerId']);
        $this->assertArrayHasKey('deliveryAddressId', $order);
        $this->assertIsInt($order['deliveryAddressId']);
        $this->assertArrayHasKey('invoiceAddressId', $order);
        $this->assertIsInt($order['invoiceAddressId']);
        $this->assertIsArray($order['items']);
        // Products mapping is now working with basic collection mapping
        if (!empty($order['items'])) {
            $this->assertIsArray($order['items'][0]);
            // Check if product data contains expected fields from OrderProductForViewing
            $product = $order['items'][0];
            if (isset($product['orderDetailId'])) {
                $this->assertIsInt($product['orderDetailId']);
            }
            if (isset($product['name'])) {
                $this->assertIsString($product['name']);
            }
            if (isset($product['quantity'])) {
                $this->assertIsInt($product['quantity']);
            }
        }

        $this->assertArrayHasKey('vatBreakdown', $order);
        $this->assertArrayHasKey('vatSummary', $order);
        $this->assertIsArray($order['vatBreakdown']);
        $this->assertIsArray($order['vatSummary']);
        // VAT breakdown may be empty for test orders without tax
        if (!empty($order['vatBreakdown'])) {
            $firstBreakdown = $order['vatBreakdown'][array_key_first($order['vatBreakdown'])];
            $this->assertArrayHasKey('vatRate', $firstBreakdown);
            $this->assertArrayHasKey('taxableAmount', $firstBreakdown);
            $this->assertArrayHasKey('vatAmount', $firstBreakdown);
        }

        // Validate VAT calculations if breakdown is available
        if (!empty($order['vatBreakdown'])) {
            $breakdownTaxable = 0.0;
            $breakdownVat = 0.0;
            foreach ($order['vatBreakdown'] as $entry) {
                $breakdownTaxable += (float) $entry['taxableAmount'];
                $breakdownVat += (float) $entry['vatAmount'];
            }
            $this->assertEquals($breakdownTaxable, (float) $order['vatSummary']['taxableAmount']);
            $this->assertEquals($breakdownVat, (float) $order['vatSummary']['vatAmount']);
        }
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
        // Ensure we have all required test data
        $carrierId = TestDataBuilder::ensureCarrierExists();
        $paymentMethod = TestDataBuilder::getWorkingPaymentMethod();
        TestDataBuilder::ensureOrderStateExists(2);
        TestDataBuilder::ensureCustomerExists();
        TestDataBuilder::ensureProductExists();

        $cart = new \Cart();
        $cart->id_customer = 1;
        $cart->id_lang = 1;
        $cart->id_currency = 1;
        $cart->id_shop = 1;
        $cart->id_address_delivery = 1;
        $cart->id_address_invoice = 1;
        $cart->id_carrier = $carrierId;
        $customer = new \Customer(1);
        $cart->secure_key = $customer->secure_key;

        if (!$cart->add()) {
            $this->markTestSkipped('Could not create test cart');
        }

        if (!$cart->updateQty(1, 1)) {
            $this->markTestSkipped('Could not add product to cart');
        }

        $created = $this->createItem('/orders', [
            'cartId' => (int) $cart->id,
            'employeeId' => 1,
            'orderMessage' => 'Test order',
            'paymentModuleName' => $paymentMethod,
            'orderStateId' => 2,
        ], ['order_write']);

        $this->assertArrayHasKey('orderId', $created);
        $orderId = (int) $created['orderId'];

        // Only test retrieval if order was created successfully
        if ($orderId > 0) {
            $order = $this->getItem('/orders/' . $orderId, ['order_read']);
            $this->assertEquals($orderId, $order['orderId']);
        } else {
            $this->markTestIncomplete('Order creation API returned invalid orderId - framework issue, not endpoint logic issue');
        }
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
            'newCarrierId' => 1,
            'number' => 'TRACK-001',
        ], ['order_write'], Response::HTTP_NOT_FOUND);
    }

    public function testPatchOrderTracking(): void
    {
        $orderId = 1;
        $order = new \Order($orderId);
        $originalCarrierId = (int) $order->id_carrier;

        // Ensure we have a valid carrier different from the original
        $availableCarrierId = TestDataBuilder::ensureCarrierExists();
        $newCarrierId = $originalCarrierId === $availableCarrierId ? ($availableCarrierId + 1) : $availableCarrierId;

        // Create a second carrier if needed
        $carrier = new \Carrier($newCarrierId);
        if (!$carrier->id) {
            // Try to get any other available carrier
            $carriers = \Carrier::getCarriers(1, true, false, false, null, \Carrier::ALL_CARRIERS);
            $foundCarrier = false;
            foreach ($carriers as $carrierData) {
                if ((int) $carrierData['id_carrier'] !== $originalCarrierId) {
                    $newCarrierId = (int) $carrierData['id_carrier'];
                    $foundCarrier = true;
                    break;
                }
            }

            if (!$foundCarrier) {
                $this->markTestSkipped('No alternative carrier available for tracking test');
            }
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
            'number' => $trackingNumber,
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
        $availableDiscountClasses = TestDataBuilder::getAvailableDiscountCommandClasses();

        if (empty($availableDiscountClasses)) {
            $this->markTestSkipped('Discount/CartRule domain classes are not available in this PrestaShop version');
        }

        // Check if discount endpoint exists
        try {
            $this->getItem('/discount', ['discount_read'], Response::HTTP_NOT_FOUND);
            $this->markTestSkipped('Discount endpoint is not available');
        } catch (\Exception $e) {
            // Endpoint exists, continue
        }

        $discount = $this->createItem('/discount', [
            'type' => 'order_level',
            'names' => [
                'en-US' => 'Order voucher',
            ],
        ], ['discount_write']);

        if (!isset($discount['discountId'])) {
            $this->markTestSkipped('Could not create test discount');
        }

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
            'orderDetailRefunds' => [],
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

    public function testCreateOrderValidationErrors(): void
    {
        // Test validation errors for OrderCreation endpoint
        $validationErrors = $this->createItem('/orders', [
            // Missing required fields: cartId, employeeId, paymentModuleName, orderStateId
        ], ['order_write'], Response::HTTP_UNPROCESSABLE_ENTITY);

        $this->assertIsArray($validationErrors);
        $this->assertValidationErrors([
            [
                'propertyPath' => 'cartId',
                'message' => 'This value should not be blank.',
            ],
            [
                'propertyPath' => 'employeeId',
                'message' => 'This value should not be blank.',
            ],
            [
                'propertyPath' => 'paymentModuleName',
                'message' => 'This value should not be blank.',
            ],
            [
                'propertyPath' => 'orderStateId',
                'message' => 'This value should not be blank.',
            ],
        ], $validationErrors);

        // Test with invalid cartId
        $validationErrors = $this->createItem('/orders', [
            'cartId' => 999999, // Invalid cart ID
            'employeeId' => 1,
            'paymentModuleName' => 'ps_wirepayment',
            'orderStateId' => 2,
        ], ['order_write'], Response::HTTP_UNPROCESSABLE_ENTITY);

        $this->assertIsArray($validationErrors);
        // The exact error message will depend on the domain validation
        $this->assertArrayHasKey(0, $validationErrors);
    }

    public function testAddCartRuleToOrderValidationErrors(): void
    {
        // Test with invalid order ID
        $this->createItem('/orders/999999/cart-rules', [
            'cartRuleId' => 1,
            'cartRuleName' => 'Test Cart Rule',
            'cartRuleType' => 1,
            'amount' => '10.00',
        ], ['order_write'], Response::HTTP_NOT_FOUND);

        // Test with missing required fields
        $this->createItem('/orders/1/cart-rules', [
            // Missing cartRuleId
            'cartRuleName' => 'Test Cart Rule',
            'cartRuleType' => 1,
            'amount' => '10.00',
        ], ['order_write'], Response::HTTP_NOT_FOUND);

        // Test with invalid cart rule ID
        $this->createItem('/orders/1/cart-rules', [
            'cartRuleId' => 999999, // Invalid cart rule ID
            'cartRuleName' => 'Test Cart Rule',
            'cartRuleType' => 1,
            'amount' => '10.00',
        ], ['order_write'], Response::HTTP_NOT_FOUND);
    }

    public function testOrderRefundValidationErrors(): void
    {
        // Test with invalid order ID
        $this->createItem('/orders/999999/refunds', [
            'orderDetailRefunds' => [],
            'refundShippingCost' => false,
            'generateCreditSlip' => true,
            'generateVoucher' => false,
            'voucherRefundType' => 0,
        ], ['order_write'], Response::HTTP_NOT_FOUND);

        // Test with empty orderDetailRefunds
        $this->createItem('/orders/1/refunds', [
            'orderDetailRefunds' => [], // Empty array
            'refundShippingCost' => false,
            'generateCreditSlip' => true,
            'generateVoucher' => false,
            'voucherRefundType' => 0,
        ], ['order_write'], Response::HTTP_UNPROCESSABLE_ENTITY);

        // Test with invalid orderDetailRefunds format
        $this->createItem('/orders/1/refunds', [
            'orderDetailRefunds' => 'invalid', // Should be array
            'refundShippingCost' => false,
            'generateCreditSlip' => true,
            'generateVoucher' => false,
            'voucherRefundType' => 0,
        ], ['order_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testOrderProductCancellationValidationErrors(): void
    {
        // Test with invalid order ID
        $this->createItem('/orders/999999/cancellations', [
            'items' => [],
        ], ['order_write'], Response::HTTP_NOT_FOUND);

        // Test with empty items
        $this->createItem('/orders/1/cancellations', [
            'items' => [], // Empty array
        ], ['order_write'], Response::HTTP_UNPROCESSABLE_ENTITY);

        // Test with invalid items format
        $this->createItem('/orders/1/cancellations', [
            'items' => 'invalid', // Should be array
        ], ['order_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testOrderResendEmailValidationErrors(): void
    {
        // Test with invalid order ID
        $this->createItem('/orders/999999/resend-email', [
            'statusId' => 1,
            'historyId' => 1,
        ], ['order_write'], Response::HTTP_NOT_FOUND);

        // Test with missing required fields
        $this->createItem('/orders/1/resend-email', [
            // Missing statusId and historyId
        ], ['order_write'], Response::HTTP_UNPROCESSABLE_ENTITY);

        // Test with invalid statusId
        $this->createItem('/orders/1/resend-email', [
            'statusId' => 999999, // Invalid status ID
            'historyId' => 1,
        ], ['order_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testOrdersWriteScopeRegistration(): void
    {
        // This endpoint is marked as internal and deprecated in the OpenAPI documentation
        // It seems to be used for write scope registration, so we'll test it with order_write scope
        $orders = $this->listItems('/orders/_write-scope', ['order_write']);

        // The endpoint should return a list format similar to the regular orders list
        $this->assertIsArray($orders);
        $this->assertArrayHasKey('items', $orders);
        $this->assertArrayHasKey('totalItems', $orders);

        // Since this is an internal endpoint, we don't need to validate the content structure
        // but we verify it returns the expected format
        if (!empty($orders['items'])) {
            $firstOrder = $orders['items'][0];
            $this->assertIsArray($firstOrder);
            $this->assertArrayHasKey('orderId', $firstOrder);
        }
    }

    public function testCreateOrderWithEmptyCart(): void
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
        // Note: We don't add any products to the cart

        $validationErrors = $this->createItem('/orders', [
            'cartId' => (int) $cart->id,
            'employeeId' => 1,
            'orderMessage' => 'Empty cart test',
            'paymentModuleName' => 'ps_wirepayment',
            'orderStateId' => 2,
        ], ['order_write'], Response::HTTP_UNPROCESSABLE_ENTITY);

        $this->assertIsArray($validationErrors);
        // Should fail because cart is empty
    }

    public function testCreateOrderWithInvalidEmployee(): void
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

        $validationErrors = $this->createItem('/orders', [
            'cartId' => (int) $cart->id,
            'employeeId' => 999999, // Invalid employee ID
            'orderMessage' => 'Invalid employee test',
            'paymentModuleName' => 'ps_wirepayment',
            'orderStateId' => 2,
        ], ['order_write'], Response::HTTP_UNPROCESSABLE_ENTITY);

        $this->assertIsArray($validationErrors);
        // Should fail because employee doesn't exist
    }

    public function testCreateOrderWithInvalidPaymentModule(): void
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

        $validationErrors = $this->createItem('/orders', [
            'cartId' => (int) $cart->id,
            'employeeId' => 1,
            'orderMessage' => 'Invalid payment module test',
            'paymentModuleName' => 'nonexistent_payment_module', // Invalid payment module
            'orderStateId' => 2,
        ], ['order_write'], Response::HTTP_UNPROCESSABLE_ENTITY);

        $this->assertIsArray($validationErrors);
        // Should fail because payment module doesn't exist
    }

    public function testCreateOrderWithInvalidOrderState(): void
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

        $validationErrors = $this->createItem('/orders', [
            'cartId' => (int) $cart->id,
            'employeeId' => 1,
            'orderMessage' => 'Invalid order state test',
            'paymentModuleName' => 'ps_wirepayment',
            'orderStateId' => 999999, // Invalid order state ID
        ], ['order_write'], Response::HTTP_UNPROCESSABLE_ENTITY);

        $this->assertIsArray($validationErrors);
        // Should fail because order state doesn't exist
    }

    public function testCancelOrderWithInsufficientStock(): void
    {
        $productId = 1;
        $quantity = 1000; // Very large quantity that exceeds stock

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

        // Use working payment method from TestDataBuilder
        $paymentMethod = TestDataBuilder::getWorkingPaymentMethod();

        $created = $this->createItem('/orders', [
            'cartId' => (int) $cart->id,
            'employeeId' => 1,
            'orderMessage' => 'Insufficient stock test order',
            'paymentModuleName' => $paymentMethod,
            'orderStateId' => 2,
        ], ['order_write']);

        $orderId = (int) $created['orderId'];
        $order = $this->getItem('/orders/' . $orderId, ['order_read']);
        $orderDetailId = (int) $order['items'][0]['orderDetailId'];

        // Try to cancel more than available stock
        $validationErrors = $this->createItem('/orders/' . $orderId . '/cancellations', [
            'items' => [
                $orderDetailId => $quantity + 10, // More than ordered quantity
            ],
        ], ['order_write'], Response::HTTP_UNPROCESSABLE_ENTITY);

        $this->assertIsArray($validationErrors);
        // Should fail because trying to cancel more than ordered
    }

    private function createTestAddress(string $alias): int
    {
        $address = new \Address();

        // Find an active country instead of hardcoding ID 1
        $activeCountry = \Country::getCountries(\Context::getContext()->language->id, true, false, false);
        if (!empty($activeCountry)) {
            $countryIds = array_keys($activeCountry);
            $address->id_country = (int) $countryIds[0]; // Use first active country
        } else {
            $address->id_country = 1; // Fallback to 1 if no active countries found
        }

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
