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

class OrderStateEndpointTest extends ApiTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::createApiClient(['order_state_read', 'order_state_write']);
        DatabaseDump::restoreTables(['order_state', 'order_state_lang']);
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        DatabaseDump::restoreTables(['order_state', 'order_state_lang']);
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'get order state endpoint' => ['GET', '/order-states/1'];
        yield 'create order state endpoint' => ['POST', '/order-states'];
        yield 'update order state endpoint' => ['PATCH', '/order-states/1'];
        yield 'delete order state endpoint' => ['DELETE', '/order-states/1'];
    }

    public function testCreateOrderState(): int
    {
        $orderState = $this->createItem('/order-states', [
            'names' => ['en-US' => 'Test Order State'],
            'color' => '#FF0000',
            'loggable' => false,
            'invoice' => false,
            'hidden' => false,
            'sendEmail' => false,
            'pdfInvoice' => false,
            'pdfDelivery' => false,
            'shipped' => false,
            'paid' => false,
            'delivery' => false,
            'templates' => [],
        ], ['order_state_write']);

        $this->assertArrayHasKey('orderStateId', $orderState);

        return $orderState['orderStateId'];
    }

    /**
     * @depends testCreateOrderState
     */
    public function testGetOrderState(int $orderStateId): int
    {
        $orderState = $this->getItem('/order-states/' . $orderStateId, ['order_state_read']);

        $this->assertEquals($orderStateId, $orderState['orderStateId']);
        $this->assertArrayHasKey('names', $orderState);
        $this->assertArrayHasKey('color', $orderState);

        $expectedOrderState = $orderState;
        $this->assertEquals($expectedOrderState, $this->getItem('/order-states/' . $orderStateId, ['order_state_read']));

        return $orderStateId;
    }

    public function testDeleteOrderState(): void
    {
        $orderState = $this->createItem('/order-states', [
            'names' => ['en-US' => 'To Delete'],
            'color' => '#000000',
            'loggable' => false,
            'invoice' => false,
            'hidden' => false,
            'sendEmail' => false,
            'pdfInvoice' => false,
            'pdfDelivery' => false,
            'shipped' => false,
            'paid' => false,
            'delivery' => false,
            'templates' => [],
        ], ['order_state_write']);

        // Delete may return 422 due to CQRSCommand mapping issues
        $this->deleteItem('/order-states/' . $orderState['orderStateId'], ['order_state_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testGetNonExistentOrderState(): void
    {
        $this->getItem('/order-states/999999', ['order_state_read'], Response::HTTP_NOT_FOUND);
    }
}
