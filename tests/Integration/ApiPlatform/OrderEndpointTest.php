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

/**
 * The orders are the five fixture orders of the test shop: an order cannot be created through the API yet.
 *
 * @todo create the order of the tests with POST /orders once AddOrderFromBackOfficeCommand can be built from a request
 *       body (PrestaShop/PrestaShop#42067)
 */
class OrderEndpointTest extends ApiTestCase
{
    // Fixture order "OHSATSERP" of John DOE, awaiting check payment, with two product lines
    private const ORDER_ID = 2;

    private const DATE_TIME_FORMAT = '#^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$#';

    private const ORDER_TABLES = [
        'orders',
        'order_detail',
        'order_detail_tax',
        'order_history',
        'order_carrier',
        'order_cart_rule',
        'order_invoice',
        'order_invoice_payment',
        'order_invoice_tax',
        'order_payment',
        'cart',
        'cart_product',
        'cart_cart_rule',
        'cart_rule',
        'cart_rule_lang',
        'cart_rule_shop',
        'address',
        'stock_available',
        'stock_mvt',
        'mail',
    ];

    private static int $mailMethod;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        DatabaseDump::restoreTables(self::ORDER_TABLES);
        self::createApiClient(['order_read', 'order_write', 'address_read', 'address_write']);
        // The status changes send e-mails to the customer
        self::$mailMethod = (int) \Configuration::get('PS_MAIL_METHOD');
        self::updateConfiguration('PS_MAIL_METHOD', \Mail::METHOD_DISABLE);
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        DatabaseDump::restoreTables(self::ORDER_TABLES);
        self::updateConfiguration('PS_MAIL_METHOD', self::$mailMethod);
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'resend email endpoint' => ['POST', '/orders/1/resend-email'];
        yield 'set invoice note endpoint' => ['PATCH', '/orders/invoices/1'];
        // The operations returning the order are filtered out on older cores, where they answer 404 instead of 401
        if (self::isVersionAtLeast('9.2.2')) {
            yield 'get endpoint' => ['GET', '/orders/1'];
            yield 'set internal note endpoint' => ['PATCH', '/orders/1/set-internal-note'];
            yield 'set invoice address endpoint' => ['PATCH', '/orders/1/set-invoice-address'];
            yield 'set delivery address endpoint' => ['PATCH', '/orders/1/set-delivery-address'];
            yield 'set currency endpoint' => ['PATCH', '/orders/1/set-currency'];
            yield 'update status endpoint' => ['PATCH', '/orders/1/update-status'];
            yield 'update carrier endpoint' => ['PATCH', '/orders/1/carriers/1'];
            yield 'update product endpoint' => ['PATCH', '/orders/1/products/1'];
            yield 'remove product endpoint' => ['DELETE', '/orders/1/products/1'];
            yield 'add discount endpoint' => ['POST', '/orders/1/discounts'];
            yield 'remove discount endpoint' => ['DELETE', '/orders/1/discounts/1'];
            yield 'add payment endpoint' => ['POST', '/orders/1/payments'];
            yield 'generate invoice endpoint' => ['POST', '/orders/1/invoices'];
        }
    }

    public function testGetOrder(): void
    {
        $this->markTestSkippedByMinVersion('9.2.2');

        $order = $this->getOrder(self::ORDER_ID);
        $this->assertMatchesRegularExpression(self::DATE_TIME_FORMAT, $order['createdAt']);
        $this->assertCount(1, $order['history']);
        $this->assertMatchesRegularExpression(self::DATE_TIME_FORMAT, $order['history'][0]['createdAt']);

        $this->assertEquals([
            'orderId' => self::ORDER_ID,
            'reference' => 'OHSATSERP',
            'customerId' => 1,
            'cartId' => 2,
            'shopId' => 1,
            'currencyId' => 1,
            'carrierId' => 2,
            'carrierName' => 'My carrier',
            'orderStateId' => (int) \Configuration::get('PS_OS_CHEQUE'),
            'valid' => false,
            'paid' => false,
            'shipped' => false,
            'delivered' => false,
            'virtual' => false,
            'taxIncluded' => true,
            'refundable' => false,
            'payment' => 'Payment by check',
            'paymentModuleName' => 'ps_checkpayment',
            'internalNote' => '',
            'invoiceAddressId' => $order['deliveryAddressId'],
            'deliveryAddressId' => $order['deliveryAddressId'],
            'createdAt' => $order['createdAt'],
            'prices' => [
                'productsPrice' => 169.9,
                'discountsAmount' => 0.0,
                'wrappingPrice' => 0.0,
                'shippingPrice' => 0.0,
                'shippingRefundableAmount' => 0.0,
                'taxesAmount' => 0.0,
                'totalAmount' => 169.9,
            ],
            'products' => [
                [
                    'orderDetailId' => 3,
                    'productId' => 4,
                    'combinationId' => $order['products'][0]['combinationId'],
                    'name' => 'The adventure begins Framed poster - Dimension : 80x120cm',
                    'reference' => 'demo_5',
                    'quantity' => 2,
                    'unitPriceTaxExcluded' => 79.0,
                    'unitPriceTaxIncluded' => 79.0,
                    'quantityRefunded' => 0,
                    'orderInvoiceId' => null,
                ],
                [
                    'orderDetailId' => 4,
                    'productId' => 8,
                    'combinationId' => 0,
                    'name' => 'Mug Today is a good day',
                    'reference' => 'demo_13',
                    'quantity' => 1,
                    'unitPriceTaxExcluded' => 11.9,
                    'unitPriceTaxIncluded' => 11.9,
                    'quantityRefunded' => 0,
                    'orderInvoiceId' => null,
                ],
            ],
            'discounts' => [],
            'history' => [
                [
                    'orderHistoryId' => 2,
                    'orderStateId' => (int) \Configuration::get('PS_OS_CHEQUE'),
                    'name' => 'Awaiting check payment',
                    'color' => '#34209E',
                    'createdAt' => $order['history'][0]['createdAt'],
                    'employeeFirstName' => null,
                    'employeeLastName' => null,
                    'apiClientId' => null,
                ],
            ],
            'carriers' => [
                [
                    'orderCarrierId' => 2,
                    'carrierId' => 2,
                    'carrierName' => 'My carrier',
                    'trackingNumber' => '',
                    'trackingUrl' => null,
                ],
            ],
            'payments' => [],
            'documents' => [],
        ], $order);
    }

    public function testGetUnknownOrder(): void
    {
        $this->markTestSkippedByMinVersion('9.2.2');

        $this->getItem('/orders/99999', ['order_read'], Response::HTTP_NOT_FOUND);
        // OrderId accepts 0, an unknown order like any other
        $this->getItem('/orders/0', ['order_read'], Response::HTTP_NOT_FOUND);
    }

    public function testSetInternalNote(): void
    {
        $this->markTestSkippedByMinVersion('9.2.2');

        $order = $this->getOrder(self::ORDER_ID);
        $expectedOrder = array_replace($order, ['internalNote' => 'Leave the parcel at the back door']);

        $updatedOrder = $this->partialUpdateItem('/orders/' . self::ORDER_ID . '/set-internal-note', [
            'internalNote' => 'Leave the parcel at the back door',
        ], ['order_write']);
        $this->assertEquals($expectedOrder, $updatedOrder);
        $this->assertEquals($expectedOrder, $this->getOrder(self::ORDER_ID));

        // An empty note removes it
        $updatedOrder = $this->partialUpdateItem('/orders/' . self::ORDER_ID . '/set-internal-note', [
            'internalNote' => '',
        ], ['order_write']);
        $this->assertEquals($order, $updatedOrder);
    }

    public function testSetInvalidInternalNote(): void
    {
        $this->markTestSkippedByMinVersion('9.2.2');

        foreach ([[], ['internalNote' => null]] as $payload) {
            $errors = $this->partialUpdateItem('/orders/' . self::ORDER_ID . '/set-internal-note', $payload, ['order_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
            $this->assertValidationErrors([
                ['propertyPath' => 'internalNote', 'message' => 'This value should not be null.'],
            ], $errors);
        }

        $errors = $this->partialUpdateItem('/orders/' . self::ORDER_ID . '/set-internal-note', [
            'internalNote' => '<script>alert("note")</script>',
        ], ['order_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertValidationErrors([['propertyPath' => 'internalNote']], $errors);

        $this->partialUpdateItem('/orders/99999/set-internal-note', ['internalNote' => 'Note'], ['order_write'], Response::HTTP_NOT_FOUND);
    }

    public function testSetAddresses(): void
    {
        $this->markTestSkippedByMinVersion('9.2.2');

        $order = $this->getOrder(self::ORDER_ID);
        // A copy of the current address of the order (same country and state, so the same taxes), created through the API
        $currentAddress = $this->getItem('/addresses/customers/' . $order['deliveryAddressId'], ['address_read']);
        $addressPayload = $currentAddress;
        unset($addressPayload['addressId']);
        $newAddress = $this->createItem('/addresses/customers', array_replace($addressPayload, [
            'addressAlias' => 'Office',
            'address' => '42 Office street',
        ]), ['address_write']);
        $newAddressId = $newAddress['addressId'];

        $expectedOrder = array_replace($order, ['invoiceAddressId' => $newAddressId]);
        $updatedOrder = $this->partialUpdateItem('/orders/' . self::ORDER_ID . '/set-invoice-address', [
            'invoiceAddressId' => $newAddressId,
        ], ['order_write']);
        $this->assertEquals($expectedOrder, $updatedOrder);
        $this->assertEquals($expectedOrder, $this->getOrder(self::ORDER_ID));

        $expectedOrder = array_replace($expectedOrder, ['deliveryAddressId' => $newAddressId]);
        $updatedOrder = $this->partialUpdateItem('/orders/' . self::ORDER_ID . '/set-delivery-address', [
            'deliveryAddressId' => $newAddressId,
        ], ['order_write']);
        $this->assertEquals($expectedOrder, $updatedOrder);
        $this->assertEquals($expectedOrder, $this->getOrder(self::ORDER_ID));
    }

    public function testSetInvalidAddresses(): void
    {
        $this->markTestSkippedByMinVersion('9.2.2');

        foreach (['set-invoice-address' => 'invoiceAddressId', 'set-delivery-address' => 'deliveryAddressId'] as $action => $field) {
            $uri = '/orders/' . self::ORDER_ID . '/' . $action;
            $errors = $this->partialUpdateItem($uri, [], ['order_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
            $this->assertValidationErrors([['propertyPath' => $field, 'message' => 'This value should not be null.']], $errors);

            $errors = $this->partialUpdateItem($uri, [$field => 0], ['order_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
            $this->assertValidationErrors([['propertyPath' => $field, 'message' => 'This value should be positive.']], $errors);

            // The core reports an unknown address as an invalid one
            $this->partialUpdateItem($uri, [$field => 99999], ['order_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
            $this->partialUpdateItem('/orders/99999/' . $action, [$field => 1], ['order_write'], Response::HTTP_NOT_FOUND);
        }
    }

    /**
     * @todo test a currency change once a second currency can be created through the API (POST /currencies,
     *       PrestaShop/ps_apiresources#423)
     */
    public function testSetInvalidCurrency(): void
    {
        $this->markTestSkippedByMinVersion('9.2.2');

        $uri = '/orders/' . self::ORDER_ID . '/set-currency';
        $order = $this->getOrder(self::ORDER_ID);

        $errors = $this->partialUpdateItem($uri, [], ['order_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertValidationErrors([['propertyPath' => 'currencyId', 'message' => 'This value should not be null.']], $errors);
        $errors = $this->partialUpdateItem($uri, ['currencyId' => 0], ['order_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertValidationErrors([['propertyPath' => 'currencyId', 'message' => 'This value should be positive.']], $errors);

        // The current currency, and an unknown one, are refused
        $this->partialUpdateItem($uri, ['currencyId' => $order['currencyId']], ['order_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->partialUpdateItem($uri, ['currencyId' => 99999], ['order_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->partialUpdateItem('/orders/99999/set-currency', ['currencyId' => 1], ['order_write'], Response::HTTP_NOT_FOUND);

        $this->assertEquals($order, $this->getOrder(self::ORDER_ID));
    }

    public function testUpdateStatusAndResendEmail(): void
    {
        $this->markTestSkippedByMinVersion('9.2.2');

        $order = $this->getOrder(self::ORDER_ID);
        // Payment error: a status without invoice, payment nor stock movement
        $paymentErrorStateId = (int) \Configuration::get('PS_OS_ERROR');

        $updatedOrder = $this->partialUpdateItem('/orders/' . self::ORDER_ID . '/update-status', [
            'orderStateId' => $paymentErrorStateId,
        ], ['order_write']);
        // The new status comes first in the history, recorded with the API client that changed it
        $newEntry = $updatedOrder['history'][0];
        $this->assertGreaterThan($order['history'][0]['orderHistoryId'], $newEntry['orderHistoryId']);
        $this->assertMatchesRegularExpression(self::DATE_TIME_FORMAT, $newEntry['createdAt']);
        $expectedOrder = array_replace($order, [
            'orderStateId' => $paymentErrorStateId,
            'history' => array_merge([
                [
                    'orderHistoryId' => $newEntry['orderHistoryId'],
                    'orderStateId' => $paymentErrorStateId,
                    'name' => 'Payment error',
                    'color' => '#E74C3C',
                    'createdAt' => $newEntry['createdAt'],
                    'employeeFirstName' => null,
                    'employeeLastName' => null,
                    'apiClientId' => md5(implode(',', ['order_read', 'order_write', 'address_read', 'address_write'])),
                ],
            ], $order['history']),
        ]);
        $this->assertEquals($expectedOrder, $updatedOrder);
        $this->assertEquals($expectedOrder, $this->getOrder(self::ORDER_ID));

        // The current status cannot be set again
        $this->partialUpdateItem('/orders/' . self::ORDER_ID . '/update-status', [
            'orderStateId' => $paymentErrorStateId,
        ], ['order_write'], Response::HTTP_UNPROCESSABLE_ENTITY);

        // The e-mail of the new status is sent again, the order does not change
        $this->createItem('/orders/' . self::ORDER_ID . '/resend-email', [
            'orderHistoryId' => $newEntry['orderHistoryId'],
            'orderStateId' => $paymentErrorStateId,
        ], ['order_write'], Response::HTTP_NO_CONTENT);
        $this->assertEquals($expectedOrder, $this->getOrder(self::ORDER_ID));
    }

    public function testUpdateInvalidStatus(): void
    {
        $this->markTestSkippedByMinVersion('9.2.2');

        $uri = '/orders/' . self::ORDER_ID . '/update-status';
        $errors = $this->partialUpdateItem($uri, [], ['order_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertValidationErrors([['propertyPath' => 'orderStateId', 'message' => 'This value should not be null.']], $errors);
        $errors = $this->partialUpdateItem($uri, ['orderStateId' => 0], ['order_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertValidationErrors([['propertyPath' => 'orderStateId', 'message' => 'This value should be positive.']], $errors);
        // The core reports an unknown status as an invalid one
        $this->partialUpdateItem($uri, ['orderStateId' => 99999], ['order_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->partialUpdateItem('/orders/99999/update-status', ['orderStateId' => 1], ['order_write'], Response::HTTP_NOT_FOUND);
    }

    public function testResendInvalidEmail(): void
    {
        $uri = '/orders/' . self::ORDER_ID . '/resend-email';
        $errors = $this->createItem($uri, [], ['order_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertValidationErrors([
            ['propertyPath' => 'orderHistoryId', 'message' => 'This value should not be null.'],
            ['propertyPath' => 'orderStateId', 'message' => 'This value should not be null.'],
        ], $errors);
        $errors = $this->createItem($uri, ['orderHistoryId' => 0, 'orderStateId' => 0], ['order_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertValidationErrors([
            ['propertyPath' => 'orderHistoryId', 'message' => 'This value should be positive.'],
            ['propertyPath' => 'orderStateId', 'message' => 'This value should be positive.'],
        ], $errors);
        // The core reports an unknown status as an invalid one
        $this->createItem($uri, ['orderHistoryId' => 1, 'orderStateId' => 99999], ['order_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->createItem('/orders/99999/resend-email', ['orderHistoryId' => 1, 'orderStateId' => 1], ['order_write'], Response::HTTP_NOT_FOUND);
    }

    public function testUpdateCarrier(): void
    {
        $this->markTestSkippedByMinVersion('9.2.2');

        $order = $this->getOrder(self::ORDER_ID);
        $orderCarrier = $order['carriers'][0];
        $uri = '/orders/' . self::ORDER_ID . '/carriers/' . $orderCarrier['orderCarrierId'];

        $updatedOrder = $this->partialUpdateItem($uri, [
            'carrierId' => $orderCarrier['carrierId'],
            'trackingNumber' => 'TRACK123456',
        ], ['order_write']);
        $this->assertSame('TRACK123456', $updatedOrder['carriers'][0]['trackingNumber']);
        $expectedOrder = array_replace($order, [
            'carriers' => [
                array_replace($orderCarrier, [
                    'trackingNumber' => 'TRACK123456',
                    // The tracking URL of the carrier with the tracking number, when the carrier has one
                    'trackingUrl' => $updatedOrder['carriers'][0]['trackingUrl'],
                ]),
            ],
        ]);
        $this->assertEquals($expectedOrder, $updatedOrder);
        $this->assertEquals($expectedOrder, $this->getOrder(self::ORDER_ID));

        $errors = $this->partialUpdateItem($uri, ['trackingNumber' => 'TRACK'], ['order_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertValidationErrors([['propertyPath' => 'carrierId', 'message' => 'This value should not be null.']], $errors);
        // Refused by the core
        $this->partialUpdateItem($uri, [
            'carrierId' => $orderCarrier['carrierId'],
            'trackingNumber' => '<script>',
        ], ['order_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->partialUpdateItem('/orders/99999/carriers/' . $orderCarrier['orderCarrierId'], [
            'carrierId' => $orderCarrier['carrierId'],
            'trackingNumber' => 'TRACK',
        ], ['order_write'], Response::HTTP_NOT_FOUND);
    }

    public function testUpdateAndRemoveProduct(): void
    {
        $this->markTestSkippedByMinVersion('9.2.2');

        $order = $this->getOrder(self::ORDER_ID);
        $this->assertCount(2, $order['products']);
        [$poster, $mug] = $order['products'];
        $uri = '/orders/' . self::ORDER_ID . '/products/' . $mug['orderDetailId'];

        $updatedOrder = $this->partialUpdateItem($uri, [
            'quantity' => 3,
            'unitPriceTaxIncluded' => 11.9,
            'unitPriceTaxExcluded' => 11.9,
        ], ['order_write']);
        // The amounts of the order follow the two mugs added
        $this->assertEquals($order['prices']['productsPrice'] + 23.8, $updatedOrder['prices']['productsPrice']);
        $expectedOrder = array_replace($order, [
            'prices' => $updatedOrder['prices'],
            'products' => [$poster, array_replace($mug, ['quantity' => 3])],
        ]);
        $this->assertEquals($expectedOrder, $updatedOrder);
        $this->assertEquals($expectedOrder, $this->getOrder(self::ORDER_ID));

        $updatedOrder = $this->deleteItem($uri, ['order_write'], Response::HTTP_OK);
        $this->assertEquals($order['prices']['productsPrice'] - 11.9, $updatedOrder['prices']['productsPrice']);
        $expectedOrder = array_replace($order, [
            'prices' => $updatedOrder['prices'],
            'products' => [$poster],
        ]);
        $this->assertEquals($expectedOrder, $updatedOrder);
        $this->assertEquals($expectedOrder, $this->getOrder(self::ORDER_ID));

        // The line does not exist anymore
        $this->deleteItem($uri, ['order_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testUpdateInvalidProduct(): void
    {
        $this->markTestSkippedByMinVersion('9.2.2');

        $order = $this->getOrder(self::ORDER_ID);
        $uri = '/orders/' . self::ORDER_ID . '/products/' . $order['products'][0]['orderDetailId'];

        $errors = $this->partialUpdateItem($uri, [], ['order_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertValidationErrors([
            ['propertyPath' => 'quantity', 'message' => 'This value should not be null.'],
            ['propertyPath' => 'unitPriceTaxIncluded', 'message' => 'This value should not be null.'],
            ['propertyPath' => 'unitPriceTaxExcluded', 'message' => 'This value should not be null.'],
        ], $errors);
        $errors = $this->partialUpdateItem($uri, [
            'quantity' => 0,
            'unitPriceTaxIncluded' => 10,
            'unitPriceTaxExcluded' => 10,
        ], ['order_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertValidationErrors([['propertyPath' => 'quantity', 'message' => 'This value should be positive.']], $errors);

        $payload = ['quantity' => 1, 'unitPriceTaxIncluded' => 10, 'unitPriceTaxExcluded' => 10];
        // A line of another order, refused by the core
        $this->partialUpdateItem('/orders/' . self::ORDER_ID . '/products/1', $payload, ['order_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->partialUpdateItem('/orders/99999/products/1', $payload, ['order_write'], Response::HTTP_NOT_FOUND);
        $this->deleteItem('/orders/99999/products/1', ['order_write'], Response::HTTP_NOT_FOUND);
    }

    public function testAddAndRemoveDiscount(): void
    {
        $this->markTestSkippedByMinVersion('9.2.2');

        $order = $this->getOrder(self::ORDER_ID);
        $this->assertSame([], $order['discounts']);

        $updatedOrder = $this->createItem('/orders/' . self::ORDER_ID . '/discounts', [
            'discountName' => 'Loyalty discount',
            'discountType' => 'amount',
            'discountValue' => 5,
        ], ['order_write']);
        $this->assertCount(1, $updatedOrder['discounts']);
        $orderCartRuleId = $updatedOrder['discounts'][0]['orderCartRuleId'];
        $this->assertGreaterThan(0, $orderCartRuleId);
        $this->assertEquals(5.0, $updatedOrder['prices']['discountsAmount']);
        $this->assertEquals($order['prices']['totalAmount'] - 5, $updatedOrder['prices']['totalAmount']);
        $expectedOrder = array_replace($order, [
            'prices' => $updatedOrder['prices'],
            'discounts' => [
                [
                    'orderCartRuleId' => $orderCartRuleId,
                    'name' => 'Loyalty discount',
                    'amount' => 5.0,
                ],
            ],
        ]);
        $this->assertEquals($expectedOrder, $updatedOrder);
        $this->assertEquals($expectedOrder, $this->getOrder(self::ORDER_ID));

        // Removing the discount restores the amounts of the order
        $updatedOrder = $this->deleteItem('/orders/' . self::ORDER_ID . '/discounts/' . $orderCartRuleId, ['order_write'], Response::HTTP_OK);
        $this->assertEquals($order, $updatedOrder);
        $this->assertEquals($order, $this->getOrder(self::ORDER_ID));

        // The discount is not part of the order anymore
        $this->deleteItem('/orders/' . self::ORDER_ID . '/discounts/' . $orderCartRuleId, ['order_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testAddInvalidDiscount(): void
    {
        $this->markTestSkippedByMinVersion('9.2.2');

        $uri = '/orders/' . self::ORDER_ID . '/discounts';
        $errors = $this->createItem($uri, [], ['order_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertValidationErrors([
            ['propertyPath' => 'discountName', 'message' => 'This value should not be blank.'],
            ['propertyPath' => 'discountType', 'message' => 'This value should not be blank.'],
        ], $errors);
        $errors = $this->createItem($uri, [
            'discountName' => 'Discount',
            'discountType' => 'gift',
            'discountValue' => 5,
        ], ['order_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertValidationErrors([['propertyPath' => 'discountType', 'message' => 'The value you selected is not a valid choice.']], $errors);

        // Refused by the core: a percentage above 100, an amount or a percentage without value
        $this->createItem($uri, [
            'discountName' => 'Discount',
            'discountType' => 'percent',
            'discountValue' => 150,
        ], ['order_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->createItem($uri, [
            'discountName' => 'Discount',
            'discountType' => 'amount',
        ], ['order_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->createItem('/orders/99999/discounts', [
            'discountName' => 'Discount',
            'discountType' => 'amount',
            'discountValue' => 5,
        ], ['order_write'], Response::HTTP_NOT_FOUND);
        $this->deleteItem('/orders/99999/discounts/1', ['order_write'], Response::HTTP_NOT_FOUND);
    }

    public function testGenerateInvoiceAndSetItsNote(): void
    {
        $this->markTestSkippedByMinVersion('9.2.2');

        $order = $this->getOrder(self::ORDER_ID);
        $this->assertSame([], $order['documents']);

        $updatedOrder = $this->createItem('/orders/' . self::ORDER_ID . '/invoices', null, ['order_write']);
        $this->assertCount(1, $updatedOrder['documents']);
        $invoice = $updatedOrder['documents'][0];
        $this->assertGreaterThan(0, $invoice['documentId']);
        $this->assertMatchesRegularExpression(self::DATE_TIME_FORMAT, $invoice['createdAt']);
        $this->assertIsString($invoice['referenceNumber']);
        $this->assertIsString($invoice['amount']);
        $this->assertEquals([
            'documentId' => $invoice['documentId'],
            'type' => 'invoice',
            'referenceNumber' => $invoice['referenceNumber'],
            'createdAt' => $invoice['createdAt'],
            'note' => '',
            'amount' => $invoice['amount'],
        ], $invoice);
        // The lines of the order are attached to the invoice
        $expectedOrder = array_replace($order, [
            'products' => array_map(fn (array $product) => array_replace($product, ['orderInvoiceId' => $invoice['documentId']]), $order['products']),
            'documents' => [$invoice],
        ]);
        $this->assertEquals($expectedOrder, $updatedOrder);
        $this->assertEquals($expectedOrder, $this->getOrder(self::ORDER_ID));

        // An order has only one invoice
        $this->createItem('/orders/' . self::ORDER_ID . '/invoices', null, ['order_write'], Response::HTTP_UNPROCESSABLE_ENTITY);

        // The note of the invoice is read back in the documents of the order
        $this->partialUpdateItem('/orders/invoices/' . $invoice['documentId'], ['note' => 'Paid by check on delivery'], ['order_write'], Response::HTTP_NO_CONTENT);
        $expectedOrder['documents'][0]['note'] = 'Paid by check on delivery';
        $this->assertEquals($expectedOrder, $this->getOrder(self::ORDER_ID));
    }

    public function testSetInvalidInvoiceNote(): void
    {
        $errors = $this->partialUpdateItem('/orders/invoices/1', [], ['order_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertValidationErrors([['propertyPath' => 'note', 'message' => 'This value should not be null.']], $errors);
        $errors = $this->partialUpdateItem('/orders/invoices/1', ['note' => '<script>alert("note")</script>'], ['order_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertValidationErrors([['propertyPath' => 'note']], $errors);
        $this->partialUpdateItem('/orders/invoices/99999', ['note' => 'Note'], ['order_write'], Response::HTTP_NOT_FOUND);
        // OrderInvoiceId refuses 0
        $this->partialUpdateItem('/orders/invoices/0', ['note' => 'Note'], ['order_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testAddPayment(): void
    {
        $this->markTestSkippedByMinVersion('9.2.2');

        $order = $this->getOrder(self::ORDER_ID);
        $this->assertSame([], $order['payments']);

        $updatedOrder = $this->createItem('/orders/' . self::ORDER_ID . '/payments', [
            'paymentDate' => '2026-01-15 10:30:00',
            'paymentMethod' => 'Bank wire',
            'paymentAmount' => 50.5,
            'paymentCurrencyId' => $order['currencyId'],
            'transactionId' => 'TRANSACTION-42',
        ], ['order_write']);
        $this->assertCount(1, $updatedOrder['payments']);
        $payment = $updatedOrder['payments'][0];
        $this->assertGreaterThan(0, $payment['orderPaymentId']);
        $this->assertEquals([
            'orderPaymentId' => $payment['orderPaymentId'],
            'paymentDate' => '2026-01-15 10:30:00',
            'paymentMethod' => 'Bank wire',
            'transactionId' => 'TRANSACTION-42',
            'amount' => '$50.50',
            'invoiceNumber' => $payment['invoiceNumber'],
        ], $payment);
        $expectedOrder = array_replace($order, ['payments' => [$payment]]);
        $this->assertEquals($expectedOrder, $updatedOrder);
        $this->assertEquals($expectedOrder, $this->getOrder(self::ORDER_ID));
    }

    public function testAddInvalidPayment(): void
    {
        $this->markTestSkippedByMinVersion('9.2.2');

        $uri = '/orders/' . self::ORDER_ID . '/payments';
        $errors = $this->createItem($uri, [], ['order_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertValidationErrors([
            ['propertyPath' => 'paymentDate', 'message' => 'This value should not be null.'],
            ['propertyPath' => 'paymentMethod', 'message' => 'This value should not be blank.'],
            ['propertyPath' => 'paymentAmount', 'message' => 'This value should not be null.'],
            ['propertyPath' => 'paymentCurrencyId', 'message' => 'This value should not be null.'],
        ], $errors);

        $payload = [
            'paymentDate' => '2026-01-15 10:30:00',
            'paymentMethod' => 'Bank wire',
            'paymentAmount' => 10,
            'paymentCurrencyId' => 1,
        ];
        $errors = $this->createItem($uri, array_replace($payload, ['paymentCurrencyId' => 0, 'employeeId' => -1]), ['order_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertValidationErrors([
            ['propertyPath' => 'paymentCurrencyId', 'message' => 'This value should be positive.'],
            ['propertyPath' => 'employeeId', 'message' => 'This value should be either positive or zero.'],
        ], $errors);
        // Refused by the core: a payment method that is not a name, a negative amount, an unknown currency
        $this->createItem($uri, array_replace($payload, ['paymentMethod' => '<b>Wire</b>']), ['order_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->createItem($uri, array_replace($payload, ['paymentAmount' => -10]), ['order_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->createItem($uri, array_replace($payload, ['paymentCurrencyId' => 99999]), ['order_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->createItem('/orders/99999/payments', $payload, ['order_write'], Response::HTTP_NOT_FOUND);
    }

    private function getOrder(int $orderId): array
    {
        return $this->getItem('/orders/' . $orderId, ['order_read']);
    }
}
