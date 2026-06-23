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

    public function testAddOrderState(): int
    {
        $orderState = $this->createItem('/order-states', $this->getCreateData(), ['order_state_write']);
        $this->assertArrayHasKey('orderStateId', $orderState);
        $orderStateId = $orderState['orderStateId'];

        $this->assertSame($this->getCreateData()['names'], $orderState['names']);
        $this->assertSame('#4169E1', $orderState['color']);

        return $orderStateId;
    }

    /**
     * @depends testAddOrderState
     */
    public function testGetOrderState(int $orderStateId): int
    {
        $orderState = $this->getItem('/order-states/' . $orderStateId, ['order_state_read']);
        $this->assertEquals($orderStateId, $orderState['orderStateId']);
        $this->assertArrayHasKey('names', $orderState);
        $this->assertArrayHasKey('color', $orderState);
        $this->assertArrayHasKey('sendEmail', $orderState);

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

        $updatedOrderState = $this->partialUpdateItem('/order-states/' . $orderStateId, $patchData, ['order_state_write']);
        $this->assertSame($patchData['names'], $updatedOrderState['names']);
        $this->assertSame($patchData['color'], $updatedOrderState['color']);

        // We check that when we GET the item it is updated as expected
        $orderState = $this->getItem('/order-states/' . $orderStateId, ['order_state_read']);
        $this->assertSame($patchData['names'], $orderState['names']);
        $this->assertSame($patchData['color'], $orderState['color']);

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

        // Getting the item should result in a 404 now
        $this->getItem('/order-states/' . $orderStateId, ['order_state_read'], Response::HTTP_NOT_FOUND);
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

        // Assert the provided order states have been removed
        foreach ($bulkIds as $orderStateId) {
            $this->getItem('/order-states/' . $orderStateId, ['order_state_read'], Response::HTTP_NOT_FOUND);
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
