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

use PrestaShop\PrestaShop\Core\Domain\ApiClient\ApiClientSettings;
use PrestaShop\PrestaShop\Core\Util\String\RandomString;
use Symfony\Component\HttpFoundation\Response;

class ApiClientEndpointTest extends ApiTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        // Pre-create the API Client with the needed scopes, this way we reduce the number of created API Clients
        self::createApiClient(['api_client_write', 'api_client_read']);
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'get endpoint' => [
            'GET',
            '/api-client/1',
        ];

        yield 'create endpoint' => [
            'POST',
            '/api-client',
        ];

        yield 'update endpoint' => [
            'PATCH',
            '/api-client/1',
        ];

        yield 'delete endpoint' => [
            'DELETE',
            '/api-client/1',
        ];

        yield 'list endpoint' => [
            'GET',
            '/api-clients',
        ];
    }

    public function testAddApiClient(): int
    {
        $itemsCount = $this->countItems('/api-clients', ['api_client_read']);

        $apiClient = $this->createItem('/api-client', [
            'clientId' => 'client_id_test',
            'clientName' => 'Client name test',
            'description' => 'Client description test',
            // Check that false can be used (previously bug because of a NotBlank constraint)
            'enabled' => false,
            'lifetime' => 3600,
            'scopes' => [
                'api_client_read',
                'hook_read',
            ],
        ], ['api_client_write']);
        $this->assertArrayHasKey('apiClientId', $apiClient);
        $this->assertArrayHasKey('secret', $apiClient);
        $apiClientId = $apiClient['apiClientId'];
        $secret = $apiClient['secret'];
        $this->assertEquals(
            [
                'apiClientId' => $apiClientId,
                'secret' => $secret,
            ],
            $apiClient
        );

        $newItemsCount = $this->countItems('/api-clients', ['api_client_read']);
        $this->assertEquals($itemsCount + 1, $newItemsCount);

        return $apiClientId;
    }

    /**
     * @depends testAddApiClient
     *
     * @param int $apiClientId
     *
     * @return int
     */
    public function testGetApiClient(int $apiClientId): int
    {
        $apiClient = $this->getItem('/api-client/' . $apiClientId, ['api_client_read']);
        $this->assertEquals(
            [
                'apiClientId' => $apiClientId,
                'clientId' => 'client_id_test',
                'clientName' => 'Client name test',
                'description' => 'Client description test',
                'externalIssuer' => null,
                'enabled' => false,
                'lifetime' => 3600,
                'scopes' => [
                    'api_client_read',
                    'hook_read',
                ],
            ],
            $apiClient
        );

        return $apiClientId;
    }

    /**
     * @depends testGetApiClient
     *
     * @param int $apiClientId
     *
     * @return int
     */
    public function testUpdateApiClient(int $apiClientId): int
    {
        // Update API client
        $updatedApiClient = $this->partialUpdateItem('/api-client/' . $apiClientId, [
            'clientId' => 'client_id_test_updated',
            'clientName' => 'Client name test updated',
            'description' => 'Client description test updated',
            'enabled' => true,
            'lifetime' => 1800,
            'scopes' => [
                'api_client_write',
                'hook_read',
            ],
        ], ['api_client_write']);
        $this->assertEquals(
            [
                'apiClientId' => $apiClientId,
                'clientId' => 'client_id_test_updated',
                'clientName' => 'Client name test updated',
                'description' => 'Client description test updated',
                'externalIssuer' => null,
                'enabled' => true,
                'lifetime' => 1800,
                'scopes' => [
                    'api_client_write',
                    'hook_read',
                ],
            ],
            $updatedApiClient
        );

        // Update partially API client
        $updatedApiClient = $this->partialUpdateItem('/api-client/' . $apiClientId, [
            'description' => 'Client description test partially updated',
            'lifetime' => 900,
            // Even if we try to modify the external issuer it is an immutable/internal field anyway
            'externalIssuer' => 'http://not-possible-to-modify',
        ], ['api_client_write']);

        // Returned data has modified fields, the others haven't changed
        $this->assertEquals([
            'apiClientId' => $apiClientId,
            'clientId' => 'client_id_test_updated',
            'clientName' => 'Client name test updated',
            'description' => 'Client description test partially updated',
            'externalIssuer' => null,
            'enabled' => true,
            'lifetime' => 900,
            'scopes' => [
                'api_client_write',
                'hook_read',
            ],
        ], $updatedApiClient);

        return $apiClientId;
    }

    /**
     * @depends testUpdateApiClient
     *
     * @param int $apiClientId
     *
     * @return int
     */
    public function testGetUpdatedApiClient(int $apiClientId): int
    {
        $apiClient = $this->getItem('/api-client/' . $apiClientId, ['api_client_read']);
        $this->assertEquals(
            [
                'apiClientId' => $apiClientId,
                'clientId' => 'client_id_test_updated',
                'clientName' => 'Client name test updated',
                'description' => 'Client description test partially updated',
                'externalIssuer' => null,
                'enabled' => true,
                'lifetime' => 900,
                'scopes' => [
                    'api_client_write',
                    'hook_read',
                ],
            ],
            $apiClient
        );

        return $apiClientId;
    }

    /**
     * @depends testGetUpdatedApiClient
     *
     * @param int $apiClientId
     *
     * @return int
     */
    public function testListApiClients(int $apiClientId): int
    {
        $apiClients = $this->listItems('/api-clients', ['api_client_read']);
        // At least two API Clients, the one created for test to actually use the API and the one created in the previous test
        $this->assertGreaterThanOrEqual(2, $apiClients['totalItems']);

        // Search for the one created previously during the tests and assert its data in the list
        $testApiClient = null;
        foreach ($apiClients['items'] as $apiClient) {
            if ($apiClient['clientId'] === 'client_id_test_updated') {
                $testApiClient = $apiClient;
            }
        }
        $this->assertNotNull($testApiClient);
        $this->assertEquals(
            [
                'apiClientId' => $apiClientId,
                'clientId' => 'client_id_test_updated',
                'clientName' => 'Client name test updated',
                'description' => 'Client description test partially updated',
                'externalIssuer' => null,
                'enabled' => true,
                'lifetime' => 900,
            ],
            $testApiClient
        );

        return $apiClientId;
    }

    /**
     * @depends testListApiClients
     *
     * @param int $apiClientId
     */
    public function testDeleteApiClient(int $apiClientId): void
    {
        // Delete API client without token is unauthorized (401)
        $errorMessage = $this->deleteItem('/api-client/' . $apiClientId, [], Response::HTTP_UNAUTHORIZED);
        $this->assertEquals('No Authorization header provided', $errorMessage);

        // Delete API client with invalid token
        static::createClient()->request('DELETE', '/api-client/' . $apiClientId, [
            'auth_bearer' => 'toto',
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);

        // Try to delete with a token with only read scope is forbidden (403)
        $this->deleteItem('/api-client/' . $apiClientId, ['api_client_read'], Response::HTTP_FORBIDDEN);

        // Check that API client was not deleted
        $this->assertNotNull($this->getItem('/api-client/' . $apiClientId, ['api_client_read']));

        // Delete API client with valid token
        $this->deleteItem('/api-client/' . $apiClientId, ['api_client_write']);

        $errorResponse = $this->getItem('/api-client/' . $apiClientId, ['api_client_read'], Response::HTTP_NOT_FOUND);
        $this->assertEquals('Could not find Api client ' . $apiClientId, $errorResponse['detail']);
    }

    public function testCreateInvalidApiClient(): void
    {
        // Creating with invalid data should return a response with invalid constraint messages and use an http code 422
        $validationErrorsResponse = $this->createItem('/api-client', [
            'clientId' => '',
            'clientName' => '',
            'description' => RandomString::generate(ApiClientSettings::MAX_DESCRIPTION_LENGTH + 1),
            'lifetime' => 0,
        ], ['api_client_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertIsArray($validationErrorsResponse);
        $this->assertValidationErrors([
            [
                'propertyPath' => 'clientId',
                'message' => 'This value should not be blank.',
            ],
            [
                'propertyPath' => 'clientId',
                'message' => 'This value is too short. It should have 1 character or more.',
            ],
            [
                'propertyPath' => 'clientName',
                'message' => 'This value should not be blank.',
            ],
            [
                'propertyPath' => 'clientName',
                'message' => 'This value is too short. It should have 1 character or more.',
            ],
            [
                'propertyPath' => 'lifetime',
                'message' => 'This value should be positive.',
            ],
            [
                'propertyPath' => 'enabled',
                'message' => 'This value should not be null.',
            ],
            [
                'propertyPath' => 'description',
                'message' => sprintf('This value is too long. It should have %d characters or less.', ApiClientSettings::MAX_DESCRIPTION_LENGTH),
            ],
        ], $validationErrorsResponse);
    }
}
