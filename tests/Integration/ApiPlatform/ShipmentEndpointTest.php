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

use PrestaShop\PrestaShop\Core\Domain\Shipment\Query\GetOrderShipments;
use Symfony\Component\HttpFoundation\Response;
use Tests\Resources\DatabaseDump;

class ShipmentEndpointTest extends ApiTestCase
{
    private static int $orderId;

    private static int $carrierId;

    private static int $addressId;

    /**
     * @var int[]
     */
    private static array $orderDetailIds;

    public static function setUpBeforeClass(): void
    {
        // The Shipment domain only exists since PrestaShop 9.1.0.
        if (!class_exists(GetOrderShipments::class)) {
            self::markTestSkipped('The Shipment domain is only available since PrestaShop 9.1.0.');
        }

        parent::setUpBeforeClass();
        self::createApiClient(['shipment_read', 'shipment_write']);

        // Pick an existing order that has at least one product line.
        $order = \Db::getInstance()->getRow(
            'SELECT o.`id_order`, o.`id_carrier`, o.`id_address_delivery`
             FROM `' . _DB_PREFIX_ . 'orders` o
             INNER JOIN `' . _DB_PREFIX_ . 'order_detail` od ON od.`id_order` = o.`id_order`
             GROUP BY o.`id_order`
             ORDER BY o.`id_order` ASC'
        );

        self::$orderId = (int) $order['id_order'];
        self::$carrierId = (int) $order['id_carrier'] > 0 ? (int) $order['id_carrier'] : 1;
        self::$addressId = (int) $order['id_address_delivery'];

        $orderDetails = \Db::getInstance()->executeS(
            'SELECT `id_order_detail` FROM `' . _DB_PREFIX_ . 'order_detail`
             WHERE `id_order` = ' . self::$orderId . ' ORDER BY `id_order_detail` ASC'
        ) ?: [];
        self::$orderDetailIds = array_map('intval', array_column($orderDetails, 'id_order_detail'));
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        DatabaseDump::restoreTables(['shipment', 'shipment_product']);
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'list order shipments' => ['GET', '/orders/1/shipments'];
        yield 'get shipment' => ['GET', '/shipments/1'];
        yield 'edit shipment' => ['PUT', '/shipments/1'];
        yield 'list shipment products' => ['GET', '/shipments/1/products'];
        yield 'delete shipment product' => ['DELETE', '/shipments/1/products/1'];
        yield 'split shipment' => ['POST', '/shipments/1/split'];
        yield 'merge shipment' => ['POST', '/shipments/1/merge'];
    }

    /**
     * Insert a shipment row directly (shipments are normally created during checkout).
     */
    private function createShipmentFixture(?string $trackingNumber = null): int
    {
        \Db::getInstance()->insert('shipment', [
            'id_order' => self::$orderId,
            'id_carrier' => self::$carrierId,
            'id_delivery_address' => self::$addressId,
            'shipping_cost_tax_excl' => 0,
            'shipping_cost_tax_incl' => 0,
            'tracking_number' => $trackingNumber,
            'date_add' => date('Y-m-d H:i:s'),
            'date_upd' => date('Y-m-d H:i:s'),
        ]);

        return (int) \Db::getInstance()->Insert_ID();
    }

    private function addShipmentProduct(int $shipmentId, int $orderDetailId, int $quantity = 1): void
    {
        \Db::getInstance()->insert('shipment_product', [
            'id_shipment' => $shipmentId,
            'id_order_detail' => $orderDetailId,
            'quantity' => $quantity,
        ]);
    }

    public function testGetOrderShipments(): void
    {
        $shipmentId = $this->createShipmentFixture('TRACK-LIST');
        $this->addShipmentProduct($shipmentId, self::$orderDetailIds[0]);

        // CQRSGetCollection returns a plain array, so we use getItem rather than the paginated listItems helper.
        $shipments = $this->getItem('/orders/' . self::$orderId . '/shipments', ['shipment_read']);

        $this->assertNotEmpty($shipments);
        $shipment = null;
        foreach ($shipments as $item) {
            if ((int) $item['shipmentId'] === $shipmentId) {
                $shipment = $item;
                break;
            }
        }
        $this->assertNotNull($shipment, 'Created shipment should be listed for the order');
        $this->assertEquals(self::$orderId, $shipment['orderId']);
        $this->assertEquals(self::$carrierId, $shipment['carrier']['id']);
        $this->assertIsString($shipment['carrier']['name']);
        $this->assertEquals('TRACK-LIST', $shipment['trackingNumber']);
        $this->assertIsInt($shipment['productsCount']);
        $this->assertArrayHasKey('shippedAt', $shipment);
    }

