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

use Tests\Resources\DatabaseDump;

class AvailableShipmentsEndpointTest extends ApiTestCase
{
    private static int $orderId;
    private static int $orderDetailId;
    private static int $productId;
    private static int $productQuantity;
    private static int $carrierId;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
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
        yield 'shipments for order detail endpoint' => ['GET', '/orders/1/order-details/1/shipments'];
        yield 'available shipments endpoint' => ['GET', '/orders/1/available-shipments'];
        yield 'available shipments for product endpoint' => ['GET', '/orders/1/products/1/available-shipments'];
    }

    public function testCreateFixtureShipment(): int
    {
        $shipment = $this->createItem('/orders/' . self::$orderId . '/shipments', [
            'carrierId' => self::$carrierId,
            'productId' => self::$productId,
            'quantity' => 1,
        ], ['shipment_write']);
        $shipmentId = $shipment['shipmentId'];

        // CreateShipment only creates the shipment shell; the product is attached separately.
        $this->createItem('/orders/' . self::$orderId . '/shipments/' . $shipmentId . '/products', [
            'productId' => self::$productId,
        ], ['shipment_write']);

        return $shipmentId;
    }

    /**
     * @depends testCreateFixtureShipment
     */
    public function testGetShipmentsForOrderDetail(int $shipmentId): void
    {
        $response = $this->getItem(
            '/orders/' . self::$orderId . '/order-details/' . self::$orderDetailId . '/shipments',
            ['shipment_read']
        );

        $this->assertIsArray($response);
        $found = null;
        foreach ($response as $entry) {
            if ($entry['shipmentId'] === $shipmentId) {
                $found = $entry;
                break;
            }
        }
        $this->assertNotNull($found, 'Expected shipment not found in GetShipmentsForOrderDetail response');
        $this->assertEquals(self::$productQuantity, $found['quantity']);
    }

    /**
     * @depends testCreateFixtureShipment
     */
    public function testListAvailableShipments(int $shipmentId): void
    {
        $response = $this->getItem(
            '/orders/' . self::$orderId . '/available-shipments',
            ['shipment_read'],
            null,
            [
                'extra' => [
                    'parameters' => [
                        'orderDetailIds' => [self::$orderDetailId],
                    ],
                ],
            ]
        );

        $this->assertIsArray($response);
        $found = null;
        foreach ($response as $entry) {
            if ($entry['shipmentId'] === $shipmentId) {
                $found = $entry;
                break;
            }
        }
        $this->assertNotNull($found, 'Expected shipment not found in ListAvailableShipments response');
        $this->assertArrayHasKey('shipmentName', $found);
        $this->assertArrayHasKey('compatible', $found);
    }

    /**
     * @depends testCreateFixtureShipment
     */
    public function testListAvailableShipmentsForProduct(int $shipmentId): void
    {
        $response = $this->getItem(
            '/orders/' . self::$orderId . '/products/' . self::$productId . '/available-shipments',
            ['shipment_read']
        );

        $this->assertIsArray($response);
        $found = null;
        foreach ($response as $entry) {
            if ($entry['shipmentId'] === $shipmentId) {
                $found = $entry;
                break;
            }
        }
        $this->assertNotNull($found, 'Expected shipment not found in ListAvailableShipmentsForProduct response');
        $this->assertArrayHasKey('shipmentName', $found);
    }
}
