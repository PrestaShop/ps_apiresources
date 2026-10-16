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
        self::resetTables();
        self::createApiClient(['order_state_read', 'order_state_write']);
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        self::resetTables();
    }

    protected static function resetTables(): void
    {
        DatabaseDump::restoreTables([
            'order_state',
            'order_state_lang',
        ]);
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'get endpoint' => [
            'GET',
            '/order-states/1',
        ];

        yield 'create endpoint' => [
            'POST',
            '/order-states',
        ];

        yield 'patch endpoint' => [
            'PATCH',
            '/order-states/1',
        ];

        yield 'list endpoint' => [
            'GET',
            '/order-states',
        ];
    }

    private function getCreateData(): array
    {
        return [
            'names' => [
                'en-US' => 'Awaiting review EN',
                'fr-FR' => 'Awaiting review FR',
            ],
            'templates' => [
                'en-US' => '',
                'fr-FR' => '',
            ],
            'color' => '#4169E1',
            'loggable' => false,
            'invoice' => false,
            'hidden' => false,
            'sendEmail' => false,
            'pdfInvoice' => false,
            'pdfDelivery' => false,
            'shipped' => false,
            'paid' => false,
            'delivery' => false,
        ];
    }

    /**
     * The create operation replays GetOrderStateForEditing, so it returns the whole entity and
     * not just its id. Asserting the complete structure here is what pins that contract.
     */
    private function getExpectedOrderState(int $orderStateId, array $data): array
    {
        return array_merge($data, [
            'orderStateId' => $orderStateId,
            // Order states are soft deleted, a freshly created one is never flagged
            'deleted' => false,
        ]);
    }

    public function testAddOrderState(): int
    {
        $orderState = $this->createItem('/order-states', $this->getCreateData(), ['order_state_write']);
        $this->assertArrayHasKey('orderStateId', $orderState);
        $orderStateId = $orderState['orderStateId'];

        $this->assertEquals(
            $this->getExpectedOrderState($orderStateId, $this->getCreateData()),
            $orderState
        );

        return $orderStateId;
    }

    /**
     * @depends testAddOrderState
     */
    public function testGetOrderState(int $orderStateId): int
    {
        $orderState = $this->getItem('/order-states/' . $orderStateId, ['order_state_read']);

        // The GET must return exactly what the POST returned
        $this->assertEquals(
            $this->getExpectedOrderState($orderStateId, $this->getCreateData()),
            $orderState
        );

        return $orderStateId;
    }

    /**
     * @depends testGetOrderState
     */
    public function testPartialUpdateOrderState(int $orderStateId): int
    {
        $patchData = [
            'names' => [
                'en-US' => 'Updated status EN',
                'fr-FR' => 'Updated status FR',
            ],
            'color' => '#32CD32',
        ];

        $expected = $this->getExpectedOrderState(
            $orderStateId,
            array_merge($this->getCreateData(), $patchData)
        );

        // The partial update returns the updated entity through the same query as the GET
        $updatedOrderState = $this->partialUpdateItem('/order-states/' . $orderStateId, $patchData, ['order_state_write']);
        $this->assertEquals($expected, $updatedOrderState);

        // And a subsequent GET returns exactly the same thing
        $this->assertEquals($expected, $this->getItem('/order-states/' . $orderStateId, ['order_state_read']));

        return $orderStateId;
    }

    /**
     * @depends testPartialUpdateOrderState
     */
    public function testListOrderStates(int $orderStateId): int
    {
        $paginatedOrderStates = $this->listItems('/order-states?orderBy=orderStateId&sortOrder=desc', ['order_state_read']);
        $this->assertGreaterThanOrEqual(1, $paginatedOrderStates['totalItems']);
        $this->assertEquals('orderStateId', $paginatedOrderStates['orderBy']);

        $firstOrderState = $paginatedOrderStates['items'][0];
        $this->assertEquals($orderStateId, $firstOrderState['orderStateId']);

        return $orderStateId;
    }

    /**
     * @depends testListOrderStates
     */
    public function testDeleteOrderState(int $orderStateId): void
    {
        $return = $this->deleteItem('/order-states/' . $orderStateId, ['order_state_write']);
        // This endpoint returns an empty response and a 204 HTTP code
        $this->assertNull($return);

        // Order states are soft-deleted (existing orders may reference them): the record is
        // still readable but flagged as deleted, and no longer appears in the listing.
        $this->assertTrue($this->getItem('/order-states/' . $orderStateId, ['order_state_read'])['deleted']);

        $orderStates = $this->listItems('/order-states?orderBy=orderStateId&sortOrder=desc', ['order_state_read']);
        $listedIds = array_column($orderStates['items'], 'orderStateId');
        $this->assertNotContains($orderStateId, $listedIds);
    }

    /**
     * @depends testDeleteOrderState
     */
    public function testBulkDeleteOrderStates(): void
    {
        $bulkIds = [];
        foreach (['A', 'B'] as $suffix) {
            $data = $this->getCreateData();
            $data['names'] = [
                'en-US' => 'Bulk status ' . $suffix,
                'fr-FR' => 'Bulk status ' . $suffix,
            ];
            $created = $this->createItem('/order-states', $data, ['order_state_write']);
            $bulkIds[] = $created['orderStateId'];
        }

        $this->bulkDeleteItems('/order-states/bulk-delete', [
            'orderStateIds' => $bulkIds,
        ], ['order_state_write']);

        // Soft-deleted order states are still readable but flagged, and no longer listed
        $orderStates = $this->listItems('/order-states?orderBy=orderStateId&sortOrder=desc', ['order_state_read']);
        $listedIds = array_column($orderStates['items'], 'orderStateId');
        foreach ($bulkIds as $orderStateId) {
            $this->assertTrue($this->getItem('/order-states/' . $orderStateId, ['order_state_read'])['deleted']);
            $this->assertNotContains($orderStateId, $listedIds);
        }
    }

    public function testInvalidOrderState(): void
    {
        $invalidData = $this->getCreateData();
        $invalidData['names'] = [
            'fr-FR' => 'Nom FR uniquement',
        ];
        $invalidData['color'] = '';

        $validationErrorsResponse = $this->createItem('/order-states', $invalidData, ['order_state_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertIsArray($validationErrorsResponse);

        $this->assertValidationErrors([
            [
                'propertyPath' => 'names',
                'message' => 'The field names is required at least in your default language.',
            ],
            [
                'propertyPath' => 'color',
                'message' => 'This value should not be blank.',
            ],
        ], $validationErrorsResponse);
    }
}
