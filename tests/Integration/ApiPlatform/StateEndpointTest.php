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

class StateEndpointTest extends ApiTestCase
{
    public static int $countryIdAU;

    public static int $countryIdFR;

    public static int $zoneIdEurope;

    public static int $zoneIdOceania;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::createApiClient(['state_write']);

        self::$countryIdAU = \Db::getInstance()->getValue('SELECT id_country FROM `' . _DB_PREFIX_ . 'country` WHERE iso_code="AU"');
        self::$countryIdFR = \Db::getInstance()->getValue('SELECT id_country FROM `' . _DB_PREFIX_ . 'country` WHERE iso_code="FR"');
        self::$zoneIdEurope = \Db::getInstance()->getValue('SELECT id_zone FROM `' . _DB_PREFIX_ . 'zone` WHERE name="Europe"');
        self::$zoneIdOceania = \Db::getInstance()->getValue('SELECT id_zone FROM `' . _DB_PREFIX_ . 'zone` WHERE name="Oceania"');
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        // Reset DB as it was before this test
        DatabaseDump::restoreTables(['state']);
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'create endpoint' => [
            'POST',
            '/states',
        ];
        yield 'get endpoint' => [
            'GET',
            '/states/1',
        ];
        yield 'update endpoint' => [
            'PATCH',
            '/states/1',
        ];
        yield 'toggle status endpoint' => [
            'PUT',
            '/states/1/toggle-status',
        ];
        yield 'delete endpoint' => [
            'DELETE',
            '/states/1',
        ];
        yield 'list endpoint' => [
            'GET',
            '/states',
        ];
        yield 'bulk disable endpoint' => [
            'PUT',
            '/states/bulk-set-status',
        ];
        yield 'bulk delete endpoint' => [
            'PUT',
            '/states/bulk-delete',
        ];
    }

    public function testAddState(): int
    {
        $itemsCount = $this->countItems('/states', ['state_read']);

        $state = $this->createItem('/states', [
            'name' => 'Normandie',
            'isoCode' => 'FR-NOR',
            'countryId' => self::$countryIdFR,
            'zoneId' => self::$zoneIdEurope,
            'enabled' => false,
        ], ['state_write']);
        $this->assertArrayHasKey('stateId', $state);
        $stateId = $state['stateId'];
        $this->assertEquals(
            [
                'stateId' => $stateId,
            ],
            $state
        );

        $newItemsCount = $this->countItems('/states', ['state_read']);
        $this->assertEquals($itemsCount + 1, $newItemsCount);

        return $stateId;
    }

    /**
     * @depends testAddState
     */
    public function testGetState(int $stateId): int
    {
        $state = $this->getItem('/states/' . $stateId, ['state_read']);
        $this->assertEquals(
            [
                'stateId' => $stateId,
                'name' => 'Normandie',
                'isoCode' => 'FR-NOR',
                'countryId' => self::$countryIdFR,
                'zoneId' => self::$zoneIdEurope,
                'enabled' => false,
            ],
            $state
        );

        return $stateId;
    }

    /**
     * @depends testGetState
     */
    public function testPartialUpdateState(int $stateId): int
    {
        // 9.1.0 : Fixes partial update in PrestaShop/PrestaShop#40894
        if (version_compare(_PS_VERSION_, '9.1.0', '>=')) {
            // name
            $updatedState = $this->partialUpdateItem('/states/' . $stateId, [
                'name' => 'Tasmania',
            ], ['state_write']);
            $this->assertEquals(
                [
                    'stateId' => $stateId,
                    'name' => 'Tasmania',
                    'isoCode' => 'FR-NOR',
                    'countryId' => self::$countryIdFR,
                    'zoneId' => self::$zoneIdEurope,
                    'enabled' => false,
                ],
                $updatedState
            );

            // isoCode
            $updatedState = $this->partialUpdateItem('/states/' . $stateId, [
                'isoCode' => 'AU-TAS',
            ], ['state_write']);
            $this->assertEquals(
                [
                    'stateId' => $stateId,
                    'name' => 'Tasmania',
                    'isoCode' => 'AU-TAS',
                    'countryId' => self::$countryIdFR,
                    'zoneId' => self::$zoneIdEurope,
                    'enabled' => false,
                ],
                $updatedState
            );

            // countryId
            $updatedState = $this->partialUpdateItem('/states/' . $stateId, [
                'countryId' => self::$countryIdAU,
            ], ['state_write']);
            $this->assertEquals(
                [
                    'stateId' => $stateId,
                    'name' => 'Tasmania',
                    'isoCode' => 'AU-TAS',
                    'countryId' => self::$countryIdAU,
                    'zoneId' => self::$zoneIdEurope,
                    'enabled' => false,
                ],
                $updatedState
            );

            // zoneId
            $updatedState = $this->partialUpdateItem('/states/' . $stateId, [
                'zoneId' => self::$zoneIdOceania,
            ], ['state_write']);
            $this->assertEquals(
                [
                    'stateId' => $stateId,
                    'name' => 'Tasmania',
                    'isoCode' => 'AU-TAS',
                    'countryId' => self::$countryIdAU,
                    'zoneId' => self::$zoneIdOceania,
                    'enabled' => false,
                ],
                $updatedState
            );

            // enabled
            $updatedState = $this->partialUpdateItem('/states/' . $stateId, [
                'enabled' => true,
            ], ['state_write']);
            $this->assertEquals(
                [
                    'stateId' => $stateId,
                    'name' => 'Tasmania',
                    'isoCode' => 'AU-TAS',
                    'countryId' => self::$countryIdAU,
                    'zoneId' => self::$zoneIdOceania,
                    'enabled' => true,
                ],
                $updatedState
            );
        } else {
            $updatedState = $this->partialUpdateItem('/states/' . $stateId, [
                'stateId' => $stateId,
                'name' => 'Tasmania',
                'isoCode' => 'AU-TAS',
                'countryId' => self::$countryIdAU,
                'zoneId' => self::$zoneIdOceania,
                'enabled' => true,
            ], ['state_write']);
            $this->assertEquals(
                [
                    'stateId' => $stateId,
                    'name' => 'Tasmania',
                    'isoCode' => 'AU-TAS',
                    'countryId' => self::$countryIdAU,
                    'zoneId' => self::$zoneIdOceania,
                    'enabled' => true,
                ],
                $updatedState
            );
        }

        return $stateId;
    }

    /**
     * @depends testPartialUpdateState
     */
    public function testGetUpdatedState(int $stateId): int
    {
        $state = $this->getItem('/states/' . $stateId, ['state_read']);
        $this->assertEquals(
            [
                'stateId' => $stateId,
                'name' => 'Tasmania',
                'isoCode' => 'AU-TAS',
                'countryId' => self::$countryIdAU,
                'zoneId' => self::$zoneIdOceania,
                'enabled' => true,
            ],
            $state
        );

        return $stateId;
    }

    /**
     * @depends testGetUpdatedState
     */
    public function testToggleStatusState(int $stateId): int
    {
        $this->updateItem('/states/' . $stateId . '/toggle-status', [], ['state_write'], Response::HTTP_NO_CONTENT);
        $state = $this->getItem('/states/' . $stateId, ['state_read']);
        $this->assertEquals(
            [
                'stateId' => $stateId,
                'name' => 'Tasmania',
                'isoCode' => 'AU-TAS',
                'countryId' => self::$countryIdAU,
                'zoneId' => self::$zoneIdOceania,
                'enabled' => false,
            ],
            $state
        );

        return $stateId;
    }

    /**
     * @depends testToggleStatusState
     */
    public function testListStates(int $stateId): int
    {
        $states = $this->listItems('/states', ['state_read']);
        $this->assertCount(50, $states['items']);
        $this->assertEquals(353, $states['totalItems']);

        $states = $this->listItems('/states?limit=1000', ['state_read']);
        $this->assertCount(353, $states['items']);

        // Search for the one created previously during the tests and assert its data in the list
        $testState = null;
        foreach ($states['items'] as $state) {
            if ($state['stateId'] === $stateId) {
                $testState = $state;
            }
        }
        $this->assertNotNull($testState);
        $this->assertEquals(
            [
                'stateId' => $stateId,
                'name' => 'Tasmania',
                'isoCode' => 'AU-TAS',
                'countryName' => 'Australia',
                'zoneName' => 'Oceania',
                'enabled' => false,
            ],
            $testState
        );

        return $stateId;
    }

    /**
     * @depends testListStates
     */
    public function testDeleteState(int $stateId): void
    {
        $return = $this->deleteItem('/states/' . $stateId, ['state_write']);
        // This endpoint return empty response and 204 HTTP code
        $this->assertNull($return);

        // Getting the item should result in a 404 now
        $this->getItem('/states/' . $stateId, ['state_read'], Response::HTTP_NOT_FOUND);
    }

    /**
     * @depends testDeleteState
     *
     * @return array<int>
     */
    public function testBulkEnable(): array
    {
        $states = $this->listItems('/states', ['state_read']);

        $state1 = $this->createItem('/states', [
            'name' => 'Pays de la Loire',
            'isoCode' => 'FR-PDL',
            'countryId' => self::$countryIdFR,
            'zoneId' => self::$zoneIdEurope,
            'enabled' => false,
        ], ['state_write']);
        $this->assertArrayHasKey('stateId', $state1);
        $stateId1 = $state1['stateId'];

        $state2 = $this->createItem('/states', [
            'name' => 'Bretagne',
            'isoCode' => 'FR-BRE',
            'countryId' => self::$countryIdFR,
            'zoneId' => self::$zoneIdEurope,
            'enabled' => false,
        ], ['state_write']);
        $this->assertArrayHasKey('stateId', $state2);
        $stateId2 = $state2['stateId'];

        $states = $this->listItems('/states', ['state_read']);
        $this->assertGreaterThanOrEqual(4, $states['totalItems']);

        $bulkStates = [
            $stateId1,
            $stateId2,
        ];

        $this->updateItem('/states/bulk-set-status', [
            'stateIds' => $bulkStates,
            'enabled' => true,
        ], ['state_write'], Response::HTTP_NO_CONTENT);

        foreach ($bulkStates as $stateId) {
            $state = $this->getItem('/states/' . $stateId, ['state_read']);
            $this->assertEquals(true, $state['enabled']);
        }

        return $bulkStates;
    }

    /**
     * @depends testBulkEnable
     *
     * @param array<int> $bulkStates
     *
     * @return array<int>
     */
    public function testBulkDisable(array $bulkStates): array
    {
        $this->updateItem('/states/bulk-set-status', [
            'stateIds' => $bulkStates,
            'enabled' => false,
        ], ['state_write'], Response::HTTP_NO_CONTENT);

        foreach ($bulkStates as $stateId) {
            $state = $this->getItem('/states/' . $stateId, ['state_read']);
            $this->assertEquals(false, $state['enabled']);
        }

        return $bulkStates;
    }

    /**
     * @depends testBulkDisable
     *
     * @param array<int> $bulkStates
     */
    public function testBulkDelete(array $bulkStates): void
    {
        $this->updateItem('/states/bulk-delete', [
            'stateIds' => $bulkStates,
        ], ['state_write'], Response::HTTP_NO_CONTENT);

        // Assert the provided states have been removed
        foreach ($bulkStates as $stateId) {
            $this->getItem('/states/' . $stateId, ['state_read'], Response::HTTP_NOT_FOUND);
        }
    }

    public function testCreateInvalidState(): void
    {
        // Creating with invalid data should return a response with invalid constraint messages and use an http code 422
        $validationErrorsResponse = $this->createItem('/states', [
            'name' => '',
        ], ['state_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertIsArray($validationErrorsResponse);
        $this->assertValidationErrors([
            [
                'propertyPath' => 'name',
                'message' => 'This value should not be blank.',
            ],
            [
                'propertyPath' => 'enabled',
                'message' => 'This value should not be null.',
            ],
        ], $validationErrorsResponse);
    }
}
