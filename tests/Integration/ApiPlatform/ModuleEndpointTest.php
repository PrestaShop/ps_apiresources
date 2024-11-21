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
use PrestaShop\PrestaShop\Core\Module\ModuleRepository;
use Tests\Resources\DatabaseDump;
use Tests\Resources\ResourceResetter;

class ModuleEndpointTest extends ApiTestCase
{
    public static function setUpBeforeClass(): void
    {
        // We also need to restore role tables related to mule installation, so it's safer to restore everything
        // We must do it before the parent::setUpBeforeClass or the necessary configuration will be erased
        DatabaseDump::restoreAllTables();
        parent::setUpBeforeClass();
        self::createApiClient(['module_write', 'module_read']);

        /** @var ModuleRepository $moduleRepository */
        $moduleRepository = self::getContainer()->get(ModuleRepository::class);
        // CLear cache as it is persisted in file cache and may be outdated because tests have failed in previous process
        $moduleRepository->clearCache();
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        // We also need to restore role tables related to mule installation, so it's safer to restore everything
        DatabaseDump::restoreAllTables();
        (new ResourceResetter())->resetTestModules();
    }

    public function getProtectedEndpoints(): iterable
    {
        yield 'get endpoint' => [
            'GET',
            '/module/ps_featureproducts',
        ];

        yield 'list modules' => [
            'GET',
            '/modules',
        ];

        yield 'bulk toggle status' => [
            'PUT',
            '/modules/toggle-status',
        ];

        yield 'toggle module status' => [
            'PUT',
            '/module/{technicalName}/status',
        ];

        yield 'reset module' => [
            'PATCH',
            '/module/{technicalName}/reset',
        ];

        yield 'upload module by source' => [
            'POST',
            '/module/upload-source',
        ];

        yield 'upload module by archive' => [
            'POST',
            '/module/upload-archive',
            'multipart/form-data',
        ];

        yield 'upgrade' => [
            'PUT',
            '/module/{technicalName}/upgrade',
        ];

        yield 'uninstall module' => [
            'PUT',
            '/module/{technicalName}/uninstall',
        ];

        yield 'bulk uninstall' => [
            'PUT',
            '/modules/uninstall',
        ];
    }

    public function testModuleNotFound(): void
    {
        $bearerToken = $this->getBearerToken(['module_read', 'module_write']);
        // GET on non existent module returns a 404
        static::createClient()->request('GET', '/module/ps_falsemodule', [
            'auth_bearer' => $bearerToken,
        ]);
        self::assertResponseStatusCodeSame(404);

        // PUT status on non existent module returns a 404
        static::createClient()->request('PUT', '/module/ps_falsemodule/status', [
            'auth_bearer' => $bearerToken,
            'json' => [
                'enabled' => true,
            ],
        ]);
        self::assertResponseStatusCodeSame(404);

        // PUT bulk status on non existent module returns a 404
        static::createClient()->request('PUT', '/modules/toggle-status', [
            'auth_bearer' => $bearerToken,
            'json' => [
                'modules' => ['ps_falsemodule'],
                'enabled' => true,
            ],
        ]);
        self::assertResponseStatusCodeSame(404);

        // PATCH reset on non existent module returns a 404
        static::createClient()->request('PATCH', '/module/ps_falsemodule/reset', [
            'auth_bearer' => $bearerToken,
            'json' => [
                'keepData' => true,
            ],
        ]);
        self::assertResponseStatusCodeSame(404);

        // PUT install on non existent module returns a 404
        static::createClient()->request('PUT', '/module/ps_falsemodule/install', [
            'auth_bearer' => $bearerToken,
            'json' => [
            ],
        ]);
        self::assertResponseStatusCodeSame(404);

        // PUT uninstall on non existent module returns a 404
        static::createClient()->request('PUT', '/module/ps_falsemodule/uninstall', [
            'auth_bearer' => $bearerToken,
            'json' => [
            ],
        ]);
        self::assertResponseStatusCodeSame(404);
    }

