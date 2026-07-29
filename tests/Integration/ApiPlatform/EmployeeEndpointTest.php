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

        yield 'bulk delete endpoint' => [
            'DELETE',
            '/employees/bulk-delete',
        ];
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
            'active' => true,
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
        $this->assertTrue($employee['active']);

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
        $this->assertTrue($employee['active']);
        $this->assertSame(1, $employee['profileId']);

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

    public function testBulkDeleteEmployees(): void
    {
        // Create two employees to bulk-delete
        $employeeNew1 = $this->createItem('/employees', [
            'firstName' => 'Bulk',
            'lastName' => 'One',
            'email' => 'bulk.one@example.com',
            'password' => 'TestPassword123!',
            'defaultPageId' => 1,
            'languageId' => 1,
            'active' => true,
            'profileId' => 1,
            'shopAssociation' => [1],
            'hasEnabledGravatar' => false,
        ], ['employee_write'], Response::HTTP_CREATED);
        $this->assertArrayHasKey('employeeId', $employeeNew1);

        $employeeNew2 = $this->createItem('/employees', [
            'firstName' => 'Bulk',
            'lastName' => 'Two',
            'email' => 'bulk.two@example.com',
            'password' => 'TestPassword123!',
            'defaultPageId' => 1,
            'languageId' => 1,
            'active' => true,
            'profileId' => 1,
            'shopAssociation' => [1],
            'hasEnabledGravatar' => false,
        ], ['employee_write'], Response::HTTP_CREATED);
        $this->assertArrayHasKey('employeeId', $employeeNew2);

        $bulkEmployees = [
            $employeeNew1['employeeId'],
            $employeeNew2['employeeId'],
        ];

        $this->bulkDeleteItems('/employees/bulk-delete', [
            'employeeIds' => $bulkEmployees,
        ], ['employee_write']);

        // Assert the provided employees have been removed
        foreach ($bulkEmployees as $employeeId) {
            $this->getItem('/employees/' . $employeeId, ['employee_read'], Response::HTTP_NOT_FOUND);
        }
    }
}
