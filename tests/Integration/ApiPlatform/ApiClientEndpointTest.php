<?php
/**
 * Copyright since 2007 PrestaShop SA and Contributors
 * PrestaShop is an International Registered Trademark & Property of PrestaShop SA
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/OSL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to https://devdocs.prestashop.com/ for more information.
 *
 * @author    PrestaShop SA and Contributors <contact@prestashop.com>
 * @copyright Since 2007 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
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

    /**
     * @dataProvider getProtectedEndpoints
     *
     * @param string $method
     * @param string $uri
     */
    public function testProtectedEndpoints(string $method, string $uri, string $contentType = 'application/json'): void
    {
        $options['headers']['content-type'] = $contentType;
        // Check that endpoints are not accessible without a proper Bearer token
        $client = static::createClient([], $options);
        $response = $client->request($method, $uri);
        self::assertResponseStatusCodeSame(401);

        $content = $response->getContent(false);
        $this->assertNotEmpty($content);
        $this->assertEquals('No Authorization header provided', $content);
    }

    public function getProtectedEndpoints(): iterable
    {
        yield 'get endpoint' => [
            'GET',
            '/api/api-client/1',
        ];

        yield 'create endpoint' => [
            'POST',
            '/api/api-client',
        ];

        yield 'update endpoint' => [
            'PUT',
            '/api/api-client/1',
        ];

        yield 'delete endpoint' => [
            'DELETE',
            '/api/api-client/1',
            'application/merge-patch+json',
        ];
    }

    public function testAddApiClient(): int
    {
        $bearerToken = $this->getBearerToken(['api_client_write']);
        $client = static::createClient();
        $response = $client->request('POST', '/api/api-client', [
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
        $client = static::createClient();
        $response = $client->request('GET', '/api/api-client/' . $apiClientId, [
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
        $client = static::createClient();

        // Update product with partial data, even multilang fields can be updated language by language
        $response = $client->request('PUT', '/api/api-client/' . $apiClientId, [
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
                'enabled' => false,
                'lifetime' => 1800,
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
        $client = static::createClient();
        $response = $client->request('GET', '/api/api-client/' . $apiClientId, [
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
                'description' => 'Client description test updated',
                'enabled' => false,
                'lifetime' => 1800,
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
     */
    public function testDeleteApiClient(int $apiClientId): void
    {
        $bearerToken = $this->getBearerToken(['api_client_read', 'api_client_write']);
        $client = static::createClient();

        // Delete API client without token
        $client->request('DELETE', '/api/api-client/' . $apiClientId);
        self::assertResponseStatusCodeSame(401);
        // Delete API client without token
        $client->request('DELETE', '/api/api-client/' . $apiClientId, [
            'auth_bearer' => 'toto',
        ]);
        self::assertResponseStatusCodeSame(401);

        // Check that API client was not deleted
        $client->request('GET', '/api/api-client/' . $apiClientId, [
            'auth_bearer' => $bearerToken,
        ]);
        self::assertResponseStatusCodeSame(200);

        // Delete API client with valid token
        $response = $client->request('DELETE', '/api/api-client/' . $apiClientId, [
            'auth_bearer' => $bearerToken,
        ]);
        self::assertResponseStatusCodeSame(204);
        $this->assertEmpty($response->getContent());

        $client = static::createClient();
        $client->request('GET', '/api/api-client/' . $apiClientId, [
            'auth_bearer' => $bearerToken,
        ]);
        self::assertResponseStatusCodeSame(404);
    }
}
