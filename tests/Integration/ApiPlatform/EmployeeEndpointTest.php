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
        self::resetTables();
        self::createApiClient(['employee_write', 'employee_read']);
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        self::resetTables();
    }

    protected static function resetTables(): void
    {
        DatabaseDump::restoreTables([
            'employee',
            'employee_shop',
        ]);
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'create endpoint' => [
            'POST',
            '/employees',
        ];

        yield 'get endpoint' => [
            'GET',
            '/employees/1',
        ];

        yield 'update endpoint' => [
            'PATCH',
            '/employees/1',
        ];

        yield 'delete endpoint' => [
            'DELETE',
            '/employees/1',
        ];

        yield 'list endpoint' => [
            'GET',
            '/employees',
        ];

        yield 'toggle status endpoint' => [
            'PUT',
            '/employees/1/toggle-status',
        ];

        yield 'bulk update status endpoint' => [
            'PUT',
            '/employees/bulk-update-status',
        ];

        yield 'bulk delete endpoint' => [
            'DELETE',
            '/employees/bulk-delete',
        ];
    }

    /**
     * Every fixture below is created through POST /employees. The status/bulk PR seeded them
     * through the legacy Employee object, on the grounds that AddEmployeeCommand refuses a
     * profile the context employee cannot grant — but the create endpoint of this same
     * resource does it fine, as testAddEmployee shows.
     */
    private function createEmployee(string $email): int
    {
        $employee = $this->createItem('/employees', [
            'firstName' => 'John',
            'lastName' => 'Doe',
            'email' => $email,
            'password' => 'TestPassword123!',
            'defaultPageId' => 1,
            'languageId' => 1,
            'enabled' => true,
            'profileId' => 1,
            'shopAssociation' => [1],
            'hasEnabledGravatar' => false,
        ], ['employee_write'], Response::HTTP_CREATED);

        return (int) $employee['employeeId'];
    }

    private function isEmployeeEnabled(int $employeeId): bool
    {
        return (bool) $this->getItem('/employees/' . $employeeId, ['employee_read'])['enabled'];
    }

    public function testAddEmployee(): int
    {
        $itemsCount = $this->countItems('/employees', ['employee_read']);

        $postData = [
            'firstName' => 'John',
            'lastName' => 'Doe',
            'email' => 'john.doe@example.com',
            'password' => 'TestPassword123!',
            'defaultPageId' => 1,
            'languageId' => 1,
            'enabled' => true,
            'profileId' => 1,
            'shopAssociation' => [1],
            'hasEnabledGravatar' => false,
        ];

        $employee = $this->createItem('/employees', $postData, ['employee_write'], Response::HTTP_CREATED);
        $this->assertArrayHasKey('employeeId', $employee);
        $employeeId = $employee['employeeId'];

        $this->assertSame('John', $employee['firstName']);
        $this->assertSame('Doe', $employee['lastName']);
        $this->assertSame('john.doe@example.com', $employee['email']);
        $this->assertSame(1, $employee['profileId']);
        $this->assertTrue($employee['enabled']);
        // The password is write only and must never be normalized back out
        $this->assertArrayNotHasKey('password', $employee);

        $newItemsCount = $this->countItems('/employees', ['employee_read']);
        $this->assertEquals($itemsCount + 1, $newItemsCount);

        return $employeeId;
    }

    /**
     * @depends testAddEmployee
     */
    public function testGetEmployee(int $employeeId): int
    {
        $employee = $this->getItem('/employees/' . $employeeId, ['employee_read']);
        $this->assertEquals($employeeId, $employee['employeeId']);
        $this->assertSame('John', $employee['firstName']);
        $this->assertSame('Doe', $employee['lastName']);
        $this->assertSame('john.doe@example.com', $employee['email']);
        $this->assertSame(1, $employee['defaultPageId']);
        $this->assertSame(1, $employee['languageId']);
        $this->assertTrue($employee['enabled']);
        $this->assertSame(1, $employee['profileId']);
        $this->assertArrayNotHasKey('password', $employee);

        return $employeeId;
    }

    /**
     * @depends testGetEmployee
     */
    public function testPartialUpdateEmployee(int $employeeId): int
    {
        $updatedEmployee = $this->partialUpdateItem('/employees/' . $employeeId, [
            'firstName' => 'Johnny',
        ], ['employee_write']);
        $this->assertSame('Johnny', $updatedEmployee['firstName']);
        $this->assertSame('Doe', $updatedEmployee['lastName']);

        $updatedEmployee = $this->partialUpdateItem('/employees/' . $employeeId, [
            'lastName' => 'Updated',
        ], ['employee_write']);
        $this->assertSame('Johnny', $updatedEmployee['firstName']);
        $this->assertSame('Updated', $updatedEmployee['lastName']);

        // Verify the GET reflects the changes
        $employee = $this->getItem('/employees/' . $employeeId, ['employee_read']);
        $this->assertSame('Johnny', $employee['firstName']);
        $this->assertSame('Updated', $employee['lastName']);

        return $employeeId;
    }

    /**
     * @depends testPartialUpdateEmployee
     */
    public function testListEmployees(int $employeeId): int
    {
        $employees = $this->listItems('/employees', ['employee_read']);
        $this->assertGreaterThanOrEqual(1, $employees['totalItems']);

        // Search for the one created previously during the tests
        $testEmployee = null;
        foreach ($employees['items'] as $employee) {
            if ($employee['employeeId'] === $employeeId) {
                $testEmployee = $employee;
                break;
            }
        }
        $this->assertNotNull($testEmployee);
        $this->assertEquals($employeeId, $testEmployee['employeeId']);

        return $employeeId;
    }

    /**
     * @depends testListEmployees
     */
    public function testDeleteEmployee(int $employeeId): void
    {
        $return = $this->deleteItem('/employees/' . $employeeId, ['employee_write']);
        // This endpoint returns empty response and 204 HTTP code
        $this->assertNull($return);

        // Getting the item should result in a 404 now
        $this->getItem('/employees/' . $employeeId, ['employee_read'], Response::HTTP_NOT_FOUND);
    }

    public function testToggleStatus(): void
    {
        $employeeId = $this->createEmployee('toggle.actions@example.com');
        $this->assertTrue($this->isEmployeeEnabled($employeeId));

        // Blind toggle: enabled -> disabled
        $this->updateItem('/employees/' . $employeeId . '/toggle-status', [], ['employee_write'], Response::HTTP_NO_CONTENT);
        $this->assertFalse($this->isEmployeeEnabled($employeeId));

        // Blind toggle again: disabled -> enabled
        $this->updateItem('/employees/' . $employeeId . '/toggle-status', [], ['employee_write'], Response::HTTP_NO_CONTENT);
        $this->assertTrue($this->isEmployeeEnabled($employeeId));
    }

    public function testToggleStatusNotFound(): void
    {
        $this->updateItem('/employees/999999/toggle-status', [], ['employee_write'], Response::HTTP_NOT_FOUND);
    }

    public function testBulkUpdateStatus(): void
    {
        $employeeIds = [
            $this->createEmployee('bulk1.actions@example.com'),
            $this->createEmployee('bulk2.actions@example.com'),
        ];

        $this->updateItem('/employees/bulk-update-status', [
            'employeeIds' => $employeeIds,
            'enabled' => false,
        ], ['employee_write'], Response::HTTP_NO_CONTENT);

        foreach ($employeeIds as $employeeId) {
            $this->assertFalse($this->isEmployeeEnabled($employeeId));
        }

        $this->updateItem('/employees/bulk-update-status', [
            'employeeIds' => $employeeIds,
            'enabled' => true,
        ], ['employee_write'], Response::HTTP_NO_CONTENT);

        foreach ($employeeIds as $employeeId) {
            $this->assertTrue($this->isEmployeeEnabled($employeeId));
        }
    }

    public function testBulkDelete(): void
    {
        $employeeIds = [
            $this->createEmployee('bulkdelete1.actions@example.com'),
            $this->createEmployee('bulkdelete2.actions@example.com'),
        ];

        $this->bulkDeleteItems('/employees/bulk-delete', [
            'employeeIds' => $employeeIds,
        ], ['employee_write']);

        // Deleted employees are really gone, the GET no longer resolves them
        foreach ($employeeIds as $employeeId) {
            $this->getItem('/employees/' . $employeeId, ['employee_read'], Response::HTTP_NOT_FOUND);
        }
    }
}
