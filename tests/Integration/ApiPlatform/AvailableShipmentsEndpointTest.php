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

use PrestaShop\PrestaShop\Core\Domain\Shipment\Command\AddProductToShipment;
use PrestaShop\PrestaShop\Core\Domain\Shipment\Command\CreateShipment;
use PrestaShop\PrestaShop\Core\Domain\Shipment\Query\GetShipmentsForOrderDetail;
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
        yield 'shipments for order detail endpoint' => ['GET', '/orders/1/order-details/1/shipments'];
        yield 'available shipments endpoint' => ['GET', '/orders/1/available-shipments'];
        yield 'available shipments for product endpoint' => ['GET', '/orders/1/products/1/available-shipments'];
    }

    /**
     * The shipment write endpoints are not exposed yet, the fixture goes through the CQRS
     * commands directly so the read endpoints can be covered in the meantime.
     */
    public function testCreateFixtureShipment(): int
    {
        $commandBus = static::createClient()->getContainer()->get('prestashop.core.command_bus');

        $shipmentId = $commandBus->handle(
            new CreateShipment(self::$orderId, self::$carrierId, self::$productId, 1)
        )->getValue();

        $commandBus->handle(
            new AddProductToShipment($shipmentId, self::$productId, self::$orderId)
        );

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
