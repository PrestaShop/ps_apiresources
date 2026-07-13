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

class ProfilePermissionsEndpointTest extends ApiTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::createApiClient(['profile_read']);
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'profile permissions endpoint' => ['GET', '/profiles/1/permissions'];
    }

    public function testGetPermissionsForConfiguration(): void
    {
        // Employee profile id 1 = SuperAdmin.
        $result = $this->getItem('/profiles/1/permissions', ['profile_read']);

        $this->assertArrayHasKey('employeeProfileId', $result);
        $this->assertSame(1, $result['employeeProfileId']);
        $this->assertArrayHasKey('profilePermissionsForTabs', $result);
        $this->assertIsArray($result['profilePermissionsForTabs']);
        $this->assertArrayHasKey('profilePermissionsForModules', $result);
        $this->assertIsArray($result['profilePermissionsForModules']);
        $this->assertArrayHasKey('bulkConfiguration', $result);
        $this->assertIsArray($result['bulkConfiguration']);
        $this->assertArrayHasKey('profiles', $result);
        $this->assertArrayHasKey('tabs', $result);
        $this->assertArrayHasKey('permissions', $result);
        $this->assertArrayHasKey('hasEmployeeEditPermission', $result);
    }
}