    public function testListModules(): array
    {
        $modules = $this->listItems('/modules', ['module_read']);
        $this->assertGreaterThan(1, $modules['totalItems']);

        $modules = $this->listItems('/modules', ['module_read'], ['technicalName' => 'ps_apiresources']);
        $this->assertEquals(1, $modules['totalItems']);
        $apiModule = $modules['items'][0];
        $this->assertEquals('ps_apiresources', $apiModule['technicalName']);
        $this->assertTrue($apiModule['enabled']);
        $this->assertTrue(version_compare($apiModule['moduleVersion'], '0.1.0', '>='));
        $this->assertTrue(version_compare($apiModule['installedVersion'], '0.1.0', '>='));
        $this->assertGreaterThan(0, $apiModule['moduleId']);

        return $apiModule;
    }

    /**
     * @depends testListModules
     */
    public function testGetModuleInfos(array $module): array
    {
        $moduleInfos = $this->getModuleInfos($module['technicalName']);
        $this->assertEquals(
            [
                'moduleId' => $module['moduleId'],
                'technicalName' => $module['technicalName'],
                'moduleVersion' => $module['moduleVersion'],
                'installedVersion' => $module['installedVersion'],
                'enabled' => true,
                'installed' => true,
            ],
            $moduleInfos
        );

        return $module;
    }

