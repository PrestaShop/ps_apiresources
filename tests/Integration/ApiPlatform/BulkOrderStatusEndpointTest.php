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

class BulkOrderStatusEndpointTest extends ApiTestCase
{
    private static int $originalMailMethod;

    /** @var array<int, int> orderId => original state id */
    private static array $originalStates = [];

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::createApiClient(['order_write']);

        // Disable real email sending so the status-change emails succeed without an SMTP server.
        self::$originalMailMethod = (int) \Configuration::get('PS_MAIL_METHOD');
        \Configuration::updateValue('PS_MAIL_METHOD', \Mail::METHOD_DISABLE);

        // Put two orders in a known logable, non-error state so the bulk change moves them to a
        // different logable state (never touching stock, never hitting "already assigned").
        $orders = \Db::getInstance()->executeS(
            'SELECT `id_order` FROM `' . _DB_PREFIX_ . 'orders` ORDER BY `id_order` DESC LIMIT 2'
        );
        foreach ($orders ?: [] as $order) {
            $orderId = (int) $order['id_order'];
            self::$originalStates[$orderId] = (int) \Db::getInstance()->getValue(
                'SELECT `id_order_state` FROM `' . _DB_PREFIX_ . 'order_history`
                 WHERE `id_order` = ' . $orderId . ' ORDER BY `id_order_history` DESC'
            );
            (new \Order($orderId))->setCurrentState((int) \Configuration::get('PS_OS_PAYMENT'));
        }
    }

    public static function tearDownAfterClass(): void
    {
        foreach (self::$originalStates as $orderId => $originalState) {
            (new \Order($orderId))->setCurrentState($originalState);
        }
        \Configuration::updateValue('PS_MAIL_METHOD', self::$originalMailMethod);

        parent::tearDownAfterClass();
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'bulk update order status endpoint' => ['PUT', '/orders/bulk-update-status'];
    }

    public function testBulkChangeOrderStatus(): void
    {
        $newStateId = (int) \Configuration::get('PS_OS_PREPARATION');
        $orderIds = array_keys(self::$originalStates);

        $this->updateItem(
            '/orders/bulk-update-status',
            ['orderIds' => $orderIds, 'newOrderStatusId' => $newStateId],
            ['order_write'],
            Response::HTTP_NO_CONTENT
        );

        foreach ($orderIds as $orderId) {
            $this->assertSame($newStateId, (int) (new \Order($orderId))->getCurrentState());
        }
    }
}
