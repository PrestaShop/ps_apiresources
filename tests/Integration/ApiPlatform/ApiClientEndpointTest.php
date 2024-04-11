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

class ApiClientEndpointTest extends ApiTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::createApiClient(['api_client_write', 'api_client_read']);
    }

    public function getProtectedEndpoints(): iterable
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
        $bearerToken = $this->getBearerToken(['api_client_write']);
        $response = static::createClient()->request('POST', '/api-client', [
            'auth_bearer' => $bearerToken,
            'json' => [
                'clientId' => 'client_id_test',
                'clientName' => 'Client name test',
                'description' => 'Client description test',
                'enabled' => true,
                'lifetime' => 3600,
                'scopes' => [
                    'api_client_read',
                    'hook_read',
                ],
            ],
        ]);
        self::assertResponseStatusCodeSame(201);
        $decodedResponse = json_decode($response->getContent(), true);
        $this->assertNotFalse($decodedResponse);
        $this->assertArrayHasKey('apiClientId', $decodedResponse);
        $this->assertArrayHasKey('secret', $decodedResponse);
        $apiClientId = $decodedResponse['apiClientId'];
        $secret = $decodedResponse['secret'];
        $this->assertEquals(
            [
                'apiClientId' => $apiClientId,
                'secret' => $secret,
            ],
            $decodedResponse
        );

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
        $bearerToken = $this->getBearerToken(['api_client_read']);
        $response = static::createClient()->request('GET', '/api-client/' . $apiClientId, [
            'auth_bearer' => $bearerToken,
        ]);
        self::assertResponseStatusCodeSame(200);

        $decodedResponse = json_decode($response->getContent(), true);
        $this->assertNotFalse($decodedResponse);
        $this->assertEquals(
            [
                'apiClientId' => $apiClientId,
                'clientId' => 'client_id_test',
                'clientName' => 'Client name test',
                'description' => 'Client description test',
                'externalIssuer' => null,
                'enabled' => true,
                'lifetime' => 3600,
                'scopes' => [
                    'api_client_read',
                    'hook_read',
                ],
            ],
            $decodedResponse
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
        $bearerToken = $this->getBearerToken(['api_client_write']);

        // Update API client
        $response = static::createClient()->request('PATCH', '/api-client/' . $apiClientId, [
            'auth_bearer' => $bearerToken,
            'json' => [
                'clientId' => 'client_id_test_updated',
                'clientName' => 'Client name test updated',
                'description' => 'Client description test updated',
                'enabled' => false,
                'lifetime' => 1800,
                'scopes' => [
                    'api_client_write',
                    'hook_read',
                ],
            ],
        ]);
        self::assertResponseStatusCodeSame(200);

        $decodedResponse = json_decode($response->getContent(), true);
        $this->assertNotFalse($decodedResponse);
        // Returned data has modified fields, the others haven't changed
        $this->assertEquals(
            [
                'apiClientId' => $apiClientId,
                'clientId' => 'client_id_test_updated',
                'clientName' => 'Client name test updated',
                'description' => 'Client description test updated',
                'externalIssuer' => null,
                'enabled' => false,
                'lifetime' => 1800,
                'scopes' => [
                    'api_client_write',
                    'hook_read',
                ],
            ],
            $decodedResponse
        );

        // Update partially API client
        $response = static::createClient()->request('PATCH', '/api-client/' . $apiClientId, [
            'auth_bearer' => $bearerToken,
            'json' => [
                'description' => 'Client description test partially updated',
                'lifetime' => 900,
                'externalIssuer' => 'http://not-possible-to-modify',
            ],
        ]);
        self::assertResponseStatusCodeSame(200);

        $decodedResponse = json_decode($response->getContent(), true);
        $this->assertNotFalse($decodedResponse);
        // Returned data has modified fields, the others haven't changed
        $this->assertEquals(
            [
                'apiClientId' => $apiClientId,
                'clientId' => 'client_id_test_updated',
                'clientName' => 'Client name test updated',
                'description' => 'Client description test partially updated',
                'externalIssuer' => null,
                'enabled' => false,
                'lifetime' => 900,
                'scopes' => [
                    'api_client_write',
                    'hook_read',
                ],
            ],
            $decodedResponse
        );

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
        $bearerToken = $this->getBearerToken(['api_client_read']);
        $response = static::createClient()->request('GET', '/api-client/' . $apiClientId, [
            'auth_bearer' => $bearerToken,
        ]);
        self::assertResponseStatusCodeSame(200);

        $decodedResponse = json_decode($response->getContent(), true);
        $this->assertNotFalse($decodedResponse);
        $this->assertEquals(
            [
                'apiClientId' => $apiClientId,
                'clientId' => 'client_id_test_updated',
                'clientName' => 'Client name test updated',
                'description' => 'Client description test partially updated',
                'externalIssuer' => null,
                'enabled' => false,
                'lifetime' => 900,
                'scopes' => [
                    'api_client_write',
                    'hook_read',
                ],
            ],
            $decodedResponse
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
        // Two APi Clients, the one created for test to actually use the API and the one created in the previous test
        $this->assertEquals(2, $apiClients['totalItems']);

        // Test content from the second (most recent) api client created
        $this->assertEquals(
            [
                'apiClientId' => $apiClientId,
                'clientId' => 'client_id_test_updated',
                'clientName' => 'Client name test updated',
                'description' => 'Client description test partially updated',
                'externalIssuer' => null,
                'enabled' => false,
                'lifetime' => 900,
            ],
            $apiClients['items'][1],
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
        // Delete API client without token
        static::createClient()->request('DELETE', '/api-client/' . $apiClientId);
        self::assertResponseStatusCodeSame(401);
        // Delete API client without token
        static::createClient()->request('DELETE', '/api-client/' . $apiClientId, [
            'auth_bearer' => 'toto',
        ]);
        self::assertResponseStatusCodeSame(401);

        // Try to delete with a token with only read scope
        $readBearerToken = $this->getBearerToken(['api_client_read']);
        $response = static::createClient()->request('DELETE', '/api-client/' . $apiClientId, [
            'auth_bearer' => $readBearerToken,
        ]);
        $this->assertEquals(403, $response->getStatusCode());
        self::assertResponseStatusCodeSame(403);

        // Check that API client was not deleted
        static::createClient()->request('GET', '/api-client/' . $apiClientId, [
            'auth_bearer' => $readBearerToken,
        ]);
        self::assertResponseStatusCodeSame(200);

        // Delete API client with valid token
        $writeBearerToken = $this->getBearerToken(['api_client_write']);
        $response = static::createClient()->request('DELETE', '/api-client/' . $apiClientId, [
            'auth_bearer' => $writeBearerToken,
        ]);
        self::assertResponseStatusCodeSame(204);
        $this->assertEmpty($response->getContent());

        static::createClient()->request('GET', '/api-client/' . $apiClientId, [
            'auth_bearer' => $readBearerToken,
        ]);
        self::assertResponseStatusCodeSame(404);
    }
}
