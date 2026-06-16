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

class OrderReturnEndpointTest extends ApiTestCase
{
    private static \OrderReturn $orderReturn;

    // State IDs matching default PS fixtures (install-dev/data/xml/order_return_state.xml)
    private const STATE_WAITING_FOR_CONFIRMATION = 1;
    private const STATE_WAITING_FOR_PACKAGE = 2;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::createApiClient(['order_return_read', 'order_return_write']);

        // Find an existing order with its customer to create the return against
        $row = \Db::getInstance()->getRow(
            'SELECT `id_order`, `id_customer` FROM `' . _DB_PREFIX_ . 'orders`'
        );

        self::$orderReturn = new \OrderReturn();
        self::$orderReturn->id_customer = (int) $row['id_customer'];
        self::$orderReturn->id_order = (int) $row['id_order'];
        self::$orderReturn->state = self::STATE_WAITING_FOR_CONFIRMATION;
        self::$orderReturn->question = 'Test merchandise return question';
        self::$orderReturn->save();
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        DatabaseDump::restoreTables(['order_return']);
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'get endpoint' => ['GET', '/order-returns/1'];
        yield 'update endpoint' => ['PATCH', '/order-returns/1'];
    }

    public function testGetOrderReturn(): int
    {
        $orderReturnId = (int) self::$orderReturn->id;

        $result = $this->getItem('/order-returns/' . $orderReturnId, ['order_return_read']);

        $this->assertEquals($orderReturnId, $result['orderReturnId']);
        $this->assertEquals((int) self::$orderReturn->id_customer, $result['customerId']);
        $this->assertIsString($result['customerFirstName']);
        $this->assertIsString($result['customerLastName']);
        $this->assertEquals((int) self::$orderReturn->id_order, $result['orderId']);
        $this->assertIsString($result['orderDate']);
        $this->assertNotEmpty($result['orderDate']);
        $this->assertEquals(self::STATE_WAITING_FOR_CONFIRMATION, $result['orderReturnStateId']);
        $this->assertEquals('Test merchandise return question', $result['question']);

        return $orderReturnId;
    }

    /**
     * @depends testGetOrderReturn
     */
    public function testUpdateOrderReturnState(int $orderReturnId): int
    {
        $result = $this->partialUpdateItem(
            '/order-returns/' . $orderReturnId,
            ['orderReturnStateId' => self::STATE_WAITING_FOR_PACKAGE],
            ['order_return_write']
        );

        $this->assertEquals($orderReturnId, $result['orderReturnId']);
        $this->assertEquals(self::STATE_WAITING_FOR_PACKAGE, $result['orderReturnStateId']);
        $this->assertEquals((int) self::$orderReturn->id_customer, $result['customerId']);
        $this->assertEquals((int) self::$orderReturn->id_order, $result['orderId']);
        $this->assertEquals('Test merchandise return question', $result['question']);

        return $orderReturnId;
    }

    /**
     * @depends testUpdateOrderReturnState
     */
    public function testGetUpdatedOrderReturn(int $orderReturnId): void
    {
        $result = $this->getItem('/order-returns/' . $orderReturnId, ['order_return_read']);

        $this->assertEquals(self::STATE_WAITING_FOR_PACKAGE, $result['orderReturnStateId']);
    }

    public function testGetNonExistentOrderReturn(): void
    {
        $this->getItem('/order-returns/999999', ['order_return_read'], Response::HTTP_NOT_FOUND);
    }

    public function testUpdateOrderReturnWithInvalidStateId(): void
    {
        $orderReturnId = (int) self::$orderReturn->id;

        $this->partialUpdateItem(
            '/order-returns/' . $orderReturnId,
            ['orderReturnStateId' => 999999],
            ['order_return_write'],
            Response::HTTP_UNPROCESSABLE_ENTITY
        );
    }
}
