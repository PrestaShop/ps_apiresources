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
use Symfony\Component\HttpFoundation\Response;
use Tests\Resources\DatabaseDump;
use Tests\Resources\ResourceResetter;

class ModuleEndpointTest extends ApiTestCase
{
    public static function setUpBeforeClass(): void
    {
        // We also need to restore role tables related to module installation, so it's safer to restore everything
        // We must do it before the parent::setUpBeforeClass or the necessary configuration will be erased
        DatabaseDump::restoreAllTables();
        parent::setUpBeforeClass();
        // Pre-create the API Client with the needed scopes, this way we reduce the number of created API Clients
        self::createApiClient(['module_write', 'module_read']);

        /** @var ModuleRepository $moduleRepository */
        $moduleRepository = self::getContainer()->get(ModuleRepository::class);
        // CLear cache as it is persisted in file cache and may be outdated because tests have failed in previous process
        $moduleRepository->clearCache();
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        // We also need to restore role tables related to module installation, so it's safer to restore everything
        DatabaseDump::restoreAllTables();
        (new ResourceResetter())->resetTestModules();
    }

    public static function getProtectedEndpoints(): iterable
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
        // GET on non existent module returns a 404
        $this->getItem('/module/ps_falsemodule', ['module_read'], Response::HTTP_NOT_FOUND);

        // PUT status on non existent module returns a 404
        $this->updateItem('/module/ps_falsemodule/status', [
            'enabled' => true,
        ], ['module_write'], Response::HTTP_NOT_FOUND);

        // PUT bulk status on non existent module returns a 404
        $this->updateItem('/modules/toggle-status', [
            'modules' => ['ps_falsemodule'],
            'enabled' => true,
        ], ['module_write'], Response::HTTP_NOT_FOUND);

        // PATCH reset on non existent module returns a 404
        $this->partialUpdateItem('/module/ps_falsemodule/reset', [
            'keepData' => true,
        ], ['module_write'], Response::HTTP_NOT_FOUND);

        // PUT install on non existent module returns a 404
        $this->updateItem('/module/ps_falsemodule/install', null, ['module_write'], Response::HTTP_NOT_FOUND);

