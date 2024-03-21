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

use Tests\Resources\DatabaseDump;

class ModuleEndpointTest extends ApiTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        DatabaseDump::restoreTables(['module']);
        self::createApiClient(['module_write', 'module_read']);
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        DatabaseDump::restoreTables(['module']);
    }

    public function getProtectedEndpoints(): iterable
    {
        yield 'get endpoint' => [
            'GET',
            '/module/1',
        ];

        yield 'bulk toggle' => [
            'PUT',
            '/modules/toggle-status',
        ];
    }

    public function testGetModuleInfos(): string
    {
        // Based on core fixtures and default data after install ps_languageselector should have the ID 6
        // This ID fetching can be improved when the listing ill be available
        $moduleInfos = $this->getModuleInfos(6);

        // Returned data has modified fields, the others haven't changed
        $this->assertEquals(
            [
                'moduleId' => 6,
                'technical_name' => 'ps_languageselector',
                'version' => '2.1.3',
                'enabled' => true,
            ],
            $moduleInfos
        );

        return $moduleInfos['technical_name'];
    }

    /**
     * @depends testGetModuleInfos
     *
     * @param string $technicalName
     */
    public function testBulkUpdateStatus(string $technicalName): void
    {
        // Bulk disable on one module
        $bearerToken = $this->getBearerToken(['module_read', 'module_write']);
        static::createClient()->request('PUT', '/modules/toggle-status', [
            'auth_bearer' => $bearerToken,
            'json' => [
                'modules' => [
                    $technicalName,
                ],
                'enabled' => false,
            ],
        ]);
        self::assertResponseStatusCodeSame(204);

        // Check updated disabled status
        $moduleInfos = $this->getModuleInfos(6);
        $this->assertFalse($moduleInfos['enabled']);

        // Bulk enable on one module
        static::createClient()->request('PUT', '/modules/toggle-status', [
            'auth_bearer' => $bearerToken,
            'json' => [
                'modules' => [
                    $technicalName,
                ],
                'enabled' => true,
            ],
        ]);
        self::assertResponseStatusCodeSame(204);

        // Check updated enabled status
        $moduleInfos = $this->getModuleInfos(6);
        $this->assertTrue($moduleInfos['enabled']);
    }

    private function getModuleInfos(int $moduleId): array
    {
        $bearerToken = $this->getBearerToken(['module_read']);
        $response = static::createClient()->request('GET', '/module/' . $moduleId, [
            'auth_bearer' => $bearerToken,
        ]);
        self::assertResponseStatusCodeSame(200);

        $decodedResponse = json_decode($response->getContent(), true);
        $this->assertNotFalse($decodedResponse);

        return $decodedResponse;
    }
}
