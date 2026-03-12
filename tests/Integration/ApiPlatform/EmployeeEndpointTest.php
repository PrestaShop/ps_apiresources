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
use Tests\Resources\Resetter\LanguageResetter;

class EmployeeEndpointTest extends ApiTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        // Add the fr-FR language to test multi lang values accurately
        LanguageResetter::resetLanguages();
        self::addLanguageByLocale('fr-FR');
        DatabaseDump::restoreTables(['gender', 'gender_lang']);
        self::createApiClient(['employee_write', 'employee_read']);
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        // Reset DB as it was before this test
        LanguageResetter::resetLanguages();
        DatabaseDump::restoreTables(['gender', 'gender_lang']);
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'create endpoint' => [
            'POST',
            '/employees',
            'multipart/form-data',
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

        $employee = $this->requestApi('POST', '/employees', null, ['employee_write'], Response::HTTP_CREATED);
        $this->assertArrayHasKey('employeeId', $employee);
        $employeeId = $employee['employeeId'];
        $this->assertEquals(
            [
                'employeeId' => $employeeId,
            ],
            $employee
        );

        $newItemsCount = $this->countItems('/employees', ['employee_read']);
        $this->assertEquals($itemsCount + 1, $newItemsCount);

        return $employeeId;
    }

    /**
     * @depends testAddEmployee
     *
     * @param int $employeeId
     *
     * @return int
     */
    public function testGetEmployee(int $employeeId): int
    {
        $employee = $this->getItem('/employees/' . $employeeId, ['employee_read']);
        $this->assertEquals(
            [
                'employeeId' => $employeeId,
                'firstName' => 'John',
                'lastName' => 'Doe',
                'email' => 'john.doe@example.com',
                'avatarUrl' => null,
                'defaultPageId' => 1,
                'languageId' => 1,
                'active' => true,
                'profileId' => 1,
                'shopAssociation' => [],
            ],
            $employee
        );

        return $employeeId;
    }

    /**
     * @depends testGetEmployee
     *
     * @param int $employeeId
     *
     * @return int
     */
    public function testPartialUpdateEmployee(int $employeeId): int
    {
        $updatedEmployee = $this->partialUpdateItem('/employees/' . $employeeId, [
            'firstName' => 'test',
        ], ['employee_write']);
        $this->assertEquals(
            [
                'employeeId' => $employeeId,
                'firstName' => 'test',
                'lastName' => 'Doe',
                'email' => 'john.doe@example.com',
                'avatarUrl' => null,
                'defaultPageId' => 1,
                'languageId' => 1,
                'enabled' => true,
                'profileId' => 1,
                'shopAssociation' => [],
            ],
            $updatedEmployee
        );

        $updatedEmployee = $this->partialUpdateItem('/employees/' . $employeeId, [
            'lastName' => 'Updated',
        ], ['employee_write']);
        $this->assertEquals(
            [
                'employeeId' => $employeeId,
                'firstName' => 'test',
                'lastName' => 'Updated',
                'email' => 'john.doe@example.com',
                'avatarUrl' => null,
                'defaultPageId' => 1,
                'languageId' => 1,
                'enabled' => true,
                'profileId' => 1,
                'shopAssociation' => [],
            ],
            $updatedEmployee
        );

        return $employeeId;
    }

    /**
     * @depends testPartialUpdateEmployee
     *
     * @param int $employeeId
     *
     * @return int
     */
    public function testGetUpdatedEmployee(int $employeeId): int
    {
        $employee = $this->getItem('/employees/' . $employeeId, ['employee_read']);
        $this->assertEquals(
            [
                'employeeId' => $employeeId,
                'names' => [
                    'en-US' => 'name en Updated',
                    'fr-FR' => 'name fr Updated',
                ],
                'gender' => 2,
                'width' => 16,
                'height' => 16,
            ],
            $employee
        );

        return $employeeId;
    }

    /**
     * @depends testGetUpdatedEmployee
     *
     * @param int $employeeId
     *
     * @return int
     */
    public function testListEmployees(int $employeeId): int
    {
        $employees = $this->listItems('/employees', ['employee_read']);
        $this->assertGreaterThanOrEqual(1, $employees['totalItems']);

        // Search for the one created previously during the tests and assert its data in the list
        $testEmployee = null;
        foreach ($employees['items'] as $employee) {
            if ($employee['employeeId'] === $employeeId) {
                $testEmployee = $employee;
            }
        }
        $this->assertNotNull($testEmployee);
        $this->assertEquals(
            [
                'employeeId' => $employeeId,
                'name' => 'name en Updated',
                'gender' => 2,
            ],
            $testEmployee
        );

        return $employeeId;
    }

    /**
     * @depends testListEmployees
     *
     * @param int $employeeId
     *
     * @return int
     */
    public function testDeleteEmployee(int $employeeId): void
    {
        $return = $this->deleteItem('/employees/' . $employeeId, ['employee_write']);
        // This endpoint return empty response and 204 HTTP code
        $this->assertNull($return);

        // Getting the item should result in a 404 now
        $this->getItem('/employees/' . $employeeId, ['employee_read'], Response::HTTP_NOT_FOUND);
    }

    /**
     * @depends testDeleteEmployee
     *
     * @param int $employeeId
     *
     * @return int
     */
    public function testBulkDeleteEmployees(): void
    {
        // There are employees in default fixtures
        $employees = $this->listItems('/employees', ['employee_read']);
        $this->assertEquals(2, $employees['totalItems']);

        // We create two new employees
        $employeeNew1 = $this->requestApi('POST', '/employees', null, ['employee_write'], Response::HTTP_CREATED);
        $this->assertArrayHasKey('employeeId', $employeeNew1);

        $employeeNew2 = $this->requestApi('POST', '/employees', null, ['employee_write'], Response::HTTP_CREATED);
        $this->assertArrayHasKey('employeeId', $employeeNew2);

        // There are employees in default fixtures
        $employees = $this->listItems('/employees', ['employee_read']);
        $this->assertEquals(4, $employees['totalItems']);

        // We remove the two employees
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

        $this->assertEquals(2, $this->countItems('/employees', ['employee_read']));
    }
}