        // PUT uninstall on non existent module returns a 404
        $this->updateItem('/module/ps_falsemodule/uninstall', null, ['module_write'], Response::HTTP_NOT_FOUND);
    }

    public function testListModules(): array
    {
        $modules = $this->listItems('/modules', ['module_read']);
        $this->assertGreaterThan(1, $modules['totalItems']);

        $modules = $this->listItems('/modules', ['module_read'], ['technicalName' => 'ps_apiresources']);
        $this->assertEquals(1, $modules['totalItems']);
        $apiModule = $modules['items'][0];

        // These two fields are likely to move so we check it's at least 0.2.0
        $this->assertTrue(version_compare($apiModule['moduleVersion'], '0.2.0', '>='));
        $this->assertTrue(version_compare($apiModule['installedVersion'], '0.2.0', '>='));
        $this->assertGreaterThan(0, $apiModule['moduleId']);

        $this->assertEquals([
            'moduleId' => $apiModule['moduleId'],
            'technicalName' => 'ps_apiresources',
            'enabled' => true,
            'moduleVersion' => $apiModule['moduleVersion'],
            'installedVersion' => $apiModule['installedVersion'],
        ], $apiModule);

        return $apiModule;
    }

    /**
     * @depends testListModules
     */
    public function testGetModuleInfos(array $module): array
    {
        $moduleInfos = $this->getItem('/module/' . $module['technicalName'], ['module_read']);
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
        $this->updateItem('/modules/toggle-status', [
            'modules' => [
                $module['technicalName'],
            ],
            'enabled' => false,
        ], ['module_write'], Response::HTTP_NO_CONTENT);

        // Check updated disabled status
        $moduleInfos = $this->getItem('/module/' . $module['technicalName'], ['module_read']);
        $this->assertFalse($moduleInfos['enabled']);

        // Check number of disabled modules
        $disabledModules = $this->listItems('/modules', ['module_read'], ['enabled' => false]);
        $this->assertEquals(1, $disabledModules['totalItems']);

        // Bulk enable on one module
        $this->updateItem('/modules/toggle-status', [
            'modules' => [
                $module['technicalName'],
            ],
            'enabled' => true,
        ], ['module_write'], Response::HTTP_NO_CONTENT);

        // Check updated enabled status
        $moduleInfos = $this->getItem('/module/' . $module['technicalName'], ['module_read']);
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
        $updatedModule = $this->updateItem(sprintf('/module/%s/status', $module['technicalName']), [
            'enabled' => false,
        ], ['module_write']);

        // Check response from status update request
        $expectedModuleInfos = [
            'moduleId' => $module['moduleId'],
            'technicalName' => $module['technicalName'],
            'moduleVersion' => $module['moduleVersion'],
            'installedVersion' => $module['installedVersion'],
            'enabled' => false,
            'installed' => true,
        ];
        $this->assertEquals($expectedModuleInfos, $updatedModule);

        // Check updated disabled status
        $moduleInfos = $this->getItem('/module/' . $module['technicalName'], ['module_read']);
        $this->assertEquals($expectedModuleInfos, $moduleInfos);

        // Check number of disabled modules
        $disabledModules = $this->listItems('/modules', ['module_read'], ['enabled' => false]);
        $this->assertEquals(1, $disabledModules['totalItems']);

        // Enable specific module
        $updatedModule = $this->updateItem(sprintf('/module/%s/status', $module['technicalName']), [
            'enabled' => true,
        ], ['module_write']);
        // Check response from status update request
        $expectedModuleInfos['enabled'] = true;
        $this->assertEquals($expectedModuleInfos, $updatedModule);

        // Check updated enabled status
        $moduleInfos = $this->getItem('/module/' . $module['technicalName'], ['module_read']);
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
        $updatedModule = $this->partialUpdateItem(sprintf('/module/%s/reset', $module['technicalName']), [
            'keepData' => false,
        ], ['module_write']);

        // Module ID has been modified because the module was uninstalled the reinstalled
        $this->assertNotEquals($module['moduleId'], $updatedModule['moduleId']);

        // Check updated module via GET endpoint
        $moduleInfos = $this->getItem('/module/' . $module['technicalName'], ['module_read']);
        // Initial ID has been modified
        $this->assertNotEquals($module['moduleId'], $moduleInfos['moduleId']);
        // New ID is in sync between the returned data from rest endpoint end the new data fetch via GET
        $this->assertEquals($moduleInfos['moduleId'], $updatedModule['moduleId']);
        $module['moduleId'] = $updatedModule['moduleId'];

        // Check response from status update request
        $expectedModuleInfos = [
            'moduleId' => $module['moduleId'],
            'technicalName' => $module['technicalName'],
            'moduleVersion' => $module['moduleVersion'],
            'installedVersion' => $module['installedVersion'],
            'enabled' => true,
            'installed' => true,
        ];
        $this->assertEquals($expectedModuleInfos, $updatedModule);

        return $module;
    }

    /**
     * @depends testResetModule
     */
    public function testResetModuleNotActive(array $module): array
    {
        // Disable specific module
        $updatedModule = $this->updateItem(sprintf('/module/%s/status', $module['technicalName']), [
            'enabled' => false,
        ], ['module_write']);

        $expectedModuleInfos = [
            'moduleId' => $module['moduleId'],
            'technicalName' => $module['technicalName'],
            'moduleVersion' => $module['moduleVersion'],
            'installedVersion' => $module['installedVersion'],
            'enabled' => false,
            'installed' => true,
        ];
        $this->assertEquals($expectedModuleInfos, $updatedModule);
        $this->assertEquals($expectedModuleInfos, $this->getItem('/module/' . $module['technicalName'], ['module_read']));

        // Now try to reset a disabled module it should be reset and enabled
        $resetModule = $this->partialUpdateItem(sprintf('/module/%s/reset', $module['technicalName']), [
            'keepData' => false,
        ], ['module_write']);

        // Module ID has been modified because the module was uninstalled the reinstalled
        $this->assertNotEquals($module['moduleId'], $resetModule['moduleId']);
        $moduleInfos = $this->getItem('/module/' . $module['technicalName'], ['module_read']);
        $this->assertNotEquals($module['moduleId'], $moduleInfos['moduleId']);
        $this->assertEquals($moduleInfos['moduleId'], $resetModule['moduleId']);
        $module['moduleId'] = $resetModule['moduleId'];

        // Module is now expected to be enabled and its ID has been updated
        $expectedModuleInfos['enabled'] = true;
        $expectedModuleInfos['moduleId'] = $module['moduleId'];
        $this->assertEquals($expectedModuleInfos, $resetModule);
        $this->assertEquals($expectedModuleInfos, $this->getItem('/module/' . $module['technicalName'], ['module_read']));

        return $module;
    }

    /**
     * @depends testResetModuleNotActive
     */
    public function testUploadModuleFromSource(): void
    {
        // Assert module dashactivity is not found and returns a 404
        $this->getItem('/module/dashactivity', ['module_read'], Response::HTTP_NOT_FOUND);

        // Now upload the module via a zip URL
        $uploadedModule = $this->createItem('/module/upload-source', [
            'source' => 'https://github.com/PrestaShop/dashactivity/releases/download/v2.1.0/dashactivity.zip',
        ], ['module_write']);

        // Check response from status update request
        $expectedModule = [
            // Module is not installed, only uploaded so its ID is null
            'moduleId' => null,
            'technicalName' => 'dashactivity',
            'moduleVersion' => '2.1.0',
            // Module is simply uploaded not installed
            'installedVersion' => null,
            'enabled' => false,
            'installed' => false,
        ];
        $this->assertEquals($expectedModule, $uploadedModule);

        // Check result from GET API
        $this->assertEquals($expectedModule, $this->getItem('/module/dashactivity', ['module_read']));
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

        $installedModule = $this->updateItem(sprintf('/module/%s/install', $expectedModule['technicalName']), null, ['module_write']);

        // The ID is dynamic so we fetch it after creation
        $this->assertArrayHasKey('moduleId', $installedModule);
        $expectedModule['moduleId'] = $installedModule['moduleId'];

        // Check response from install request
        $this->assertEquals($expectedModule, $installedModule);

        // Check result from GET API
        $this->assertEquals($expectedModule, $this->getItem('/module/' . $expectedModule['technicalName'], ['module_read']));
    }

    /**
     * @depends testInstallModule
     */
    public function testInstallModuleAlreadyInstalled(): void
    {
        // Installing a module already installed is forbidden
        $errorResponse = $this->updateItem('/module/dashactivity/install', [], ['module_write'], Response::HTTP_FORBIDDEN);
        $this->assertEquals('Cannot install module dashactivity since it is already installed', $errorResponse['detail']);
    }

    /**
     * @depends testInstallModuleAlreadyInstalled
     */
    public function testUninstallModuleWithFilesKept()
    {
        $expectedModule = [
            // Module is uninstalled so no more ID
            'moduleId' => null,
            'technicalName' => 'dashactivity',
            // Module is uninstalled but files were kept so we still know the version of the module on the disk
            'moduleVersion' => '2.1.0',
            // Module is uninstalled so installed version is null
            'installedVersion' => null,
            'enabled' => false,
            'installed' => false,
        ];

        // Uninstall specific module deleteFiles false
        $this->updateItem(sprintf('/module/%s/uninstall', $expectedModule['technicalName']), [
            // We keep files, so we can check the module status afterward (deleted module would return a 404)
            'deleteFiles' => false,
        ], ['module_write'], Response::HTTP_NO_CONTENT);

        // Check result from GET API (the module is uninstalled but its files were kept so we can still provide some minimum infos)
        $this->assertEquals($expectedModule, $this->getItem('/module/' . $expectedModule['technicalName'], ['module_read']));
    }

    /**
     * @depends testUninstallModuleWithFilesKept
     */
    public function testUninstallModuleNotInstalled(): void
    {
        // Uninstalling a module already installed is forbidden
        $errorResponse = $this->updateItem('/module/dashactivity/uninstall', [
            // We keep files, so we can check the module status afterward (deleted module would return a 404)
            'deleteFiles' => false,
        ], ['module_write'], Response::HTTP_FORBIDDEN);
        $this->assertEquals('Cannot uninstall module dashactivity since it is not installed', $errorResponse['detail']);
    }

    /**
     * @depends testUninstallModuleNotInstalled
     */
    public function testResetModuleNotInstalled(): void
    {
        // Now try to reset a module not installed it should be forbidden
        $errorResponse = $this->partialUpdateItem('/module/dashactivity/reset', null, ['module_write'], Response::HTTP_FORBIDDEN);
        $this->assertEquals('Cannot reset module dashactivity since it is not installed', $errorResponse['detail']);
    }

    /**
     * @depends testResetModuleNotInstalled
     */
    public function testUploadModuleByArchive()
    {
        $uploadedArchive = $this->prepareUploadedFile(__DIR__ . '/../../Resources/assets/archive/test_install_cqrs_command.zip');
        $uploadedModule = $this->requestApi('POST', '/module/upload-archive', null, ['module_write'], Response::HTTP_CREATED, [
            'headers' => [
                'content-type' => 'multipart/form-data',
            ],
            'extra' => [
                'files' => [
                    'archive' => $uploadedArchive,
                ],
            ],
        ]);

        $expectedModule = [
            // Module uploaded but not installed so no moduleId
            'moduleId' => null,
            'technicalName' => 'test_install_cqrs_command',
            'moduleVersion' => '1.0.0',
            // Module uploaded but not installed so no version in DB
            'installedVersion' => null,
            'enabled' => false,
            'installed' => false,
        ];

        // The returned response contains the module details
        $this->assertEquals($expectedModule, $uploadedModule);
        // The module GET endpoint returns the same data
        $this->assertEquals($expectedModule, $this->getItem('/module/' . $expectedModule['technicalName'], ['module_read']));
    }

    /**
     * @depends testUploadModuleByArchive
     */
    public function testUninstallModuleAndRemoveFiles(): void
    {
        // Uninstall specific module deleteFiles true
        $this->updateItem('/module/test_install_cqrs_command/uninstall', [
            // We remove files, so the module no longer exists
            'deleteFiles' => true,
        ], ['module_write'], Response::HTTP_NO_CONTENT);

        // Check that the module no longer exists
        $this->getItem('/module/test_install_cqrs_command', ['module_read'], Response::HTTP_NOT_FOUND);
    }

    /**
     * @depends testUninstallModuleAndRemoveFiles
     */
    public function testBulkUninstallModule()
    {
        $modules = ['bankwire', 'ps_emailsubscription'];
        foreach ($modules as $module) {
            $moduleInfos = $this->getItem('/module/' . $module, ['module_read']);
            $this->assertGreaterThan(0, $moduleInfos['moduleId']);
            $this->assertTrue($moduleInfos['enabled']);
            $this->assertTrue($moduleInfos['installed']);
            $this->assertTrue(version_compare($moduleInfos['moduleVersion'], '0.1.0', '>='));
            $this->assertTrue(version_compare($moduleInfos['installedVersion'], '0.1.0', '>='));
        }

        // Uninstall specific module deleteFiles true
        $this->updateItem('/modules/uninstall', [
            'modules' => $modules,
            // Force removal of the files
            'deleteFiles' => true,
        ], ['module_write'], Response::HTTP_NO_CONTENT);

        // Module files have been removed, so they don't exist at all anymore, thus requesting their info results in a 404
        foreach ($modules as $module) {
            $this->getItem('/module/' . $module, ['module_read'], Response::HTTP_NOT_FOUND);
        }
    }

    public function testUpgradeModule(): void
    {
        $bearerToken = $this->getBearerToken(['module_write']);

        // Upload Zip from GitHub with version 2.1.2 the module is present but not installed
        $uploadedModule = $this->createItem('/module/upload-source', [
            'source' => 'https://github.com/PrestaShop/dashproducts/releases/download/v2.1.2/dashproducts.zip',
        ], ['module_write'], Response::HTTP_CREATED);

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
        $this->assertEquals($module212, $uploadedModule);
        $this->assertEquals($module212, $this->getItem('/module/' . $module212['technicalName'], ['module_read']));

        // Now we install the module
        $installedModule = $this->updateItem(sprintf('/module/%s/install', $module212['technicalName']), null, ['module_write']);

        // The ID is dynamic, so we fetch it after creation
        $this->assertArrayHasKey('moduleId', $installedModule);

        $installedModule212 = [
            'moduleId' => $installedModule['moduleId'],
            'technicalName' => 'dashproducts',
            'moduleVersion' => '2.1.2',
            'installedVersion' => '2.1.2',
            'enabled' => true,
            'installed' => true,
        ];
        $this->assertEquals($installedModule212, $installedModule);
        $this->assertEquals($installedModule212, $this->getItem('/module/' . $installedModule212['technicalName'], ['module_read']));

        // Now upload the source for version 2.1.3, the module version is updated but not the installed one (not upgraded yet)
        $reUploadedModule = $this->createItem('/module/upload-source', [
            'source' => 'https://github.com/PrestaShop/dashproducts/releases/download/v2.1.3/dashproducts.zip',
        ], ['module_write'], Response::HTTP_CREATED);

        $module213 = [
            // ID has not changed since previous installation
            'moduleId' => $installedModule212['moduleId'],
            'technicalName' => 'dashproducts',
            // Module version (based on the disk, available files) has been updated
            'moduleVersion' => '2.1.3',
            // Module is simply uploaded not installed (an upgrade action is still needed)
            'installedVersion' => '2.1.2',
            'enabled' => true,
            'installed' => true,
        ];
        $this->assertEquals($module213, $reUploadedModule);
        $this->assertEquals($module213, $this->getItem('/module/' . $module213['technicalName'], ['module_read']));

        // Now perform the upgrade action
        $upgradedModule = $this->updateItem(sprintf('/module/%s/upgrade', $installedModule212['technicalName']), null, ['module_write']);

        // Check response from status upgrade request (the installedVersion field should have been updated)
        $upgradedModule213 = ['installedVersion' => '2.1.3'] + $module213;
        $this->assertEquals($upgradedModule213, $upgradedModule);
        $this->assertEquals($upgradedModule213, $this->getItem('/module/' . $upgradedModule213['technicalName'], ['module_read']));
    }
}
