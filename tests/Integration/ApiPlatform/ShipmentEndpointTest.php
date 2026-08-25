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
    private static int $secondCarrierId;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        // The Shipment domain is only shipped on PrestaShop 9.2+; skip the whole class on older versions
        // instead of letting the scopes below fail (they don't resolve to any route there).
        if (!class_exists(GetShipmentsForOrderDetail::class)) {
            self::markTestSkipped('Shipment domain does not exist on this PrestaShop version');
        }

        self::createApiClient(['shipment_read', 'shipment_write']);

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

        $carrierRows = \Db::getInstance()->executeS(
            'SELECT `id_carrier` FROM `' . _DB_PREFIX_ . 'carrier` WHERE `deleted` = 0 AND `active` = 1 ORDER BY `id_carrier` ASC LIMIT 2'
        );
        self::$carrierId = (int) $carrierRows[0]['id_carrier'];
        self::$secondCarrierId = (int) ($carrierRows[1]['id_carrier'] ?? $carrierRows[0]['id_carrier']);
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        DatabaseDump::restoreTables(['shipment', 'shipment_product']);
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'create endpoint' => ['POST', '/orders/1/shipments'];
        yield 'get endpoint' => ['GET', '/orders/1/shipments/1'];
        yield 'add product endpoint' => ['POST', '/orders/1/shipments/1/products'];
        yield 'switch carrier endpoint' => ['PATCH', '/shipments/1/carriers'];
        yield 'fulfill endpoint' => ['PATCH', '/shipments/1/fulfill'];
    }

    /**
     * Inserted directly rather than through the create endpoint, so that the read tests do not
     * depend on the write semantics still being discussed in
     * https://github.com/PrestaShop/PrestaShop/issues/42397.
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

    public function testCreateShipment(): void
    {
        $response = $this->createItem('/orders/' . self::$orderId . '/shipments', [
            'carrierId' => self::$carrierId,
            'productId' => self::$productId,
            'quantity' => 1,
        ], ['shipment_write']);

        $this->assertArrayHasKey('shipmentId', $response);
        $this->assertEquals(self::$orderId, $response['orderId']);
        $this->assertEquals(self::$carrierId, $response['carrierId']);
        $this->assertEquals('', $response['trackingNumber']);
        // The handler does not attach the product yet, see
        // https://github.com/PrestaShop/PrestaShop/issues/42397
        $this->assertEmpty($response['selectedProducts']);
    }

    public function testCreateShipmentWithoutCarrier(): void
    {
        $response = $this->createItem(
            '/orders/' . self::$orderId . '/shipments',
            [
                'productId' => self::$productId,
                'quantity' => 1,
            ],
            ['shipment_write'],
            Response::HTTP_UNPROCESSABLE_ENTITY
        );

        $this->assertValidationErrors([
            ['propertyPath' => 'carrierId', 'message' => 'This value should not be null.'],
        ], $response);
    }

    public function testAddProductToShipment(): void
    {
        $shipmentId = $this->createFixtureShipment();

        $response = $this->createItem('/orders/' . self::$orderId . '/shipments/' . $shipmentId . '/products', [
            'productId' => self::$productId,
        ], ['shipment_write']);

        $this->assertEquals($shipmentId, $response['shipmentId']);
        $this->assertArrayHasKey(self::$productId, $response['selectedProducts']);
    }

    public function testSwitchShipmentCarrier(): void
    {
        $shipmentId = $this->createFixtureShipment();

        $response = $this->partialUpdateItem(
            '/shipments/' . $shipmentId . '/carriers',
            ['carrierId' => self::$secondCarrierId],
            ['shipment_write'],
            Response::HTTP_NO_CONTENT
        );
        $this->assertNull($response);

        $shipment = $this->getItem('/orders/' . self::$orderId . '/shipments/' . $shipmentId, ['shipment_read']);
        $this->assertEquals(self::$secondCarrierId, $shipment['carrierId']);
    }

    public function testFulfillShipment(): void
    {
        $shipmentId = $this->createFixtureShipment();

        $response = $this->partialUpdateItem(
            '/shipments/' . $shipmentId . '/fulfill',
            ['trackingNumber' => 'TRACK-12345'],
            ['shipment_write'],
            Response::HTTP_NO_CONTENT
        );
        $this->assertNull($response);

        $shipment = $this->getItem('/orders/' . self::$orderId . '/shipments/' . $shipmentId, ['shipment_read']);
        $this->assertEquals('TRACK-12345', $shipment['trackingNumber']);
    }

    public function testFulfillShipmentNotFound(): void
    {
        $this->partialUpdateItem(
            '/shipments/999999/fulfill',
            ['trackingNumber' => 'TRACK-99999'],
            ['shipment_write'],
            Response::HTTP_NOT_FOUND
        );
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