    public function testGetShipment(): void
    {
        $shipmentId = $this->createShipmentFixture('TRACK-VIEW');
        $this->addShipmentProduct($shipmentId, self::$orderDetailIds[0]);

        $result = $this->getItem('/shipments/' . $shipmentId, ['shipment_read']);

        $this->assertEquals($shipmentId, $result['shipmentId']);
        $this->assertEquals('TRACK-VIEW', $result['trackingNumber']);
        $this->assertEquals(self::$carrierId, $result['carrier']['id']);
        $this->assertIsArray($result['shippingAddress']);
    }

    public function testGetShipmentProducts(): void
    {
        $shipmentId = $this->createShipmentFixture();
        $this->addShipmentProduct($shipmentId, self::$orderDetailIds[0], 2);

        $products = $this->getItem('/shipments/' . $shipmentId . '/products', ['shipment_read']);

        $this->assertNotEmpty($products);
        $this->assertEquals(self::$orderDetailIds[0], $products[0]['orderDetailId']);
        $this->assertEquals(2, $products[0]['quantity']);
        $this->assertIsString($products[0]['productName']);
    }

    public function testEditShipment(): void
    {
        $shipmentId = $this->createShipmentFixture('OLD-TRACK');
        $this->addShipmentProduct($shipmentId, self::$orderDetailIds[0]);

        $this->updateItem(
            '/shipments/' . $shipmentId,
            ['trackingNumber' => 'NEW-TRACK-123', 'carrierId' => self::$carrierId],
            ['shipment_write'],
            Response::HTTP_NO_CONTENT
        );

        $result = $this->getItem('/shipments/' . $shipmentId, ['shipment_read']);
        $this->assertEquals('NEW-TRACK-123', $result['trackingNumber']);
    }

    public function testGetNonExistentShipment(): void
    {
        $this->getItem('/shipments/999999', ['shipment_read'], Response::HTTP_NOT_FOUND);
    }

    public function testDeleteProductFromShipment(): void
    {
        $shipmentId = $this->createShipmentFixture();
        $this->addShipmentProduct($shipmentId, self::$orderDetailIds[0]);
        $this->addShipmentProduct($shipmentId, self::$orderDetailIds[count(self::$orderDetailIds) > 1 ? 1 : 0]);

        $this->deleteItem(
            '/shipments/' . $shipmentId . '/products/' . self::$orderDetailIds[0],
            ['shipment_write'],
            Response::HTTP_NO_CONTENT
        );

        $remaining = (int) \Db::getInstance()->getValue(
            'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'shipment_product`
             WHERE `id_shipment` = ' . $shipmentId . ' AND `id_order_detail` = ' . self::$orderDetailIds[0]
        );
        $this->assertSame(0, $remaining);
    }

    public function testSplitShipment(): void
    {
        if (count(self::$orderDetailIds) < 1) {
            $this->markTestSkipped('Order has no product lines to split.');
        }

        $shipmentId = $this->createShipmentFixture();
        $this->addShipmentProduct($shipmentId, self::$orderDetailIds[0], 2);

        $this->createItem(
            '/shipments/' . $shipmentId . '/split',
            [
                'orderDetailQuantity' => [
                    ['id_order_detail' => self::$orderDetailIds[0], 'quantity' => 1],
                ],
                'carrierId' => self::$carrierId,
            ],
            ['shipment_write'],
            Response::HTTP_CREATED
        );

        $shipmentCount = (int) \Db::getInstance()->getValue(
            'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'shipment` WHERE `id_order` = ' . self::$orderId
        );
        $this->assertGreaterThanOrEqual(2, $shipmentCount);
    }

    public function testMergeShipments(): void
    {
        if (count(self::$orderDetailIds) < 1) {
            $this->markTestSkipped('Order has no product lines to merge.');
        }

        $sourceShipmentId = $this->createShipmentFixture();
        $this->addShipmentProduct($sourceShipmentId, self::$orderDetailIds[0], 1);

        $targetShipmentId = $this->createShipmentFixture();

        $this->createItem(
            '/shipments/' . $sourceShipmentId . '/merge',
            [
                'targetShipmentId' => $targetShipmentId,
                'orderDetailQuantities' => [
                    ['id_order_detail' => self::$orderDetailIds[0], 'quantity' => 1],
                ],
            ],
            ['shipment_write'],
            Response::HTTP_CREATED
        );

        $mergedProducts = (int) \Db::getInstance()->getValue(
            'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'shipment_product`
             WHERE `id_shipment` = ' . $targetShipmentId . ' AND `id_order_detail` = ' . self::$orderDetailIds[0]
        );
        $this->assertGreaterThanOrEqual(1, $mergedProducts);
    }
}
