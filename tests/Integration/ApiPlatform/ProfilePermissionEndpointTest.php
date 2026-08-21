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

class ProfilePermissionEndpointTest extends ApiTestCase
{
    private const SUPER_ADMIN_PROFILE_ID = 1;

    private static int $profileId;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::createApiClient(['profile_read', 'profile_write']);
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        DatabaseDump::restoreTables(['profile', 'profile_lang', 'access', 'module_access']);
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'permissions configuration endpoint' => ['GET', '/profiles/permissions?employeeProfileId=1'];

        // The two permission endpoints are covered by testPermissionEndpointsAreProtected()
        // instead: the generic helper sends no body, and these commands cannot be built without
        // one, so the request fails at deserialization before the scope check ever runs.
    }

    /**
     * The scope protection of the two permission endpoints, asserted with a body.
     *
     * ScopeCheckerListener runs on kernel.request, but so does ApiPlatform's DeserializeListener,
     * and it goes first: UpdateTabPermissionsCommand and UpdateModulePermissionsCommand both
     * require tabId/moduleId, permission and isActive, none of which the URI carries, so an
     * empty request answers 400 before the scope is ever looked at. Sending a valid body is what
     * makes the 401 and the 403 observable.
     */
    public function testPermissionEndpointsAreProtected(): void
    {
        $endpoints = [
            '/profiles/' . self::$profileId . '/tab-permissions' => ['tabId' => $this->getConfigurableTabId(), 'permission' => 'view', 'enabled' => true],
            '/profiles/' . self::$profileId . '/module-permissions' => ['moduleId' => $this->getConfigurableModuleId(), 'permission' => 'view', 'enabled' => true],
        ];

        foreach ($endpoints as $uri => $payload) {
            $this->requestApi('PUT', $uri, $payload, [], Response::HTTP_UNAUTHORIZED);
            $this->requestApi('PUT', $uri, $payload, ['profile_read'], Response::HTTP_FORBIDDEN);
        }
    }

    /**
     * The profile is created through POST /profiles, which the Profile resource of this same
     * PR provides. The permission tests used to seed it with new Profile()->add().
     */
    protected function setUp(): void
    {
        parent::setUp();

        if (!isset(self::$profileId)) {
            self::$profileId = (int) $this->createItem('/profiles', [
                'names' => ['en-US' => 'API permission test profile', 'fr-FR' => 'API permission test profile'],
            ], ['profile_write'])['profileId'];
        }
    }

    private function getPermissionsConfiguration(): array
    {
        return $this->getItem(
            '/profiles/permissions?employeeProfileId=' . self::SUPER_ADMIN_PROFILE_ID,
            ['profile_read']
        );
    }

    public function testGetPermissionsConfiguration(): void
    {
        $result = $this->getPermissionsConfiguration();

        $this->assertEquals(
            [
                'employeeProfileId',
                'hasEmployeeEditPermission',
                'profilePermissionsForTabs',
                'profilePermissionsForModules',
                'bulkConfiguration',
                'profiles',
                'tabs',
                'permissions',
            ],
            array_keys($result)
        );
        $this->assertSame(self::SUPER_ADMIN_PROFILE_ID, $result['employeeProfileId']);

        // The profile created by this suite is part of the configuration
        $this->assertArrayHasKey(self::$profileId, $result['profilePermissionsForTabs']);
        $this->assertArrayHasKey(self::$profileId, $result['profilePermissionsForModules']);
    }

    public function testUpdateTabPermission(): void
    {
        $tabId = $this->getConfigurableTabId();

        // Disable the "view" permission on the tab, then read it back through the API instead
        // of Profile::resetStaticCache() + Profile::getProfileAccess()
        $this->updateItem(
            '/profiles/' . self::$profileId . '/tab-permissions',
            ['tabId' => $tabId, 'permission' => 'view', 'enabled' => false],
            ['profile_write'],
            Response::HTTP_NO_CONTENT
        );
        $this->assertFalse($this->getTabViewPermission($tabId));

        $this->updateItem(
            '/profiles/' . self::$profileId . '/tab-permissions',
            ['tabId' => $tabId, 'permission' => 'view', 'enabled' => true],
            ['profile_write'],
            Response::HTTP_NO_CONTENT
        );
        $this->assertTrue($this->getTabViewPermission($tabId));
    }

    public function testUpdateTabPermissionWithInvalidPermissionIsRejected(): void
    {
        $this->updateItem(
            '/profiles/' . self::$profileId . '/tab-permissions',
            ['tabId' => $this->getConfigurableTabId(), 'permission' => 'not-a-permission', 'enabled' => true],
            ['profile_write'],
            Response::HTTP_UNPROCESSABLE_ENTITY
        );
    }

    public function testUpdateModulePermission(): void
    {
        $moduleId = $this->getConfigurableModuleId();

        $this->updateItem(
            '/profiles/' . self::$profileId . '/module-permissions',
            ['moduleId' => $moduleId, 'permission' => 'view', 'enabled' => true],
            ['profile_write'],
            Response::HTTP_NO_CONTENT
        );
        $this->assertTrue($this->getModuleViewPermission($moduleId));

        $this->updateItem(
            '/profiles/' . self::$profileId . '/module-permissions',
            ['moduleId' => $moduleId, 'permission' => 'view', 'enabled' => false],
            ['profile_write'],
            Response::HTTP_NO_CONTENT
        );
        $this->assertFalse($this->getModuleViewPermission($moduleId));
    }

    /**
     * A tab that is both configurable (the configuration endpoint only returns a whitelist of
     * tabs) and has a READ authorization role — without one, updateLgcAccess() raises
     * "slug not found" and the command fails. The role part is a SQL lookup because no
     * endpoint exposes ps_authorization_role; the whitelist part comes from the API.
     */
    private function getConfigurableTabId(): int
    {
        $configurableTabIds = array_column(
            $this->getPermissionsConfiguration()['profilePermissionsForTabs'][self::$profileId],
            'id_tab'
        );

        $rows = \Db::getInstance()->executeS(
            'SELECT t.`id_tab` FROM `' . _DB_PREFIX_ . 'tab` t
             WHERE t.`class_name` != \'\' AND EXISTS (
                 SELECT 1 FROM `' . _DB_PREFIX_ . 'authorization_role` r
                 WHERE r.`slug` = CONCAT(\'ROLE_MOD_TAB_\', UPPER(t.`class_name`), \'_READ\')
             )
             ORDER BY t.`id_tab` ASC'
        );

        foreach ($rows ?: [] as $row) {
            if (in_array((int) $row['id_tab'], array_map('intval', $configurableTabIds), true)) {
                return (int) $row['id_tab'];
            }
        }

        $this->markTestSkipped('No tab is both configurable and carries a READ authorization role.');
    }

    private function getConfigurableModuleId(): int
    {
        $moduleId = (int) \Db::getInstance()->getValue(
            'SELECT m.`id_module` FROM `' . _DB_PREFIX_ . 'module` m
             WHERE EXISTS (
                 SELECT 1 FROM `' . _DB_PREFIX_ . 'authorization_role` r
                 WHERE r.`slug` = CONCAT(\'ROLE_MOD_MODULE_\', UPPER(m.`name`), \'_READ\')
             )
             ORDER BY m.`id_module` ASC'
        );

        if (0 === $moduleId) {
            $this->markTestSkipped('No module carries a READ authorization role.');
        }

        return $moduleId;
    }

    private function getTabViewPermission(int $tabId): bool
    {
        foreach ($this->getPermissionsConfiguration()['profilePermissionsForTabs'][self::$profileId] as $access) {
            if ((int) $access['id_tab'] === $tabId) {
                return (bool) $access['view'];
            }
        }

        $this->fail(sprintf('Tab %d is not part of the permission configuration.', $tabId));
    }

    private function getModuleViewPermission(int $moduleId): bool
    {
        foreach ($this->getPermissionsConfiguration()['profilePermissionsForModules'][self::$profileId] as $access) {
            if ((int) $access['id_module'] === $moduleId) {
                return (bool) $access['view'];
            }
        }

        $this->fail(sprintf('Module %d is not part of the permission configuration.', $moduleId));
    }
}
