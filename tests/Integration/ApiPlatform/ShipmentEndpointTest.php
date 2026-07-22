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

class ShipmentEndpointTest extends ApiTestCase
{
    private static int $orderId;
    private static int $productId;
    private static int $carrierId;
    private static int $secondCarrierId;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::createApiClient(['shipment_read', 'shipment_write']);

        $orderRow = \Db::getInstance()->getRow(
            'SELECT `id_order` FROM `' . _DB_PREFIX_ . 'orders` ORDER BY `id_order` ASC'
        );
        self::$orderId = (int) $orderRow['id_order'];

        $orderDetailRow = \Db::getInstance()->getRow(
            'SELECT `product_id` FROM `' . _DB_PREFIX_ . 'order_detail` WHERE `id_order` = ' . self::$orderId . ' ORDER BY `id_order_detail` ASC'
        );
        self::$productId = (int) $orderDetailRow['product_id'];

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

    public function testCreateShipment(): int
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
        $this->assertEmpty($response['selectedProducts']);

        return $response['shipmentId'];
    }

    /**
     * @depends testCreateShipment
     */
    public function testGetShipment(int $shipmentId): int
    {
        $response = $this->getItem('/orders/' . self::$orderId . '/shipments/' . $shipmentId, ['shipment_read']);

        $this->assertEquals(self::$orderId, $response['orderId']);
        $this->assertEquals($shipmentId, $response['shipmentId']);
        $this->assertEquals(self::$carrierId, $response['carrierId']);
        $this->assertEquals('', $response['trackingNumber']);
        $this->assertEmpty($response['selectedProducts']);

        return $shipmentId;
    }

    /**
     * @depends testGetShipment
     */
    public function testAddProductToShipment(int $shipmentId): int
    {
        $response = $this->createItem('/orders/' . self::$orderId . '/shipments/' . $shipmentId . '/products', [
            'productId' => self::$productId,
        ], ['shipment_write']);

        $this->assertEquals($shipmentId, $response['shipmentId']);
        $this->assertArrayHasKey(self::$productId, $response['selectedProducts']);

        return $shipmentId;
    }

    /**
     * @depends testAddProductToShipment
     */
    public function testSwitchShipmentCarrier(int $shipmentId): int
    {
        $response = $this->partialUpdateItem(
            '/shipments/' . $shipmentId . '/carriers',
            ['carrierId' => self::$secondCarrierId],
            ['shipment_write'],
            Response::HTTP_NO_CONTENT
        );
        $this->assertNull($response);

        $shipment = $this->getItem('/orders/' . self::$orderId . '/shipments/' . $shipmentId, ['shipment_read']);
        $this->assertEquals(self::$secondCarrierId, $shipment['carrierId']);

        return $shipmentId;
    }

    /**
     * @depends testSwitchShipmentCarrier
     */
    public function testFulfillShipment(int $shipmentId): void
    {
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

    public function testCreateShipmentInvalid(): void
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

    public function testFulfillShipmentNotFound(): void
    {
        $this->partialUpdateItem(
            '/shipments/999999/fulfill',
            ['trackingNumber' => 'TRACK-99999'],
            ['shipment_write'],
            Response::HTTP_NOT_FOUND
        );
    }
}
