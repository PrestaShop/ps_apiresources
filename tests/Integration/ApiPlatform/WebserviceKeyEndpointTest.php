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
            '/webservice-key',
        ];

        yield 'get endpoint' => [
            'GET',
            '/webservice-key/1',
        ];

        yield 'update endpoint' => [
            'PATCH',
            '/webservice-key/1',
        ];

        yield 'list endpoint' => [
            'GET',
            '/webservice-keys',
        ];
    }

    public function testAddWebserviceKey(): int
    {
        $itemsCount = $this->countItems('/webservice-keys', ['webservice_key_read']);

        $webserviceKey = $this->createItem('/webservice-key', [
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
     *
     * @param int $webserviceKeyId
     *
     * @return int
     */
    public function testGetWebserviceKey(int $webserviceKeyId): int
    {
        $webserviceKey = $this->getItem('/webservice-key/' . $webserviceKeyId, ['webservice_key_read']);
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
     *
     * @param int $webserviceKeyId
     *
     * @return int
     */
    public function testUpdateWebserviceKey(int $webserviceKeyId): int
    {
        // description
        $updatedWebserviceKey = $this->partialUpdateItem('/webservice-key/' . $webserviceKeyId, [
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
        $updatedWebserviceKey = $this->partialUpdateItem('/webservice-key/' . $webserviceKeyId, [
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
        $updatedWebserviceKey = $this->partialUpdateItem('/webservice-key/' . $webserviceKeyId, [
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
        $updatedWebserviceKey = $this->partialUpdateItem('/webservice-key/' . $webserviceKeyId, [
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
     *
     * @param int $webserviceKeyId
     *
     * @return int
     */
    public function testGetUpdatedWebserviceKey(int $webserviceKeyId): int
    {
        $webserviceKey = $this->getItem('/webservice-key/' . $webserviceKeyId, ['webservice_key_read']);
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
     *
     * @param int $webserviceKeyId
     *
     * @return int
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

    public function testCreateInvalidWebserviceKey(): void
    {
        // Creating with invalid data should return a response with invalid constraint messages and use an http code 422
        $validationErrorsResponse = $this->createItem('/webservice-key', [
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
