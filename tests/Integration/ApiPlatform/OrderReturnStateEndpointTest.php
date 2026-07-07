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
use Tests\Resources\Resetter\LanguageResetter;

class OrderReturnStateEndpointTest extends ApiTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        LanguageResetter::resetLanguages();
        self::addLanguageByLocale('fr-FR');
        DatabaseDump::restoreTables(['order_return_state', 'order_return_state_lang']);
        // Pre-create the API Client with the needed scopes, this way we reduce the number of created API Clients
        self::createApiClient(['order_return_state_write', 'order_return_state_read']);
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        // Reset DB as it was before this test
        DatabaseDump::restoreTables(['order_return_state', 'order_return_state_lang']);
        LanguageResetter::resetLanguages();
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'get endpoint' => [
            'GET',
            '/order-return-states/1',
        ];

        yield 'create endpoint' => [
            'POST',
            '/order-return-states',
        ];

        yield 'update endpoint' => [
            'PATCH',
            '/order-return-states/1',
        ];

        yield 'delete endpoint' => [
            'DELETE',
            '/order-return-states/1',
        ];

        yield 'list endpoint' => [
            'GET',
            '/order-return-states',
        ];

        yield 'bulk delete endpoint' => [
            'DELETE',
            '/order-return-states/bulk-delete',
        ];
    }

    public function testAddOrderReturnState(): int
    {
        $itemsCount = $this->countItems('/order-return-states', ['order_return_state_read']);

        $orderReturnState = $this->createItem('/order-return-states', [
            'localizedNames' => [
                'en-US' => 'Waiting for refund',
                'fr-FR' => 'En attente de remboursement',
            ],
            'color' => '#32CD32',
        ], ['order_return_state_write']);
        $this->assertArrayHasKey('orderReturnStateId', $orderReturnState);
        $orderReturnStateId = $orderReturnState['orderReturnStateId'];
        $this->assertEquals(
            [
                'orderReturnStateId' => $orderReturnStateId,
            ],
            $orderReturnState
        );

        $newItemsCount = $this->countItems('/order-return-states', ['order_return_state_read']);
        $this->assertEquals($itemsCount + 1, $newItemsCount);

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
                'localizedNames' => [
                    'en-US' => 'Waiting for refund',
                    'fr-FR' => 'En attente de remboursement',
                ],
                'color' => '#32CD32',
            ],
            $orderReturnState
        );

        return $orderReturnStateId;
    }

    /**
     * @depends testGetOrderReturnState
     */
    public function testUpdateOrderReturnState(int $orderReturnStateId): int
    {
        $updatedOrderReturnState = $this->partialUpdateItem('/order-return-states/' . $orderReturnStateId, [
            'localizedNames' => [
                'en-US' => 'Waiting for refund updated',
                'fr-FR' => 'En attente de remboursement maj',
            ],
        ], ['order_return_state_write']);
        // Returned data has modified fields, the color hasn't changed
        $this->assertEquals(
            [
                'orderReturnStateId' => $orderReturnStateId,
                'localizedNames' => [
                    'en-US' => 'Waiting for refund updated',
                    'fr-FR' => 'En attente de remboursement maj',
                ],
                'color' => '#32CD32',
            ],
            $updatedOrderReturnState
        );

        $updatedOrderReturnState = $this->partialUpdateItem('/order-return-states/' . $orderReturnStateId, [
            'color' => '#4169E1',
        ], ['order_return_state_write']);
        $this->assertEquals(
            [
                'orderReturnStateId' => $orderReturnStateId,
                'localizedNames' => [
                    'en-US' => 'Waiting for refund updated',
                    'fr-FR' => 'En attente de remboursement maj',
                ],
                'color' => '#4169E1',
            ],
            $updatedOrderReturnState
        );

        return $orderReturnStateId;
    }

    /**
     * @depends testUpdateOrderReturnState
     */
    public function testGetUpdatedOrderReturnState(int $orderReturnStateId): int
    {
        $orderReturnState = $this->getItem('/order-return-states/' . $orderReturnStateId, ['order_return_state_read']);
        $this->assertEquals(
            [
                'orderReturnStateId' => $orderReturnStateId,
                'localizedNames' => [
                    'en-US' => 'Waiting for refund updated',
                    'fr-FR' => 'En attente de remboursement maj',
                ],
                'color' => '#4169E1',
            ],
            $orderReturnState
        );

        return $orderReturnStateId;
    }

    /**
     * @depends testGetUpdatedOrderReturnState
     */
    public function testListOrderReturnStates(int $orderReturnStateId): int
    {
        $orderReturnStates = $this->listItems('/order-return-states', ['order_return_state_read']);
        // There are 5 order return states in the default fixtures, plus the one created during the tests
        $this->assertGreaterThanOrEqual(6, $orderReturnStates['totalItems']);

        // Search for the one created previously during the tests and assert its data in the list
        $testOrderReturnState = null;
        foreach ($orderReturnStates['items'] as $orderReturnState) {
            if ($orderReturnState['orderReturnStateId'] === $orderReturnStateId) {
                $testOrderReturnState = $orderReturnState;
                break;
            }
        }
        $this->assertNotNull($testOrderReturnState);
        $this->assertEquals(
            [
                'orderReturnStateId' => $orderReturnStateId,
                'name' => 'Waiting for refund updated',
                'color' => '#4169E1',
            ],
            $testOrderReturnState
        );

        return $orderReturnStateId;
    }

    /**
     * @depends testListOrderReturnStates
     */
    public function testDeleteOrderReturnState(int $orderReturnStateId): void
    {
        $return = $this->deleteItem('/order-return-states/' . $orderReturnStateId, ['order_return_state_write']);
        // This endpoint returns an empty response and a 204 HTTP code
        $this->assertNull($return);

        // Getting the item should result in a 404 now
        $this->getItem('/order-return-states/' . $orderReturnStateId, ['order_return_state_read'], Response::HTTP_NOT_FOUND);
    }

    public function testUpdateNotFoundOrderReturnState(): void
    {
        // Updating a non-existent state returns a 404
        $this->partialUpdateItem('/order-return-states/99999', [
            'color' => '#000000',
        ], ['order_return_state_write'], Response::HTTP_NOT_FOUND);
    }

    public function testDeleteNotFoundOrderReturnState(): void
    {
        // Deleting a non-existent state returns a 404
        $this->deleteItem('/order-return-states/99999', ['order_return_state_write'], Response::HTTP_NOT_FOUND);
    }

    public function testCreateInvalidOrderReturnState(): void
    {
        // Creating with empty localized names should return a 422 with the matching constraint message
        $validationErrorsResponse = $this->createItem('/order-return-states', [
            'localizedNames' => [],
            'color' => '#32CD32',
        ], ['order_return_state_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertIsArray($validationErrorsResponse);
        $this->assertValidationErrors([
            [
                'propertyPath' => 'localizedNames',
                'message' => 'This value should not be blank.',
            ],
        ], $validationErrorsResponse);
    }

    public function testBulkDeleteOrderReturnStates(): void
    {
        // Create two states to delete in bulk
        $firstId = $this->createItem('/order-return-states', [
            'localizedNames' => ['en-US' => 'Bulk 1', 'fr-FR' => 'Lot 1'],
            'color' => '#111111',
        ], ['order_return_state_write'])['orderReturnStateId'];
        $secondId = $this->createItem('/order-return-states', [
            'localizedNames' => ['en-US' => 'Bulk 2', 'fr-FR' => 'Lot 2'],
            'color' => '#222222',
        ], ['order_return_state_write'])['orderReturnStateId'];

        $itemsCount = $this->countItems('/order-return-states', ['order_return_state_read']);

        $this->bulkDeleteItems('/order-return-states/bulk-delete', [
            'orderReturnStateIds' => [$firstId, $secondId],
        ], ['order_return_state_write']);

        // Assert the provided states have been removed
        foreach ([$firstId, $secondId] as $deletedId) {
            $this->getItem('/order-return-states/' . $deletedId, ['order_return_state_read'], Response::HTTP_NOT_FOUND);
        }

        $this->assertEquals($itemsCount - 2, $this->countItems('/order-return-states', ['order_return_state_read']));
    }
}
