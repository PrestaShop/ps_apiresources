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

class OrderPartialRefundEndpointTest extends ApiTestCase
{
    private static int $originalMailMethod;

    private static int $orderId;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::createApiClient(['order_write']);

        // Disable real email sending so the refund email succeeds without an SMTP server.
        self::$originalMailMethod = (int) \Configuration::get('PS_MAIL_METHOD');
        \Configuration::updateValue('PS_MAIL_METHOD', \Mail::METHOD_DISABLE);

        // Use an order that is ALREADY delivered (has a delivery-flagged state in its history):
        // partial refund is allowed on it, and with restockRefundedProducts=false it never
        // reinjects stock. We don't change its state ourselves (delivering an order would
        // decrement stock, which is not possible without an employee in the API context).
        self::$orderId = (int) \Db::getInstance()->getValue(
            'SELECT oh.`id_order`
             FROM `' . _DB_PREFIX_ . 'order_history` oh
             INNER JOIN `' . _DB_PREFIX_ . 'order_state` os ON os.`id_order_state` = oh.`id_order_state`
             WHERE os.`delivery` = 1
             GROUP BY oh.`id_order`
             ORDER BY oh.`id_order` DESC'
        );
    }

    public static function tearDownAfterClass(): void
    {
        \Configuration::updateValue('PS_MAIL_METHOD', self::$originalMailMethod);

        parent::tearDownAfterClass();
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'issue order partial refund endpoint' => ['POST', '/orders/1/partial-refunds'];
    }

    public function testIssuePartialRefund(): void
    {
        if (self::$orderId === 0) {
            $this->markTestSkipped('No delivered order available in the fixtures.');
        }

        $orderDetailId = (int) \Db::getInstance()->getValue(
            'SELECT `id_order_detail` FROM `' . _DB_PREFIX_ . 'order_detail`
             WHERE `id_order` = ' . self::$orderId . ' ORDER BY `id_order_detail` ASC'
        );

        $this->createItem(
            '/orders/' . self::$orderId . '/partial-refunds',
            [
                'orderDetailRefunds' => [
                    $orderDetailId => ['quantity' => 1, 'amount' => '1.00'],
                ],
                'shippingCostRefundAmount' => '0',
                'restockRefundedProducts' => false,
                'generateCreditSlip' => false,
                'generateVoucher' => false,
                'voucherRefundType' => 0,
            ],
            ['order_write'],
            Response::HTTP_NO_CONTENT
        );
    }

    public function testIssuePartialRefundWithEmptyOrderDetailRefunds(): void
    {
        if (self::$orderId === 0) {
            $this->markTestSkipped('No delivered order available in the fixtures.');
        }

        $validationErrorsResponse = $this->createItem(
            '/orders/' . self::$orderId . '/partial-refunds',
            [
                'orderDetailRefunds' => [],
                'shippingCostRefundAmount' => '0',
                'restockRefundedProducts' => false,
                'generateCreditSlip' => false,
                'generateVoucher' => false,
                'voucherRefundType' => 0,
            ],
            ['order_write'],
            Response::HTTP_UNPROCESSABLE_ENTITY
        );
        $this->assertIsArray($validationErrorsResponse);
        $this->assertValidationErrors([
            [
                'propertyPath' => 'orderDetailRefunds',
                'message' => 'This value should not be blank.',
            ],
        ], $validationErrorsResponse);
    }
}
