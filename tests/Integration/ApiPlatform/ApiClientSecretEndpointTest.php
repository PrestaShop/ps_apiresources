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

use PrestaShop\PrestaShop\Core\Domain\ApiClient\ValueObject\ApiClientSecret;
use Symfony\Component\HttpFoundation\Response;

class ApiClientSecretEndpointTest extends ApiTestCase
{
    private const FORCED_SECRET = 'ForcedSecretValue1234567890abcdef';

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::createApiClient(['api_client_read', 'api_client_write']);
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'generate secret endpoint' => ['PUT', '/api-clients/1/secrets'];
        yield 'force secret endpoint' => ['PATCH', '/api-clients/1/secrets'];
    }

    /**
     * The API client this test operates on is created through the API itself, so that no
     * fixture is built from the command bus or from raw SQL.
     */
    public function testCreateApiClient(): array
    {
        $apiClient = $this->createItem('/api-clients', [
            'clientId' => 'client_id_secret_test',
            'clientName' => 'Client name secret test',
            'description' => 'Client used by the secret endpoints test',
            'enabled' => true,
            'lifetime' => 3600,
            'scopes' => ['api_client_read'],
        ], ['api_client_write']);

        $this->assertEquals(['apiClientId', 'secret'], array_keys($apiClient));
        $this->assertGreaterThanOrEqual(ApiClientSecret::MIN_SIZE, strlen($apiClient['secret']));

        return $apiClient;
    }

    /**
     * @depends testCreateApiClient
     */
    public function testForceApiClientSecret(array $apiClient): array
    {
        // The secret is stored hashed, so there is nothing to read back: setting a known
        // secret answers an empty 204.
        $response = $this->partialUpdateItem(
            '/api-clients/' . $apiClient['apiClientId'] . '/secrets',
            ['secret' => self::FORCED_SECRET],
            ['api_client_write'],
            Response::HTTP_NO_CONTENT
        );

        $this->assertNull($response);

        return $apiClient;
    }

    public static function getInvalidSecrets(): iterable
    {
        yield 'missing secret' => [
            [],
            [['propertyPath' => 'secret', 'message' => 'This value should not be blank.']],
        ];
        yield 'too short secret' => [
            ['secret' => str_repeat('a', ApiClientSecret::MIN_SIZE - 1)],
            [['propertyPath' => 'secret', 'message' => sprintf('This value is too short. It should have %d characters or more.', ApiClientSecret::MIN_SIZE)]],
        ];
        yield 'too long secret' => [
            ['secret' => str_repeat('a', ApiClientSecret::MAX_SIZE + 1)],
            [['propertyPath' => 'secret', 'message' => sprintf('This value is too long. It should have %d characters or less.', ApiClientSecret::MAX_SIZE)]],
        ];
    }

    /**
     * The length is validated on the field before the command, so the secret sent is never
     * echoed back in the error.
     *
     * @depends testForceApiClientSecret
     *
     * @dataProvider getInvalidSecrets
     */
    public function testForceInvalidApiClientSecretIsRejected(array $data, array $expectedErrors, array $apiClient): void
    {
        $validationErrors = $this->partialUpdateItem(
            '/api-clients/' . $apiClient['apiClientId'] . '/secrets',
            $data,
            ['api_client_write'],
            Response::HTTP_UNPROCESSABLE_ENTITY
        );

        $this->assertIsArray($validationErrors);
        $this->assertValidationErrors($expectedErrors, $validationErrors);
        if (isset($data['secret'])) {
            $this->assertStringNotContainsString($data['secret'], json_encode($validationErrors));
        }
    }

    public function testForceSecretOfUnknownApiClient(): void
    {
        $this->partialUpdateItem(
            '/api-clients/999999/secrets',
            ['secret' => self::FORCED_SECRET],
            ['api_client_write'],
            Response::HTTP_NOT_FOUND
        );
    }

    /**
     * Requires PrestaShop/PrestaShop#41964: GenerateApiClientSecretCommand returns the secret
     * as a scalar, which the core CommandProcessor cannot forward to the API resource without
     * that fix. This test stays red until the core PR is merged into the target branches.
     *
     * @depends testForceApiClientSecret
     */
    public function testGenerateApiClientSecret(array $apiClient): void
    {
        // The body is empty: the core generates the secret itself
        $response = $this->updateItem(
            '/api-clients/' . $apiClient['apiClientId'] . '/secrets',
            [],
            ['api_client_write'],
            Response::HTTP_OK
        );

        // This is the caller's only chance to read the generated secret, so the operation
        // returns it, unlike the PATCH above
        $this->assertEquals(
            [
                'apiClientId' => $apiClient['apiClientId'],
                'secret' => $response['secret'] ?? null,
            ],
            $response
        );

        $this->assertNotEquals(self::FORCED_SECRET, $response['secret']);
        $this->assertNotEquals($apiClient['secret'], $response['secret']);
        $this->assertGreaterThanOrEqual(ApiClientSecret::MIN_SIZE, strlen($response['secret']));
        $this->assertLessThanOrEqual(ApiClientSecret::MAX_SIZE, strlen($response['secret']));
    }
}
