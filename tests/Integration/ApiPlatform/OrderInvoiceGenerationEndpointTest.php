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

class OrderInvoiceGenerationEndpointTest extends ApiTestCase
{
    private static int $orderId;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::createApiClient(['order_write']);

        // Pick an order that has no invoice yet.
        self::$orderId = (int) \Db::getInstance()->getValue(
            'SELECT o.`id_order` FROM `' . _DB_PREFIX_ . 'orders` o
             WHERE NOT EXISTS (
                 SELECT 1 FROM `' . _DB_PREFIX_ . 'order_invoice` oi WHERE oi.`id_order` = o.`id_order`
             )
             ORDER BY o.`id_order` ASC'
        );
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        DatabaseDump::restoreTables(['orders', 'order_invoice', 'order_detail']);
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'generate invoice endpoint' => ['PUT', '/orders/1/invoices'];
    }

    public function testGenerateOrderInvoice(): void
    {
        $this->updateItem(
            '/orders/' . self::$orderId . '/invoices',
            [],
            ['order_write'],
            Response::HTTP_NO_CONTENT
        );

        $invoiceCount = (int) \Db::getInstance()->getValue(
            'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'order_invoice` WHERE `id_order` = ' . self::$orderId
        );
        $this->assertGreaterThanOrEqual(1, $invoiceCount);
    }
}
