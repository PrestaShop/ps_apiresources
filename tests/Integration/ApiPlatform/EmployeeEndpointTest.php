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

use PrestaShop\PrestaShop\Core\Crypto\Hashing;
use Symfony\Component\HttpFoundation\Response;
use Tests\Resources\DatabaseDump;

class EmployeeEndpointTest extends ApiTestCase
{
    private static int $profileId;

    private static int $langId;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::resetTables();
        self::createApiClient(['employee_write', 'employee_read']);

        // Any profile but the super admin one, which no employee may grant
        self::$profileId = (int) \Db::getInstance()->getValue(
            'SELECT id_profile FROM `' . _DB_PREFIX_ . 'profile` WHERE id_profile <> 1 ORDER BY id_profile ASC'
        );
        self::$langId = (int) \Configuration::get('PS_LANG_DEFAULT');
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
     * Seeded through the legacy object model, and not through the API: this resource has no
     * create operation, because AddEmployeeCommand builds the Password value object itself and
     * so requires the password policy bounds the CQRS normalizer has no way to supply. See the
     * class docblock of the Employee resource.
     */
    private function createEmployee(string $email): int
    {
        $employee = new \Employee();
        $employee->id_profile = self::$profileId;
        $employee->id_lang = self::$langId;
        $employee->firstname = 'John';
        $employee->lastname = 'Doe';
        $employee->email = $email;
        $employee->passwd = (new Hashing())->hash('Pr3st@Sh0p!Test');
        $employee->active = true;
        $employee->default_tab = self::accessibleTabId();
        $employee->add();

        return (int) $employee->id;
    }

    /**
     * EditEmployeeHandler refuses a default page the profile cannot view, so the fixtures and
     * the update payload have to agree on a tab this profile actually has access to.
     */
    private static function accessibleTabId(): int
    {
        static $tabId = null;

        if (null === $tabId) {
            foreach (\Profile::getProfileAccesses(self::$profileId) as $id => $access) {
                if ('1' === $access['view']) {
                    $tabId = (int) $id;
                    break;
                }
            }
            self::assertNotNull($tabId, sprintf('Profile %d can view no tab at all.', self::$profileId));
        }

        return $tabId;
    }

    private function isEmployeeEnabled(int $employeeId): bool
    {
        return (bool) $this->getItem('/employees/' . $employeeId, ['employee_read'])['enabled'];
    }

    public function testGetEmployee(): int
    {
        $employeeId = $this->createEmployee('john.doe@example.com');

        $employee = $this->getItem('/employees/' . $employeeId, ['employee_read']);
        $this->assertEquals($employeeId, $employee['employeeId']);
        $this->assertSame('John', $employee['firstName']);
        $this->assertSame('Doe', $employee['lastName']);
        $this->assertSame('john.doe@example.com', $employee['email']);
        $this->assertSame(self::$profileId, $employee['profileId']);
        $this->assertTrue($employee['enabled']);
        // The resource carries no password property at all, in either direction
        $this->assertArrayNotHasKey('password', $employee);

        return $employeeId;
    }

    /**
     * The PATCH is only nominally partial. EditEmployeeHandler overwrites firstname, lastname,
     * email, default_tab, id_lang, id_profile, active and has_enabled_gravatar from the command
     * without checking whether they were sent — omitting `enabled` silently disables the
     * employee — and it refuses an employee with no shop association. Every request therefore
     * has to carry the whole representation; only the field under test changes.
     *
     * @depends testGetEmployee
     */
    public function testPartialUpdateEmployee(int $employeeId): int
    {
        $payload = [
            'firstName' => 'John',
            'lastName' => 'Doe',
            'email' => 'john.doe@example.com',
            'defaultPageId' => self::accessibleTabId(),
            'languageId' => self::$langId,
            'profileId' => self::$profileId,
            'shopAssociation' => [1],
            'hasEnabledGravatar' => false,
            'enabled' => true,
        ];

        $updatedEmployee = $this->partialUpdateItem(
            '/employees/' . $employeeId,
            ['firstName' => 'Johnny'] + $payload,
            ['employee_write']
        );
        $this->assertSame('Johnny', $updatedEmployee['firstName']);
        $this->assertSame('Doe', $updatedEmployee['lastName']);

        $updatedEmployee = $this->partialUpdateItem(
            '/employees/' . $employeeId,
            ['firstName' => 'Johnny', 'lastName' => 'Updated'] + $payload,
            ['employee_write']
        );
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
        $this->assertEquals(
            ['employeeId', 'firstName', 'lastName', 'email', 'profileId', 'profileName', 'enabled', 'lastConnectionDate'],
            array_keys($testEmployee)
        );
        $this->assertSame('Johnny', $testEmployee['firstName']);
        $this->assertSame('Updated', $testEmployee['lastName']);
        $this->assertSame('john.doe@example.com', $testEmployee['email']);
        $this->assertSame(self::$profileId, $testEmployee['profileId']);
        $this->assertTrue($testEmployee['enabled']);
        // The grid selects e.*, so the raw rows carry the password hash and the reset token —
        // neither is declared on the resource, so neither is normalized out
        $this->assertArrayNotHasKey('passwd', $testEmployee);
        $this->assertArrayNotHasKey('resetPasswordToken', $testEmployee);

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
