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

class OrderShippingDetailsEndpointTest extends ApiTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::createApiClient(['order_write']);
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'update order shipping details endpoint' => ['PUT', '/orders/1/shipping-details'];
    }

    public function testUpdateOrderShippingDetails(): void
    {
        // Reuse an existing order carrier row. We send back the same carrier and
        // tracking number so the command runs without changing the carrier (no
        // shipping-cost recalculation) and without sending the "in transit" email
        // (only sent when the tracking number actually changes) - neither of which
        // is available in the test environment.
        $orderCarrier = \Db::getInstance()->getRow(
            'SELECT `id_order`, `id_order_carrier`, `id_carrier`, `tracking_number`
             FROM `' . _DB_PREFIX_ . 'order_carrier` ORDER BY `id_order_carrier` ASC'
        );

        $orderId = (int) $orderCarrier['id_order'];
        $orderCarrierId = (int) $orderCarrier['id_order_carrier'];
        $trackingNumber = (string) $orderCarrier['tracking_number'];

        $this->updateItem(
            '/orders/' . $orderId . '/shipping-details',
            [
                'currentOrderCarrierId' => $orderCarrierId,
                'newCarrierId' => (int) $orderCarrier['id_carrier'],
                'trackingNumber' => $trackingNumber,
            ],
            ['order_write'],
            Response::HTTP_NO_CONTENT
        );

        $reloaded = new \OrderCarrier($orderCarrierId);
        $this->assertSame($trackingNumber, (string) $reloaded->tracking_number);
    }
}
