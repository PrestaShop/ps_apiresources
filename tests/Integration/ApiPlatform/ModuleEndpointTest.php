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

use Module;
use Tests\Resources\DatabaseDump;

class ModuleEndpointTest extends ApiTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        DatabaseDump::restoreTables(['module', 'module_shop']);
        self::createApiClient(['module_write', 'module_read']);
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        DatabaseDump::restoreTables(['module', 'module_shop']);
    }

    public function getProtectedEndpoints(): iterable
    {
        yield 'get endpoint' => [
            'GET',
            '/module/1',
        ];

        yield 'list modules' => [
            'GET',
            '/modules',
        ];

        yield 'bulk toggle' => [
            'PUT',
            '/modules/toggle-status',
        ];
    }

    public function testListModules(): int
    {
        $modules = $this->listItems('/modules', ['module_read']);
        $this->assertGreaterThan(1, $modules['totalItems']);

        $modules = $this->listItems('/modules', ['module_read'], ['technicalName' => 'ps_apiresources']);
        $this->assertEquals(1, $modules['totalItems']);
        $apiModule = $modules['items'][0];
        $this->assertEquals('ps_apiresources', $apiModule['technicalName']);
        $this->assertTrue($apiModule['enabled']);
        $this->assertTrue(version_compare($apiModule['version'], '0.1.0', '>='));
        $this->assertGreaterThan(0, $apiModule['moduleId']);

        return $apiModule['moduleId'];
    }

    /**
     * @depends testListModules
     */
    public function testGetModuleInfos(int $moduleId): string
    {
        $moduleInfos = $this->getModuleInfos($moduleId);

        // Returned data has modified fields, the others haven't changed
        $this->assertArrayHasKey('version', $moduleInfos);
        $version = $moduleInfos['version'];
        $this->assertEquals(
            [
                'moduleId' => $moduleId,
                'technicalName' => 'ps_apiresources',
                'version' => $version,
                'enabled' => true,
            ],
            $moduleInfos
        );

        return $moduleInfos['technicalName'];
    }

    /**
     * @depends testListModules
     * @depends testGetModuleInfos
     */
    public function testBulkUpdateStatus(int $moduleId, string $technicalName): void
    {
        // Check number of disabled modules
        $disabledModules = $this->listItems('/modules', ['module_read'], ['enabled' => false]);
        $this->assertEquals(0, $disabledModules['totalItems']);

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
        // Active status is cached so we must clear it before calling the single endpoint
        \Module::resetStaticCache();

        // Check updated disabled status
        $moduleInfos = $this->getModuleInfos($moduleId);
        $this->assertFalse($moduleInfos['enabled']);

        // Check number of disabled modules
        $disabledModules = $this->listItems('/modules', ['module_read'], ['enabled' => false]);
        $this->assertEquals(1, $disabledModules['totalItems']);

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
        // Active status is cached so we must clear it before calling the single endpoint
        \Module::resetStaticCache();

        // Check updated enabled status
        $moduleInfos = $this->getModuleInfos($moduleId);
        $this->assertTrue($moduleInfos['enabled']);

        // Check number of disabled modules
        $disabledModules = $this->listItems('/modules', ['module_read'], ['enabled' => false]);
        $this->assertEquals(0, $disabledModules['totalItems']);
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
