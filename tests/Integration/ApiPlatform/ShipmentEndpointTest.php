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

use PrestaShop\PrestaShop\Core\Domain\Shipment\Query\GetShipmentsForOrderDetail;
use Symfony\Component\HttpFoundation\Response;
use Tests\Resources\DatabaseDump;

class ShipmentEndpointTest extends ApiTestCase
{
    private static int $orderId;
    private static int $orderDetailId;
    private static int $productId;
    private static int $productQuantity;
    private static int $carrierId;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        // The Shipment domain is only shipped on PrestaShop 9.2+; skip the whole class on older versions
        // instead of letting the scopes below fail (they don't resolve to any route there).
        if (!class_exists(GetShipmentsForOrderDetail::class)) {
            self::markTestSkipped('Shipment domain does not exist on this PrestaShop version');
        }

        self::createApiClient(['shipment_read']);

        $orderRow = \Db::getInstance()->getRow(
            'SELECT `id_order` FROM `' . _DB_PREFIX_ . 'orders` ORDER BY `id_order` ASC'
        );
        self::$orderId = (int) $orderRow['id_order'];

        $orderDetailRow = \Db::getInstance()->getRow(
            'SELECT `id_order_detail`, `product_id`, `product_quantity` FROM `' . _DB_PREFIX_ . 'order_detail` WHERE `id_order` = ' . self::$orderId . ' ORDER BY `id_order_detail` ASC'
        );
        self::$orderDetailId = (int) $orderDetailRow['id_order_detail'];
        self::$productId = (int) $orderDetailRow['product_id'];
        self::$productQuantity = (int) $orderDetailRow['product_quantity'];

        $carrierRow = \Db::getInstance()->getRow(
            'SELECT `id_carrier` FROM `' . _DB_PREFIX_ . 'carrier` WHERE `deleted` = 0 AND `active` = 1 ORDER BY `id_carrier` ASC'
        );
        self::$carrierId = (int) $carrierRow['id_carrier'];
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        DatabaseDump::restoreTables(['shipment', 'shipment_product']);
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'get endpoint' => ['GET', '/orders/1/shipments/1'];
    }

    /**
     * Putting a product in a shipment is not exposed yet, and the CQRS commands that do it are
     * the subject of https://github.com/PrestaShop/PrestaShop/issues/42397, so the fixture is
     * inserted directly rather than built through a contract that is still moving.
     */
    private function createFixtureShipment(): int
    {
        $db = \Db::getInstance();
        $addressId = (int) $db->getValue(
            'SELECT `id_address_delivery` FROM `' . _DB_PREFIX_ . 'orders` WHERE `id_order` = ' . self::$orderId
        );

        $db->execute(
            'INSERT INTO `' . _DB_PREFIX_ . 'shipment`
                (`id_order`, `id_carrier`, `id_delivery_address`, `deleted`, `date_add`, `date_upd`)
             VALUES (' . self::$orderId . ', ' . self::$carrierId . ', ' . $addressId . ', 0, NOW(), NOW())'
        );
        $shipmentId = (int) $db->Insert_ID();

        $db->execute(
            'INSERT INTO `' . _DB_PREFIX_ . 'shipment_product` (`id_shipment`, `id_order_detail`, `quantity`)
             VALUES (' . $shipmentId . ', ' . self::$orderDetailId . ', ' . self::$productQuantity . ')'
        );

        return $shipmentId;
    }

    public function testGetShipment(): void
    {
        $shipmentId = $this->createFixtureShipment();

        $response = $this->getItem('/orders/' . self::$orderId . '/shipments/' . $shipmentId, ['shipment_read']);

        $this->assertEquals(self::$orderId, $response['orderId']);
        $this->assertEquals($shipmentId, $response['shipmentId']);
        $this->assertEquals(self::$carrierId, $response['carrierId']);
        $this->assertEquals('', $response['trackingNumber']);
        $this->assertArrayHasKey(self::$productId, $response['selectedProducts']);
        // Reported as 0 for every product until PrestaShop/PrestaShop#42092 lands
        $this->assertSame(self::$productQuantity, $response['selectedProducts'][self::$productId]);
    }

    public function testGetShipmentNotFound(): void
    {
        $this->getItem(
            '/orders/' . self::$orderId . '/shipments/999999',
            ['shipment_read'],
            Response::HTTP_NOT_FOUND
        );
    }
}
