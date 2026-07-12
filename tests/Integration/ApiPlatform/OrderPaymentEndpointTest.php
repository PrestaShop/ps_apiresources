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

class OrderPaymentEndpointTest extends ApiTestCase
{
    private static int $orderId;
    private static string $orderReference;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::createApiClient(['order_write']);

        // An order without an invoice avoids having to provide a valid orderInvoiceId.
        $row = \Db::getInstance()->getRow(
            'SELECT o.`id_order`, o.`reference` FROM `' . _DB_PREFIX_ . 'orders` o
             WHERE NOT EXISTS (
                 SELECT 1 FROM `' . _DB_PREFIX_ . 'order_invoice` oi WHERE oi.`id_order` = o.`id_order`
             )
             ORDER BY o.`id_order` ASC'
        );
        self::$orderId = (int) $row['id_order'];
        self::$orderReference = (string) $row['reference'];
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        DatabaseDump::restoreTables(['order_payment']);
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'add payment endpoint' => ['PUT', '/orders/1/payments'];
    }

    public function testAddPaymentToOrder(): void
    {
        $this->updateItem(
            '/orders/' . self::$orderId . '/payments',
            [
                'paymentDate' => '2026-06-01 10:00:00',
                'paymentMethod' => 'API payment',
                'paymentAmount' => '15.00',
                'paymentCurrencyId' => 1,
                'employeeId' => 1,
            ],
            ['order_write'],
            Response::HTTP_NO_CONTENT
        );

        $count = (int) \Db::getInstance()->getValue(
            'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'order_payment`
             WHERE `order_reference` = "' . pSQL(self::$orderReference) . '" AND `payment_method` = "API payment"'
        );
        $this->assertSame(1, $count);
    }
}
