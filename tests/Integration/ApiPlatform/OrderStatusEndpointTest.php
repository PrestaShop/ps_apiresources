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

class OrderStatusEndpointTest extends ApiTestCase
{
    private static int $originalMailMethod;

    private static int $orderId;

    private static int $originalState;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::createApiClient(['order_write']);

        // Disable real email sending so the status-change email succeeds without an SMTP server.
        self::$originalMailMethod = (int) \Configuration::get('PS_MAIL_METHOD');
        \Configuration::updateValue('PS_MAIL_METHOD', \Mail::METHOD_DISABLE);

        // Use an order whose current state is "logable" and only move it between logable states,
        // so the transition never adjusts stock.
        self::$orderId = (int) \Db::getInstance()->getValue(
            'SELECT o.`id_order` FROM `' . _DB_PREFIX_ . 'orders` o
             INNER JOIN `' . _DB_PREFIX_ . 'order_state` os ON os.`id_order_state` = o.`current_state`
             WHERE os.`logable` = 1 ORDER BY o.`id_order` DESC'
        );
        self::$originalState = (int) (new \Order(self::$orderId))->getCurrentState();
    }

    public static function tearDownAfterClass(): void
    {
        // Restore the order state (while mail is still disabled), then restore the mail method.
        (new \Order(self::$orderId))->setCurrentState(self::$originalState);
        \Configuration::updateValue('PS_MAIL_METHOD', self::$originalMailMethod);

        parent::tearDownAfterClass();
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'update order status endpoint' => ['PUT', '/orders/1/status'];
    }

    public function testUpdateOrderStatus(): void
    {
        $newStateId = (int) \Db::getInstance()->getValue(
            'SELECT `id_order_state` FROM `' . _DB_PREFIX_ . 'order_state`
             WHERE `deleted` = 0 AND `logable` = 1 AND `id_order_state` <> ' . self::$originalState . '
             ORDER BY `id_order_state` ASC'
        );

        $this->updateItem(
            '/orders/' . self::$orderId . '/status',
            ['newOrderStatusId' => $newStateId],
            ['order_write'],
            Response::HTTP_NO_CONTENT
        );

        $this->assertSame($newStateId, (int) (new \Order(self::$orderId))->getCurrentState());
    }
}