    /**
     * @depends testGetModuleInfos
     */
    public function testBulkUpdateStatus(array $module): array
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
                    $module['technicalName'],
                ],
                'enabled' => false,
            ],
        ]);
        self::assertResponseStatusCodeSame(204);

        // Check updated disabled status
        $moduleInfos = $this->getModuleInfos($module['technicalName']);
        $this->assertFalse($moduleInfos['enabled']);

        // Check number of disabled modules
        $disabledModules = $this->listItems('/modules', ['module_read'], ['enabled' => false]);
        $this->assertEquals(1, $disabledModules['totalItems']);

        // Bulk enable on one module
        static::createClient()->request('PUT', '/modules/toggle-status', [
            'auth_bearer' => $bearerToken,
            'json' => [
                'modules' => [
                    $module['technicalName'],
                ],
                'enabled' => true,
            ],
        ]);
        self::assertResponseStatusCodeSame(204);

        // Check updated enabled status
        $moduleInfos = $this->getModuleInfos($module['technicalName']);
        $this->assertTrue($moduleInfos['enabled']);

        // Check number of disabled modules
        $disabledModules = $this->listItems('/modules', ['module_read'], ['enabled' => false]);
        $this->assertEquals(0, $disabledModules['totalItems']);

        return $module;
    }

    /**
     * @depends testBulkUpdateStatus
     */
    public function testUpdateModuleStatus(array $module): array
    {
        // Check number of disabled modules
        $disabledModules = $this->listItems('/modules', ['module_read'], ['enabled' => false]);
        $this->assertEquals(0, $disabledModules['totalItems']);

        // Disable specific module
        $bearerToken = $this->getBearerToken(['module_read', 'module_write']);
        $response = static::createClient()->request('PUT', sprintf('/module/%s/status', $module['technicalName']), [
            'auth_bearer' => $bearerToken,
            'json' => [
                'enabled' => false,
            ],
        ]);
        self::assertResponseStatusCodeSame(200);
        $decodedResponse = json_decode($response->getContent(), true);
        $this->assertNotFalse($decodedResponse);

        // Check response from status update request
        $expectedModuleInfos = [
            'moduleId' => $module['moduleId'],
            'technicalName' => $module['technicalName'],
            'moduleVersion' => $module['moduleVersion'],
            'installedVersion' => $module['installedVersion'],
            'enabled' => false,
            'installed' => true,
        ];
        $this->assertEquals($expectedModuleInfos, $decodedResponse);

        // Check updated disabled status
        $moduleInfos = $this->getModuleInfos($module['technicalName']);
        $this->assertEquals($expectedModuleInfos, $moduleInfos);

        // Check number of disabled modules
        $disabledModules = $this->listItems('/modules', ['module_read'], ['enabled' => false]);
        $this->assertEquals(1, $disabledModules['totalItems']);

        // Enable specific module
        $bearerToken = $this->getBearerToken(['module_read', 'module_write']);
        $response = static::createClient()->request('PUT', sprintf('/module/%s/status', $module['technicalName']), [
            'auth_bearer' => $bearerToken,
            'json' => [
                'enabled' => true,
            ],
        ]);
        self::assertResponseStatusCodeSame(200);
        $decodedResponse = json_decode($response->getContent(), true);
        $this->assertNotFalse($decodedResponse);

        // Check response from status update request
        $expectedModuleInfos['enabled'] = true;
        $this->assertEquals($expectedModuleInfos, $decodedResponse);

        // Check updated enabled status
        $moduleInfos = $this->getModuleInfos($module['technicalName']);
        $this->assertTrue($moduleInfos['enabled']);

        // Check updated enabled status
        $moduleInfos = $this->getModuleInfos($module['technicalName']);
        $this->assertEquals($expectedModuleInfos, $moduleInfos);

        // Check number of disabled modules
        $disabledModules = $this->listItems('/modules', ['module_read'], ['enabled' => false]);
        $this->assertEquals(0, $disabledModules['totalItems']);

        return $module;
    }

    /**
     * @depends testUpdateModuleStatus
     */
    public function testResetModule(array $module): array
    {
        // Reset specific module
        $bearerToken = $this->getBearerToken(['module_read', 'module_write']);
        $response = static::createClient()->request('PATCH', sprintf('/module/%s/reset', $module['technicalName']), [
            'auth_bearer' => $bearerToken,
            'json' => [
                'keepData' => false,
            ],
        ]);
        self::assertResponseStatusCodeSame(200);
        $decodedResponse = json_decode($response->getContent(), true);
        $this->assertNotFalse($decodedResponse);

        // Module ID has been modified because the module was uninstalled the reinstalled
        $this->assertNotEquals($module['moduleId'], $decodedResponse['moduleId']);
        $moduleInfos = $this->getModuleInfos($module['technicalName']);
        $this->assertNotEquals($module['moduleId'], $moduleInfos['moduleId']);
        $this->assertEquals($moduleInfos['moduleId'], $decodedResponse['moduleId']);
        $module['moduleId'] = $decodedResponse['moduleId'];

        // Check response from status update request
        $expectedModuleInfos = [
            'moduleId' => $module['moduleId'],
            'technicalName' => $module['technicalName'],
            'moduleVersion' => $module['moduleVersion'],
            'installedVersion' => $module['installedVersion'],
            'enabled' => true,
            'installed' => true,
        ];
        $this->assertEquals($expectedModuleInfos, $decodedResponse);

        return $module;
    }

    /**
     * @depends testResetModule
     */
    public function testResetModuleNotActive(array $module): array
    {
        // Disable specific module
        $bearerToken = $this->getBearerToken(['module_read', 'module_write']);
        static::createClient()->request('PUT', sprintf('/module/%s/status', $module['technicalName']), [
            'auth_bearer' => $bearerToken,
            'json' => [
                'enabled' => false,
            ],
        ]);
        self::assertResponseStatusCodeSame(200);

        $expectedModuleInfos = [
            'moduleId' => $module['moduleId'],
            'technicalName' => $module['technicalName'],
            'moduleVersion' => $module['moduleVersion'],
            'installedVersion' => $module['installedVersion'],
            'enabled' => false,
            'installed' => true,
        ];
        $this->assertEquals($expectedModuleInfos, $this->getModuleInfos($module['technicalName']));

        // Now try to reset a disabled module it should be reset and enabled
        $bearerToken = $this->getBearerToken(['module_read', 'module_write']);
        $response = static::createClient()->request('PATCH', sprintf('/module/%s/reset', $module['technicalName']), [
            'auth_bearer' => $bearerToken,
            'json' => [
                'keepData' => false,
            ],
        ]);
        self::assertResponseStatusCodeSame(200);
        $decodedResponse = json_decode($response->getContent(), true);

        // Module ID has been modified because the module was uninstalled the reinstalled
        $this->assertNotEquals($module['moduleId'], $decodedResponse['moduleId']);
        $moduleInfos = $this->getModuleInfos($module['technicalName']);
        $this->assertNotEquals($module['moduleId'], $moduleInfos['moduleId']);
        $this->assertEquals($moduleInfos['moduleId'], $decodedResponse['moduleId']);
        $module['moduleId'] = $decodedResponse['moduleId'];

        // Module is now expected to be enabled and its ID has been updated
        $expectedModuleInfos['enabled'] = true;
        $expectedModuleInfos['moduleId'] = $module['moduleId'];
        $this->assertEquals($expectedModuleInfos, $decodedResponse);
        $this->assertEquals($expectedModuleInfos, $this->getModuleInfos($module['technicalName']));

        return $module;
    }

    /**
     * @depends testResetModuleNotActive
     */
    public function testUploadModuleFromSource(): void
    {
        $this->assertModuleNotFound('dashactivity');
        $expectedModule = [
            'moduleId' => null,
            'technicalName' => 'dashactivity',
            'moduleVersion' => '2.1.0',
            // Module is simply uploaded not installed
            'installedVersion' => null,
            'enabled' => false,
            'installed' => false,
        ];

        $bearerToken = $this->getBearerToken(['module_write']);
        $response = static::createClient()->request('POST', '/module/upload-source', [
            'auth_bearer' => $bearerToken,
            'json' => [
                'source' => 'https://github.com/PrestaShop/dashactivity/releases/download/v2.1.0/dashactivity.zip',
            ],
        ]);

        self::assertResponseStatusCodeSame(201);
        $decodedResponse = json_decode($response->getContent(), true);
        $this->assertNotFalse($decodedResponse);
        // Check response from status update request
        $this->assertEquals($expectedModule, $decodedResponse);

        // Check result from GET API
        $this->assertEquals($expectedModule, $this->getModuleInfos($expectedModule['technicalName']));
    }

    /**
     * @depends testUploadModuleFromSource
     */
    public function testInstallModule(): void
    {
        $expectedModule = [
            'technicalName' => 'dashactivity',
            'moduleVersion' => '2.1.0',
            'installedVersion' => '2.1.0',
            'enabled' => true,
            'installed' => true,
        ];

        $bearerToken = $this->getBearerToken(['module_write']);
        $response = static::createClient()->request('PUT', sprintf('/module/%s/install', $expectedModule['technicalName']), [
            'auth_bearer' => $bearerToken,
            // We must define a JSON body even if it is empty, we need to search how to make this optional
            'json' => [
            ],
        ]);

        self::assertResponseStatusCodeSame(200);
        $decodedResponse = json_decode($response->getContent(), true);
        $this->assertNotFalse($decodedResponse);
        // The ID is dynamic so we fetch it after creation
        $this->assertArrayHasKey('moduleId', $decodedResponse);
        $expectedModule['moduleId'] = $decodedResponse['moduleId'];

        // Check response from install request
        $this->assertEquals($expectedModule, $decodedResponse);

        // Check result from GET API
        $this->assertEquals($expectedModule, $this->getModuleInfos($expectedModule['technicalName']));
    }

    /**
     * @depends testInstallModule
     */
    public function testInstallModuleAlreadyInstalled(): void
    {
        $bearerToken = $this->getBearerToken(['module_write']);
        $response = static::createClient()->request('PUT', '/module/dashactivity/install', [
            'auth_bearer' => $bearerToken,
            // We must define a JSON body even if it is empty, we need to search how to make this optional
            'json' => [
            ],
        ]);

        self::assertResponseStatusCodeSame(403);
        $decodedResponse = json_decode($response->getContent(false), true);
        $this->assertEquals('Cannot install module dashactivity since it is already installed', $decodedResponse['detail']);
    }

    /**
     * @depends testInstallModuleAlreadyInstalled
     */
    public function testUninstallModuleWithFilesKept()
    {
        $expectedModule = [
            'moduleId' => null,
            'technicalName' => 'dashactivity',
            'moduleVersion' => '2.1.0',
            'installedVersion' => null,
            'enabled' => false,
            'installed' => false,
        ];

        // Uninstall specific module deleteFiles false
        $bearerToken = $this->getBearerToken(['module_write']);
        static::createClient()->request('PUT', sprintf('/module/%s/uninstall', $expectedModule['technicalName']), [
            'auth_bearer' => $bearerToken,
            'json' => [
                // We keep files, so we can check the module status afterward (deleted module would return a 404)
                'deleteFiles' => false,
            ],
        ]);
        self::assertResponseStatusCodeSame(204);

        // Check result from GET API
        $this->assertEquals($expectedModule, $this->getModuleInfos($expectedModule['technicalName']));
    }

    /**
     * @depends testUninstallModuleWithFilesKept
     */
    public function testUninstallModuleNotInstalled(): void
    {
        $bearerToken = $this->getBearerToken(['module_read', 'module_write']);
        $response = static::createClient()->request('PUT', '/module/dashactivity/uninstall', [
            'auth_bearer' => $bearerToken,
            'json' => [
                // We keep files, so we can check the module status afterward (deleted module would return a 404)
                'deleteFiles' => false,
            ],
        ]);
        self::assertResponseStatusCodeSame(403);
        $decodedResponse = json_decode($response->getContent(false), true);
        $this->assertEquals('Cannot uninstall module dashactivity since it is not installed', $decodedResponse['detail']);
    }

    /**
     * @depends testUninstallModuleNotInstalled
     */
    public function testResetModuleNotInstalled(): void
    {
        // Now try to reset a module not installed it should be forbidden
        $bearerToken = $this->getBearerToken(['module_read', 'module_write']);
        $response = static::createClient()->request('PATCH', '/module/dashactivity/reset', [
            'auth_bearer' => $bearerToken,
            'json' => [
            ],
        ]);
        self::assertResponseStatusCodeSame(403);
        $decodedResponse = json_decode($response->getContent(false), true);
        $this->assertEquals('Cannot reset module dashactivity since it is not installed', $decodedResponse['detail']);
    }

    /**
     * @depends testResetModuleNotInstalled
     */
    public function testUploadModuleByArchive()
    {
        $bearerToken = $this->getBearerToken(['module_write']);
        $uploadedArchive = $this->prepareUploadedFile(__DIR__ . '/../../Resources/assets/archive/test_install_cqrs_command.zip');

        $response = static::createClient()->request('POST', '/module/upload-archive', [
            'auth_bearer' => $bearerToken,
            'headers' => [
                'content-type' => 'multipart/form-data',
            ],
            'extra' => [
                'files' => [
                    'archive' => $uploadedArchive,
                ],
            ],
        ]);
        self::assertResponseStatusCodeSame(201);
        $decodedResponse = json_decode($response->getContent(), true);
        $expectedModule = [
            'moduleId' => null,
            'technicalName' => 'test_install_cqrs_command',
            'moduleVersion' => '1.0.0',
            'installedVersion' => null,
            'enabled' => false,
            'installed' => false,
        ];

        // The returned response contains the module details
        $this->assertEquals($expectedModule, $decodedResponse);
        // The module GET endpoint returns the same data
        $this->assertEquals($expectedModule, $this->getModuleInfos($expectedModule['technicalName']));
    }

    /**
     * @depends testUploadModuleByArchive
     */
    public function testUninstallModuleAndRemoveFiles(): void
    {
        // Uninstall specific module deleteFiles true
        $bearerToken = $this->getBearerToken(['module_write']);
        static::createClient()->request('PUT', '/module/test_install_cqrs_command/uninstall', [
            'auth_bearer' => $bearerToken,
            'json' => [
                // We remove files, so the module no longer exists
                'deleteFiles' => true,
            ],
        ]);
        self::assertResponseStatusCodeSame(204);

        // Check that the module no longer exists
        $this->assertModuleNotFound('test_install_cqrs_command');
    }

    /**
     * @depends testUninstallModuleAndRemoveFiles
     */
    public function testBulkUninstallModule()
    {
        $modules = ['bankwire', 'ps_emailsubscription'];
        foreach ($modules as $module) {
            $moduleInfos = $this->getModuleInfos($module);
            $this->assertGreaterThan(0, $moduleInfos['moduleId']);
            $this->assertTrue($moduleInfos['enabled']);
            $this->assertTrue($moduleInfos['installed']);
            $this->assertTrue(version_compare($moduleInfos['moduleVersion'], '0.1.0', '>='));
            $this->assertTrue(version_compare($moduleInfos['installedVersion'], '0.1.0', '>='));
        }

        // uninstall specific module deleteFiles true
        $bearerToken = $this->getBearerToken(['module_write']);
        static::createClient()->request('PUT', sprintf('/modules/uninstall'), [
            'auth_bearer' => $bearerToken,
            'json' => [
                'modules' => $modules,
                // Force removal of the files
                'deleteFiles' => true,
            ],
        ]);
        self::assertResponseStatusCodeSame(204);

        // Module files have been removed, so they don't exist at all anymore, thus requesting their info results in a 404
        foreach ($modules as $module) {
            $this->assertModuleNotFound($module);
        }
    }

    public function testUpgradeModule(): void
    {
        $bearerToken = $this->getBearerToken(['module_write']);

        // Upload Zip from GitHub with version 2.1.2 the module is present but not installed
        $response = static::createClient()->request('POST', '/module/upload-source', [
            'auth_bearer' => $bearerToken,
            'json' => [
                'source' => 'https://github.com/PrestaShop/dashproducts/releases/download/v2.1.2/dashproducts.zip',
            ],
        ]);
        self::assertResponseStatusCodeSame(201);
        $decodedResponse = json_decode($response->getContent(), true);
        $this->assertNotFalse($decodedResponse);

        // The returned response and the GET infos response should be identical
        $module212 = [
            'moduleId' => null,
            'technicalName' => 'dashproducts',
            'moduleVersion' => '2.1.2',
            // Module is simply uploaded not installed
            'installedVersion' => null,
            'enabled' => false,
            'installed' => false,
        ];
        $this->assertEquals($module212, $decodedResponse);
        $this->assertEquals($module212, $this->getModuleInfos($module212['technicalName']));

        // Now we install the module
        $response = static::createClient()->request('PUT', sprintf('/module/%s/install', $module212['technicalName']), [
            'auth_bearer' => $bearerToken,
            // We must define a JSON body even if it is empty, we need to search how to make this optional
            'json' => [
            ],
        ]);
        self::assertResponseStatusCodeSame(200);
        $decodedResponse = json_decode($response->getContent(), true);
        $this->assertNotFalse($decodedResponse);

        // The ID is dynamic, so we fetch it after creation
        $this->assertArrayHasKey('moduleId', $decodedResponse);
        $module212['moduleId'] = $decodedResponse['moduleId'];
        $module212['installed'] = true;
        $module212['enabled'] = true;
        $module212['installedVersion'] = '2.1.2';
        $this->assertEquals($module212, $decodedResponse);
        $this->assertEquals($module212, $this->getModuleInfos($module212['technicalName']));

        // Now upload the source for version 2.1.3, the module version is updated but not the installed one
        $response = static::createClient()->request('POST', '/module/upload-source', [
            'auth_bearer' => $bearerToken,
            'json' => [
                'source' => 'https://github.com/PrestaShop/dashproducts/releases/download/v2.1.3/dashproducts.zip',
            ],
        ]);
        self::assertResponseStatusCodeSame(201);
        $decodedResponse = json_decode($response->getContent(), true);
        $this->assertNotFalse($decodedResponse);

        $module213 = [
            'moduleId' => $module212['moduleId'],
            'technicalName' => 'dashproducts',
            'moduleVersion' => '2.1.3',
            // Module is simply uploaded not installed
            'installedVersion' => '2.1.2',
            'enabled' => true,
            'installed' => true,
        ];
        $this->assertEquals($module213, $decodedResponse);
        $this->assertEquals($module213, $this->getModuleInfos($module213['technicalName']));

        $response = static::createClient()->request('PUT', sprintf('/module/%s/upgrade', $module212['technicalName']), [
            'auth_bearer' => $bearerToken,
            // We must define a JSON body even if it is empty, we need to search how to make this optional
            'json' => [
            ],
        ]);
        self::assertResponseStatusCodeSame(200);
        $decodedResponse = json_decode($response->getContent(), true);
        $this->assertNotFalse($decodedResponse);

        // Check response from status upgrade request
        $module213['installedVersion'] = '2.1.3';
        $this->assertEquals($module213, $decodedResponse);
        $this->assertEquals($module213, $this->getModuleInfos($module213['technicalName']));
    }

    private function getModuleInfos(string $technicalName): array
    {
        $bearerToken = $this->getBearerToken(['module_read']);
        $response = static::createClient()->request('GET', '/module/' . $technicalName, [
            'auth_bearer' => $bearerToken,
        ]);
        self::assertResponseStatusCodeSame(200);

        $decodedResponse = json_decode($response->getContent(), true);
        $this->assertNotFalse($decodedResponse);

        return $decodedResponse;
    }

    private function assertModuleNotFound(string $technicalName): void
    {
        $bearerToken = $this->getBearerToken(['module_read']);
        static::createClient()->request('GET', '/module/' . $technicalName, [
            'auth_bearer' => $bearerToken,
        ]);
        self::assertResponseStatusCodeSame(404);
    }
}
