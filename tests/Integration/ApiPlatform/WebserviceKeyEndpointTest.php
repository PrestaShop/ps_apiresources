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

class WebserviceKeyEndpointTest extends ApiTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        // Pre-create the API Client with the needed scopes, this way we reduce the number of created API Clients
        self::createApiClient(['webservice_key_write', 'webservice_key_read']);
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        // Reset DB as it was before this test
        DatabaseDump::restoreTables(['webservice_account', 'webservice_account_shop', 'webservice_permission']);
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'create endpoint' => [
            'POST',
            '/webservice-keys',
        ];

        yield 'get endpoint' => [
            'GET',
            '/webservice-keys/1',
        ];

        yield 'update endpoint' => [
            'PATCH',
            '/webservice-keys/1',
        ];

        yield 'delete endpoint' => [
            'DELETE',
            '/webservice-keys/1',
        ];

        yield 'list endpoint' => [
            'GET',
            '/webservice-keys',
        ];

        yield 'bulk delete endpoint' => [
            'PUT',
            '/webservice-keys/bulk-delete',
        ];
    }

    public function testAddWebserviceKey(): int
    {
        $itemsCount = $this->countItems('/webservice-keys', ['webservice_key_read']);

        $webserviceKey = $this->createItem('/webservice-keys', [
            'key' => 'AZERTYUIOPAZERTYUIOPAZERTYUIOPAZ',
            'description' => 'Webservice Key test',
            'enabled' => false,
            'permissions' => [
                'DELETE' => ['addresses'],
                'GET' => ['addresses'],
                'HEAD' => ['addresses', 'carriers'],
                'PATCH' => ['addresses', 'carriers'],
                'PUT' => ['addresses', 'carriers', 'carts'],
                'POST' => ['addresses', 'carriers', 'carts'],
            ],
            'shopIds' => [1],
        ], ['webservice_key_write']);
        $this->assertArrayHasKey('webserviceKeyId', $webserviceKey);
        $webserviceKeyId = $webserviceKey['webserviceKeyId'];
        $this->assertEquals(
            [
                'webserviceKeyId' => $webserviceKeyId,
            ],
            $webserviceKey
        );

        $newItemsCount = $this->countItems('/webservice-keys', ['webservice_key_read']);
        $this->assertEquals($itemsCount + 1, $newItemsCount);

        return $webserviceKeyId;
    }

    /**
     * @depends testAddWebserviceKey
     */
    public function testGetWebserviceKey(int $webserviceKeyId): int
    {
        $webserviceKey = $this->getItem('/webservice-keys/' . $webserviceKeyId, ['webservice_key_read']);
        $this->assertEquals(
            [
                'webserviceKeyId' => $webserviceKeyId,
                'description' => 'Webservice Key test',
                'key' => 'AZERTYUIOPAZERTYUIOPAZERTYUIOPAZ',
                'enabled' => false,
                'permissions' => [
                    'addresses' => [
                        'DELETE',
                        'GET',
                        'HEAD',
                        'PATCH',
                        'PUT',
                        'POST',
                    ],
                    'carriers' => [
                        'HEAD',
                        'PATCH',
                        'PUT',
                        'POST',
                    ],
                    'carts' => [
                        'PUT',
                        'POST',
                    ],
                ],
                'shopIds' => [1],
            ],
            $webserviceKey
        );

        return $webserviceKeyId;
    }

    /**
     * @depends testGetWebserviceKey
     */
    public function testUpdateWebserviceKey(int $webserviceKeyId): int
    {
        // description
        $updatedWebserviceKey = $this->partialUpdateItem('/webservice-keys/' . $webserviceKeyId, [
            'description' => 'Webservice Key test updated',
        ], ['webservice_key_write']);
        $this->assertEquals(
            [
                'webserviceKeyId' => $webserviceKeyId,
                'description' => 'Webservice Key test updated',
                'key' => 'AZERTYUIOPAZERTYUIOPAZERTYUIOPAZ',
                'enabled' => false,
                'permissions' => [
                    'addresses' => [
                        'DELETE',
                        'GET',
                        'HEAD',
                        'PATCH',
                        'PUT',
                        'POST',
                    ],
                    'carriers' => [
                        'HEAD',
                        'PATCH',
                        'PUT',
                        'POST',
                    ],
                    'carts' => [
                        'PUT',
                        'POST',
                    ],
                ],
                'shopIds' => [1],
            ],
            $updatedWebserviceKey
        );

        // key
        $updatedWebserviceKey = $this->partialUpdateItem('/webservice-keys/' . $webserviceKeyId, [
            'key' => 'AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA',
        ], ['webservice_key_write']);
        $this->assertEquals(
            [
                'webserviceKeyId' => $webserviceKeyId,
                'description' => 'Webservice Key test updated',
                'key' => 'AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA',
                'enabled' => false,
                'permissions' => [
                    'addresses' => [
                        'DELETE',
                        'GET',
                        'HEAD',
                        'PATCH',
                        'PUT',
                        'POST',
                    ],
                    'carriers' => [
                        'HEAD',
                        'PATCH',
                        'PUT',
                        'POST',
                    ],
                    'carts' => [
                        'PUT',
                        'POST',
                    ],
                ],
                'shopIds' => [1],
            ],
            $updatedWebserviceKey
        );

        // enabled
        $updatedWebserviceKey = $this->partialUpdateItem('/webservice-keys/' . $webserviceKeyId, [
            'enabled' => true,
        ], ['webservice_key_write']);
        $this->assertEquals(
            [
                'webserviceKeyId' => $webserviceKeyId,
                'description' => 'Webservice Key test updated',
                'key' => 'AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA',
                'enabled' => true,
                'permissions' => [
                    'addresses' => [
                        'DELETE',
                        'GET',
                        'HEAD',
                        'PATCH',
                        'PUT',
                        'POST',
                    ],
                    'carriers' => [
                        'HEAD',
                        'PATCH',
                        'PUT',
                        'POST',
                    ],
                    'carts' => [
                        'PUT',
                        'POST',
                    ],
                ],
                'shopIds' => [1],
            ],
            $updatedWebserviceKey
        );

        // enabled
        $updatedWebserviceKey = $this->partialUpdateItem('/webservice-keys/' . $webserviceKeyId, [
            'permissions' => [
                'DELETE' => ['carts', 'carriers', 'addresses'],
                'GET' => ['carts', 'carriers', 'addresses'],
                'HEAD' => ['carts', 'carriers'],
                'PATCH' => ['carts', 'carriers'],
                'PUT' => ['carts'],
                'POST' => ['carts'],
            ],
        ], ['webservice_key_write']);
        $this->assertEquals(
            [
                'webserviceKeyId' => $webserviceKeyId,
                'description' => 'Webservice Key test updated',
                'key' => 'AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA',
                'enabled' => true,
                'permissions' => [
                    'addresses' => [
                        'DELETE',
                        'GET',
                    ],
                    'carriers' => [
                        'DELETE',
                        'GET',
                        'HEAD',
                        'PATCH',
                    ],
                    'carts' => [
                        'DELETE',
                        'GET',
                        'HEAD',
                        'PATCH',
                        'PUT',
                        'POST',
                    ], ],
                'shopIds' => [1],
            ],
            $updatedWebserviceKey
        );

        return $webserviceKeyId;
    }

    /**
     * @depends testUpdateWebserviceKey
     */
    public function testGetUpdatedWebserviceKey(int $webserviceKeyId): int
    {
        $webserviceKey = $this->getItem('/webservice-keys/' . $webserviceKeyId, ['webservice_key_read']);
        $this->assertEquals(
            [
                'webserviceKeyId' => $webserviceKeyId,
                'description' => 'Webservice Key test updated',
                'key' => 'AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA',
                'enabled' => true,
                'permissions' => [
                    'addresses' => [
                        'DELETE',
                        'GET',
                    ],
                    'carriers' => [
                        'DELETE',
                        'GET',
                        'HEAD',
                        'PATCH',
                    ],
                    'carts' => [
                        'DELETE',
                        'GET',
                        'HEAD',
                        'PATCH',
                        'PUT',
                        'POST',
                    ],
                ],
                'shopIds' => [1],
            ],
            $webserviceKey
        );

        return $webserviceKeyId;
    }

    /**
     * @depends testGetUpdatedWebserviceKey
     */
    public function testListWebserviceKeys(int $webserviceKeyId): int
    {
        $webserviceKeys = $this->listItems('/webservice-keys', ['webservice_key_read']);
        // At least two API Clients, the one created for test to actually use the API and the one created in the previous test
        $this->assertGreaterThanOrEqual(1, $webserviceKeys['totalItems']);

        // Search for the one created previously during the tests and assert its data in the list
        $testWebserviceKey = null;
        foreach ($webserviceKeys['items'] as $webserviceKey) {
            if ($webserviceKey['webserviceKeyId'] === $webserviceKeyId) {
                $testWebserviceKey = $webserviceKey;
            }
        }
        $this->assertNotNull($testWebserviceKey);
        $this->assertEquals(
            [
                'webserviceKeyId' => $webserviceKeyId,
                'description' => 'Webservice Key test updated',
                'key' => 'AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA',
                'enabled' => true,
            ],
            $testWebserviceKey
        );

        return $webserviceKeyId;
    }

    /**
     * @depends testListWebserviceKeys
     */
    public function testDelete(int $webserviceKeyId): void
    {
        $return = $this->deleteItem('/webservice-keys/' . $webserviceKeyId, ['webservice_key_write']);
        // This endpoint return empty response and 204 HTTP code
        $this->assertNull($return);

        // Getting the item should result in a 404 now
        $this->getItem('/webservice-keys/' . $webserviceKeyId, ['webservice_key_read'], Response::HTTP_NOT_FOUND);
    }

    /**
     * @depends testListWebserviceKeys
     */
    public function testBulkDelete(): void
    {
        $itemsCount = $this->countItems('/webservice-keys', ['webservice_key_read']);
        $this->assertEquals(0, $itemsCount);

        $webserviceKey1 = $this->createItem('/webservice-keys', [
            'key' => 'AZERTYUIOPAZERTYUIOPAZERTYUIOPAZ',
            'description' => 'Webservice Key test',
            'enabled' => false,
            'permissions' => [
                'DELETE' => ['addresses'],
                'GET' => ['addresses'],
                'HEAD' => ['addresses', 'carriers'],
                'PATCH' => ['addresses', 'carriers'],
                'PUT' => ['addresses', 'carriers', 'carts'],
                'POST' => ['addresses', 'carriers', 'carts'],
            ],
            'shopIds' => [1],
        ], ['webservice_key_write']);
        $this->assertArrayHasKey('webserviceKeyId', $webserviceKey1);
        $webserviceKeyId1 = $webserviceKey1['webserviceKeyId'];

        $webserviceKey2 = $this->createItem('/webservice-keys', [
            'key' => 'AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA',
            'description' => 'Webservice Key test',
            'enabled' => false,
            'permissions' => [
                'DELETE' => ['addresses'],
                'GET' => ['addresses'],
                'HEAD' => ['addresses', 'carriers'],
                'PATCH' => ['addresses', 'carriers'],
                'PUT' => ['addresses', 'carriers', 'carts'],
                'POST' => ['addresses', 'carriers', 'carts'],
            ],
            'shopIds' => [1],
        ], ['webservice_key_write']);
        $this->assertArrayHasKey('webserviceKeyId', $webserviceKey2);
        $webserviceKeyId2 = $webserviceKey2['webserviceKeyId'];

        $itemsCount = $this->countItems('/webservice-keys', ['webservice_key_read']);
        $this->assertEquals(2, $itemsCount);

        // We remove the two webservice keys
        $bulkWebserviceKeyIds = [
            $webserviceKeyId1,
            $webserviceKeyId2,
        ];

        $this->updateItem('/webservice-keys/bulk-delete', [
            'webserviceKeyIds' => $bulkWebserviceKeyIds,
        ], ['webservice_key_write'], Response::HTTP_NO_CONTENT);

        // Assert the provided webservice keys have been removed
        foreach ($bulkWebserviceKeyIds as $webserviceKeyId) {
            $this->getItem('/webservice-keys/' . $webserviceKeyId, ['webservice_key_read'], Response::HTTP_NOT_FOUND);
        }

        $itemsCount = $this->countItems('/webservice-keys', ['webservice_key_read']);
        $this->assertEquals(0, $itemsCount);
    }

    public function testCreateInvalidWebserviceKey(): void
    {
        // Creating with invalid data should return a response with invalid constraint messages and use an http code 422
        $validationErrorsResponse = $this->createItem('/webservice-keys', [
            'key' => '',
            'description' => '',
        ], ['webservice_key_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertIsArray($validationErrorsResponse);
        $this->assertValidationErrors([
            [
                'propertyPath' => 'key',
                'message' => 'This value should not be blank.',
            ],
            [
                'propertyPath' => 'key',
                'message' => 'This value should have exactly 32 characters.',
            ],
            [
                'propertyPath' => 'description',
                'message' => 'This value should not be blank.',
            ],
            [
                'propertyPath' => 'description',
                'message' => 'This value is too short. It should have 1 character or more.',
            ],
            [
                'propertyPath' => 'enabled',
                'message' => 'This value should not be null.',
            ],
        ], $validationErrorsResponse);
    }
}
