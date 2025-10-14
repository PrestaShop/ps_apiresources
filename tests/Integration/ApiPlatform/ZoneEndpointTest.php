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

class ZoneEndpointTest extends ApiTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::createApiClient(['zone_write', 'zone_read']);
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        // Reset DB as it was before this test
        DatabaseDump::restoreTables(['zone', 'zone_shop']);
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'create endpoint' => [
            'POST',
            '/zone',
        ];

        yield 'get endpoint' => [
            'GET',
            '/zone/1',
        ];

        yield 'update endpoint' => [
            'PUT',
            '/zone/1',
        ];

        yield 'toggle status endpoint' => [
            'PUT',
            '/zone/1/toggle-status',
        ];

        yield 'delete endpoint' => [
            'DELETE',
            '/zone/1',
        ];

        yield 'list endpoint' => [
            'GET',
            '/zones',
        ];

        yield 'bulk delete endpoint' => [
            'PUT',
            '/zones/delete',
        ];

        yield 'bulk toggle status endpoint' => [
            'PUT',
            '/zones/toggle-status',
        ];
    }

    public function testAddZone(): int
    {
        $itemsCount = $this->countItems('/zones', ['zone_read']);

        $zone = $this->createItem('/zone', [
            'name' => 'My Zone',
            'enabled' => false,
            'shopIds' => [1],
        ], ['zone_write']);
        $this->assertArrayHasKey('zoneId', $zone);
        $zoneId = $zone['zoneId'];
        $this->assertEquals(
            [
                'zoneId' => $zoneId,
            ],
            $zone
        );

        $newItemsCount = $this->countItems('/zones', ['zone_read']);
        $this->assertEquals($itemsCount + 1, $newItemsCount);

        return $zoneId;
    }

    /**
     * @depends testAddZone
     *
     * @param int $zoneId
     *
     * @return int
     */
    public function testGetZone(int $zoneId): int
    {
        $zone = $this->getItem('/zone/' . $zoneId, ['zone_read']);
        $this->assertEquals(
            [
                'zoneId' => $zoneId,
                'name' => 'My Zone',
                'enabled' => false,
                'shopIds' => [1],
            ],
            $zone
        );

        return $zoneId;
    }

    /**
     * @depends testGetZone
     *
     * @param int $zoneId
     *
     * @return int
     */
    public function testUpdateZone(int $zoneId): int
    {
        $updatedZone = $this->updateItem('/zone/' . $zoneId, [
            'name' => 'My Zone updated',
            'enabled' => true,
        ], ['zone_write']);
        $this->assertEquals(
            [
                'zoneId' => $zoneId,
                'name' => 'My Zone updated',
                'enabled' => true,
                'shopIds' => [1],
            ],
            $updatedZone
        );

        return $zoneId;
    }

    /**
     * @depends testUpdateZone
     *
     * @param int $zoneId
     *
     * @return int
     */
    public function testGetUpdatedZone(int $zoneId): int
    {
        $zone = $this->getItem('/zone/' . $zoneId, ['zone_read']);
        $this->assertEquals(
            [
                'zoneId' => $zoneId,
                'name' => 'My Zone updated',
                'enabled' => true,
                'shopIds' => [1],
            ],
            $zone
        );

        return $zoneId;
    }

    /**
     * @depends testGetUpdatedZone
     *
     * @param int $zoneId
     *
     * @return int
     */
    public function testToggleStatusZone(int $zoneId): int
    {
        $this->updateItem('/zone/' . $zoneId . '/toggle-status', [], ['zone_write'], Response::HTTP_NO_CONTENT);
        $zone = $this->getItem('/zone/' . $zoneId, ['zone_read']);
        $this->assertEquals(
            [
                'zoneId' => $zoneId,
                'name' => 'My Zone updated',
                'enabled' => false,
                'shopIds' => [1],
            ],
            $zone
        );

        return $zoneId;
    }

    /**
     * @depends testToggleStatusZone
     *
     * @param int $zoneId
     *
     * @return int
     */
    public function testListZones(int $zoneId): int
    {
        $zones = $this->listItems('/zones', ['zone_read']);
        $this->assertGreaterThanOrEqual(1, $zones['totalItems']);

        // Search for the one created previously during the tests and assert its data in the list
        $testZone = null;
        foreach ($zones['items'] as $zone) {
            if ($zone['zoneId'] === $zoneId) {
                $testZone = $zone;
            }
        }
        $this->assertNotNull($testZone);
        $this->assertEquals(
            [
                'zoneId' => $zoneId,
                'name' => 'My Zone updated',
                'enabled' => false,
            ],
            $testZone
        );

        return $zoneId;
    }

    /**
     * @depends testListZones
     *
     * @param int $zoneId
     *
     * @return int
     */
    public function testDeleteZone(int $zoneId): void
    {
        $return = $this->deleteItem('/zone/' . $zoneId, ['zone_write']);
        // This endpoint return empty response and 204 HTTP code
        $this->assertNull($return);

        // Getting the item should result in a 404 now
        $this->getItem('/zone/' . $zoneId, ['zone_read'], Response::HTTP_NOT_FOUND);
    }

    /**
     * @depends testDeleteZone
     *
     * @param int $zoneId
     *
     * @return int
     */
    public function testBulkDeleteZones(): void
    {
        $zones = $this->listItems('/zones', ['zone_read']);

        // There are zones in default fixtures
        $this->assertEquals(8, $zones['totalItems']);

        // We remove the two zones
        $bulkZones = [
            $zones['items'][2]['zoneId'],
            $zones['items'][3]['zoneId'],
        ];

        $this->updateItem('/zones/delete', [
            'zoneIds' => $bulkZones,
        ], ['zone_write'], Response::HTTP_NO_CONTENT);

        // Assert the provided zones have been removed
        foreach ($bulkZones as $zoneId) {
            $this->getItem('/zone/' . $zoneId, ['zone_read'], Response::HTTP_NOT_FOUND);
        }

        $this->assertEquals(6, $this->countItems('/zones', ['zone_read']));
    }

    /**
     * @depends testBulkDeleteZones
     *
     * @return void
     */
    public function testBulkToggleStatusZones(): void
    {
        $zones = $this->listItems('/zones', ['zone_read']);

        // There are zones in default fixtures
        $this->assertEquals(6, $zones['totalItems']);

        // We toggle status the first two zones
        $bulkZones = [
            $zones['items'][0]['zoneId'],
            $zones['items'][1]['zoneId'],
        ];

        $this->updateItem('/zones/toggle-status', [
            'zoneIds' => $bulkZones,
            'enabled' => false,
        ], ['zone_write'], Response::HTTP_NO_CONTENT);

        foreach ($bulkZones as $zoneId) {
            $zone = $this->getItem('/zone/' . $zoneId, ['zone_read']);
            $this->assertEquals(false, $zone['enabled']);
        }
    }

    public function testCreateInvalidZone(): void
    {
        // Creating with invalid data should return a response with invalid constraint messages and use an http code 422
        $validationErrorsResponse = $this->createItem('/zone', [
            'name' => '',
        ], ['zone_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertIsArray($validationErrorsResponse);
        $this->assertValidationErrors([
            [
                'propertyPath' => 'name',
                'message' => 'This value should not be blank.',
            ],
            [
                'propertyPath' => 'name',
                'message' => 'This value is too short. It should have 1 character or more.',
            ],
            [
                'propertyPath' => 'enabled',
                'message' => 'This value should not be null.',
            ],
        ], $validationErrorsResponse);
    }
}
