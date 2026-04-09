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

class EmployeeEndpointTest extends ApiTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::createApiClient(['employee_read', 'employee_write']);
        DatabaseDump::restoreTables(['employee', 'employee_shop']);
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        DatabaseDump::restoreTables(['employee', 'employee_shop']);
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'get employee endpoint' => ['GET', '/employees/1'];
        yield 'update employee endpoint' => ['PATCH', '/employees/1'];
        yield 'delete employee endpoint' => ['DELETE', '/employees/1'];
        yield 'toggle employee status endpoint' => ['PUT', '/employees/1/toggle-status'];
        yield 'bulk delete employees endpoint' => ['DELETE', '/employees/bulk-delete'];
        yield 'bulk update employees status endpoint' => ['PUT', '/employees/bulk-update-status'];
    }

    public function testGetEmployee(): void
    {
        $employee = $this->getItem('/employees/1', ['employee_read']);

        $this->assertEquals(1, $employee['employeeId']);
        $this->assertArrayHasKey('firstName', $employee);
        $this->assertArrayHasKey('lastName', $employee);
        $this->assertArrayHasKey('email', $employee);
        $this->assertArrayHasKey('defaultPageId', $employee);
        $this->assertArrayHasKey('languageId', $employee);
        $this->assertArrayHasKey('profileId', $employee);
        $this->assertArrayHasKey('shopIds', $employee);
        $this->assertArrayHasKey('avatarUrl', $employee);

        $expectedEmployee = $employee;
        $this->assertEquals($expectedEmployee, $this->getItem('/employees/1', ['employee_read']));
    }

    public function testGetNonExistentEmployee(): void
    {
        $this->getItem('/employees/999999', ['employee_read'], Response::HTTP_NOT_FOUND);
    }
}
