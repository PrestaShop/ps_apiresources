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

class OrderStateEndpointTest extends ApiTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        DatabaseDump::restoreTables(['order_state', 'order_state_lang']);
        self::createApiClient(['order_state_write', 'order_state_read']);
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        DatabaseDump::restoreTables(['order_state', 'order_state_lang']);
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'create endpoint' => ['POST', '/order-states'];
        yield 'get endpoint' => ['GET', '/order-states/1'];
        yield 'update endpoint' => ['PATCH', '/order-states/1'];
        yield 'delete endpoint' => ['DELETE', '/order-states/1'];
        yield 'bulk delete endpoint' => ['DELETE', '/order-states/bulk-delete'];
    }

    private function createPayload(): array
    {
        return [
            'names' => [
                'en-US' => 'My Order State EN',
                'fr-FR' => 'My Order State FR',
            ],
            'color' => '#4169E1',
            'loggable' => true,
            'invoice' => false,
            'hidden' => false,
            'sendEmail' => false,
            'pdfInvoice' => false,
            'pdfDelivery' => false,
            'shipped' => false,
            'paid' => false,
            'delivery' => false,
            'templates' => [
                'en-US' => '',
                'fr-FR' => '',
            ],
        ];
    }

    public function testAddOrderState(): int
    {
        $orderState = $this->createItem('/order-states', $this->createPayload(), ['order_state_write']);

        $this->assertArrayHasKey('orderStateId', $orderState);
        $orderStateId = $orderState['orderStateId'];
        $this->assertEquals(['orderStateId' => $orderStateId], $orderState);

        return $orderStateId;
    }

    /**
     * @depends testAddOrderState
     */
    public function testGetOrderState(int $orderStateId): int
    {
        $orderState = $this->getItem('/order-states/' . $orderStateId, ['order_state_read']);

        $this->assertSame($orderStateId, $orderState['orderStateId']);
        $this->assertSame(
            ['en-US' => 'My Order State EN', 'fr-FR' => 'My Order State FR'],
            $orderState['names']
        );
        $this->assertSame('#4169E1', $orderState['color']);
        $this->assertTrue($orderState['loggable']);
        $this->assertFalse($orderState['invoice']);
        $this->assertFalse($orderState['hidden']);
        $this->assertFalse($orderState['sendEmail']);
        $this->assertFalse($orderState['pdfInvoice']);
        $this->assertFalse($orderState['pdfDelivery']);
        $this->assertFalse($orderState['shipped']);
        $this->assertFalse($orderState['paid']);
        $this->assertFalse($orderState['delivery']);
        $this->assertFalse($orderState['deleted']);
        $this->assertArrayHasKey('templates', $orderState);

        return $orderStateId;
    }

    /**
     * @depends testGetOrderState
     */
    public function testEditOrderState(int $orderStateId): int
    {
        $updated = $this->partialUpdateItem('/order-states/' . $orderStateId, [
            'names' => [
                'en-US' => 'My Order State EN Updated',
                'fr-FR' => 'My Order State FR Updated',
            ],
            'paid' => true,
        ], ['order_state_write']);

        $this->assertSame(
            ['en-US' => 'My Order State EN Updated', 'fr-FR' => 'My Order State FR Updated'],
            $updated['names']
        );
        $this->assertTrue($updated['paid']);

        return $orderStateId;
    }

    /**
     * @depends testEditOrderState
     */
    public function testDeleteOrderState(int $orderStateId): void
    {
        $return = $this->deleteItem('/order-states/' . $orderStateId, ['order_state_write']);
        // This endpoint returns an empty response and a 204 HTTP code
        $this->assertNull($return);

        // Order states are soft-deleted: the record still exists but is flagged as deleted
        $deleted = $this->getItem('/order-states/' . $orderStateId, ['order_state_read']);
        $this->assertTrue($deleted['deleted']);
    }

    public function testBulkDeleteOrderStates(): void
    {
        $firstId = $this->createItem('/order-states', $this->createPayload(), ['order_state_write'])['orderStateId'];
        $secondId = $this->createItem('/order-states', $this->createPayload(), ['order_state_write'])['orderStateId'];

        $this->bulkDeleteItems('/order-states/bulk-delete', [
            'orderStateIds' => [$firstId, $secondId],
        ], ['order_state_write']);

        // Order states are soft-deleted: still readable but flagged as deleted
        $this->assertTrue($this->getItem('/order-states/' . $firstId, ['order_state_read'])['deleted']);
        $this->assertTrue($this->getItem('/order-states/' . $secondId, ['order_state_read'])['deleted']);
    }
}
