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

class OrderReturnStateEndpointTest extends ApiTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        DatabaseDump::restoreTables(['order_return_state', 'order_return_state_lang']);
        self::createApiClient(['order_return_state_write', 'order_return_state_read']);
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        DatabaseDump::restoreTables(['order_return_state', 'order_return_state_lang']);
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'create endpoint' => [
            'POST',
            '/order-return-states',
        ];

        yield 'get endpoint' => [
            'GET',
            '/order-return-states/1',
        ];

        yield 'update endpoint' => [
            'PATCH',
            '/order-return-states/1',
        ];

        yield 'delete endpoint' => [
            'DELETE',
            '/order-return-states/1',
        ];

        yield 'bulk delete endpoint' => [
            'DELETE',
            '/order-return-states/bulk-delete',
        ];
    }

    public function testAddOrderReturnState(): int
    {
        $orderReturnState = $this->createItem('/order-return-states', [
            'names' => [
                'en-US' => 'My Return State EN',
                'fr-FR' => 'My Return State FR',
            ],
            'color' => '#112233',
        ], ['order_return_state_write']);

        $this->assertArrayHasKey('orderReturnStateId', $orderReturnState);
        $orderReturnStateId = $orderReturnState['orderReturnStateId'];
        $this->assertEquals(['orderReturnStateId' => $orderReturnStateId], $orderReturnState);

        return $orderReturnStateId;
    }

    /**
     * @depends testAddOrderReturnState
     */
    public function testGetOrderReturnState(int $orderReturnStateId): int
    {
        $orderReturnState = $this->getItem('/order-return-states/' . $orderReturnStateId, ['order_return_state_read']);
        $this->assertEquals(
            [
                'orderReturnStateId' => $orderReturnStateId,
                'names' => [
                    'en-US' => 'My Return State EN',
                    'fr-FR' => 'My Return State FR',
                ],
                'color' => '#112233',
            ],
            $orderReturnState
        );

        return $orderReturnStateId;
    }

    /**
     * @depends testGetOrderReturnState
     */
    public function testEditOrderReturnState(int $orderReturnStateId): int
    {
        $updated = $this->partialUpdateItem('/order-return-states/' . $orderReturnStateId, [
            'names' => [
                'en-US' => 'My Return State EN Updated',
                'fr-FR' => 'My Return State FR Updated',
            ],
            'color' => '#445566',
        ], ['order_return_state_write']);
        $this->assertEquals(
            [
                'orderReturnStateId' => $orderReturnStateId,
                'names' => [
                    'en-US' => 'My Return State EN Updated',
                    'fr-FR' => 'My Return State FR Updated',
                ],
                'color' => '#445566',
            ],
            $updated
        );

        return $orderReturnStateId;
    }

    /**
     * @depends testEditOrderReturnState
     */
    public function testDeleteOrderReturnState(int $orderReturnStateId): void
    {
        $return = $this->deleteItem('/order-return-states/' . $orderReturnStateId, ['order_return_state_write']);
        // This endpoint returns an empty response and a 204 HTTP code
        $this->assertNull($return);

        // Getting the item should result in a 404 now
        $this->getItem('/order-return-states/' . $orderReturnStateId, ['order_return_state_read'], Response::HTTP_NOT_FOUND);
    }

    public function testBulkDeleteOrderReturnStates(): void
    {
        $firstId = $this->createItem('/order-return-states', [
            'names' => ['en-US' => 'Bulk RS 1 EN', 'fr-FR' => 'Bulk RS 1 FR'],
            'color' => '#101010',
        ], ['order_return_state_write'])['orderReturnStateId'];
        $secondId = $this->createItem('/order-return-states', [
            'names' => ['en-US' => 'Bulk RS 2 EN', 'fr-FR' => 'Bulk RS 2 FR'],
            'color' => '#202020',
        ], ['order_return_state_write'])['orderReturnStateId'];

        $this->bulkDeleteItems('/order-return-states/bulk-delete', [
            'orderReturnStateIds' => [$firstId, $secondId],
        ], ['order_return_state_write']);

        $this->getItem('/order-return-states/' . $firstId, ['order_return_state_read'], Response::HTTP_NOT_FOUND);
        $this->getItem('/order-return-states/' . $secondId, ['order_return_state_read'], Response::HTTP_NOT_FOUND);
    }
}
