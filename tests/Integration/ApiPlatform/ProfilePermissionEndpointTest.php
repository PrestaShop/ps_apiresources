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
    private static int $profileId;
    private static int $tabId;
    private static int $moduleId;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::createApiClient(['profile_write']);

        $profile = new \Profile();
        $profile->name = [];
        foreach (\Language::getIDs(false) as $langId) {
            $profile->name[(int) $langId] = 'API permission test profile';
        }
        $profile->add();
        self::$profileId = (int) $profile->id;

        // Pick a tab/module that actually has a "view" (READ) authorization role, otherwise
        // updateLgcAccess() raises "slug not found" and the command fails.
        self::$tabId = (int) \Db::getInstance()->getValue(
            'SELECT t.`id_tab` FROM `' . _DB_PREFIX_ . 'tab` t
             WHERE t.`class_name` != "" AND EXISTS (
                 SELECT 1 FROM `' . _DB_PREFIX_ . 'authorization_role` r
                 WHERE r.`slug` = CONCAT("ROLE_MOD_TAB_", UPPER(t.`class_name`), "_READ")
             )
             ORDER BY t.`id_tab` ASC'
        );
        self::$moduleId = (int) \Db::getInstance()->getValue(
            'SELECT m.`id_module` FROM `' . _DB_PREFIX_ . 'module` m
             WHERE EXISTS (
                 SELECT 1 FROM `' . _DB_PREFIX_ . 'authorization_role` r
                 WHERE r.`slug` = CONCAT("ROLE_MOD_MODULE_", UPPER(m.`name`), "_READ")
             )
             ORDER BY m.`id_module` ASC'
        );
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        DatabaseDump::restoreTables(['profile', 'profile_lang', 'access', 'module_access']);
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'tab permission endpoint' => ['PUT', '/profiles/1/tab-permissions'];
        yield 'module permission endpoint' => ['PUT', '/profiles/1/module-permissions'];
    }

    public function testUpdateTabPermission(): void
    {
        // Disable the "view" permission on the tab
        $this->updateItem(
            '/profiles/' . self::$profileId . '/tab-permissions',
            ['tabId' => self::$tabId, 'permission' => 'view', 'active' => false],
            ['profile_write'],
            Response::HTTP_NO_CONTENT
        );
        \Profile::resetStaticCache();
        $access = \Profile::getProfileAccess(self::$profileId, self::$tabId);
        $this->assertSame('0', $access['view']);

        // Enable it
        $this->updateItem(
            '/profiles/' . self::$profileId . '/tab-permissions',
            ['tabId' => self::$tabId, 'permission' => 'view', 'active' => true],
            ['profile_write'],
            Response::HTTP_NO_CONTENT
        );
        \Profile::resetStaticCache();
        $access = \Profile::getProfileAccess(self::$profileId, self::$tabId);
        $this->assertSame('1', $access['view']);
    }

    public function testUpdateTabPermissionWithInvalidPermissionIsRejected(): void
    {
        $this->updateItem(
            '/profiles/' . self::$profileId . '/tab-permissions',
            ['tabId' => self::$tabId, 'permission' => 'not-a-permission', 'active' => true],
            ['profile_write'],
            Response::HTTP_UNPROCESSABLE_ENTITY
        );
    }

    public function testUpdateModulePermission(): void
    {
        // Handler throws on failure, so a 204 confirms the module permission was updated
        $this->updateItem(
            '/profiles/' . self::$profileId . '/module-permissions',
            ['moduleId' => self::$moduleId, 'permission' => 'view', 'active' => true],
            ['profile_write'],
            Response::HTTP_NO_CONTENT
        );
    }
}
