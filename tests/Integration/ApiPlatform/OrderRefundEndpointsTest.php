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

class OrderRefundEndpointsTest extends ApiTestCase
{
    private static int $orderId;
    private static int $orderDetailId;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::createApiClient(['order_write']);

        // Pick the first seeded order that has at least one order_detail row,
        // then bring it into a paid state so a standard refund is allowed
        // (paying the order also triggers invoice generation, which the
        // IssueStandardRefundCommand handler needs).
        $row = \Db::getInstance()->getRow(
            'SELECT o.id_order, od.id_order_detail
               FROM `' . _DB_PREFIX_ . 'orders` o
               INNER JOIN `' . _DB_PREFIX_ . 'order_detail` od
                       ON od.id_order = o.id_order
              LIMIT 1'
        );
        self::$orderId = (int) $row['id_order'];
        self::$orderDetailId = (int) $row['id_order_detail'];

        $order = new \Order(self::$orderId);
        $order->setCurrentState((int) \Configuration::get('PS_OS_PAYMENT'));
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        DatabaseDump::restoreTables([
            'orders',
            'order_detail',
            'order_history',
            'order_invoice',
            'order_payment',
            'order_slip',
            'order_slip_detail',
        ]);
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'issue standard refund endpoint' => ['POST', '/orders/1/refunds'];
        yield 'issue return product endpoint' => ['POST', '/orders/1/product-returns'];
    }

    public function testIssueStandardRefundRejectsMissingOrderDetailRefunds(): void
    {
        $this->createItem(
            '/orders/1/refunds',
            [
                'orderDetailRefunds' => [],
                'refundShippingCost' => false,
                'generateCreditSlip' => false,
                'generateVoucher' => false,
                'voucherRefundType' => 1,
            ],
            ['order_write'],
            Response::HTTP_UNPROCESSABLE_ENTITY
        );
    }

    public function testIssueProductReturnRejectsMissingOrderDetailRefunds(): void
    {
        $this->createItem(
            '/orders/1/product-returns',
            [
                'orderDetailRefunds' => [],
                'restockRefundedProducts' => false,
                'refundShippingCost' => false,
                'generateCreditSlip' => false,
                'generateVoucher' => false,
                'voucherRefundType' => 1,
            ],
            ['order_write'],
            Response::HTTP_UNPROCESSABLE_ENTITY
        );
    }

    public function testIssueStandardRefundGeneratesCreditSlip(): void
    {
        $slipsBefore = (int) \Db::getInstance()->getValue(
            'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'order_slip`
              WHERE id_order = ' . self::$orderId
        );

        $this->createItem(
            '/orders/' . self::$orderId . '/refunds',
            [
                'orderDetailRefunds' => [
                    (string) self::$orderDetailId => ['quantity' => 1],
                ],
                'refundShippingCost' => false,
                'generateCreditSlip' => true,
                'generateVoucher' => false,
                'voucherRefundType' => 1,
            ],
            ['order_write'],
            Response::HTTP_CREATED
        );

        $slipsAfter = (int) \Db::getInstance()->getValue(
            'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'order_slip`
              WHERE id_order = ' . self::$orderId
        );
        $this->assertSame(
            $slipsBefore + 1,
            $slipsAfter,
            'a credit slip row should be created for the refunded order'
        );
    }
}
