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

class EmployeeActionsEndpointTest extends ApiTestCase
{
    private static int $profileId;

    private static int $langId;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::createApiClient(['employee_write']);

        // A non SuperAdmin profile so the seeded employees are never considered the "last admin",
        // which would block the toggle/bulk status and bulk delete operations.
        self::$profileId = (int) \Db::getInstance()->getValue(
            'SELECT id_profile FROM `' . _DB_PREFIX_ . 'profile` WHERE id_profile <> 1 ORDER BY id_profile ASC'
        );
        self::$langId = (int) \Configuration::get('PS_LANG_DEFAULT');
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        DatabaseDump::restoreTables(['employee', 'employee_shop']);
    }

    public static function getProtectedEndpoints(): iterable
    {
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

    public function testToggleStatus(): void
    {
        $employeeId = $this->createEmployee('toggle.actions@prestashop.com', true);
        $this->assertTrue($this->getEmployeeActiveStatus($employeeId));

        // Blind toggle: enabled -> disabled
        $this->updateItem('/employees/' . $employeeId . '/toggle-status', [], ['employee_write'], Response::HTTP_NO_CONTENT);
        $this->assertFalse($this->getEmployeeActiveStatus($employeeId));

        // Blind toggle again: disabled -> enabled
        $this->updateItem('/employees/' . $employeeId . '/toggle-status', [], ['employee_write'], Response::HTTP_NO_CONTENT);
        $this->assertTrue($this->getEmployeeActiveStatus($employeeId));
    }

    public function testToggleStatusNotFound(): void
    {
        $this->updateItem('/employees/999999/toggle-status', [], ['employee_write'], Response::HTTP_NOT_FOUND);
    }

    public function testBulkUpdateStatus(): void
    {
        $employeeId1 = $this->createEmployee('bulk1.actions@prestashop.com', true);
        $employeeId2 = $this->createEmployee('bulk2.actions@prestashop.com', true);

        $this->updateItem('/employees/bulk-update-status', [
            'employeeIds' => [$employeeId1, $employeeId2],
            'enabled' => false,
        ], ['employee_write'], Response::HTTP_NO_CONTENT);

        $this->assertFalse($this->getEmployeeActiveStatus($employeeId1));
        $this->assertFalse($this->getEmployeeActiveStatus($employeeId2));

        $this->updateItem('/employees/bulk-update-status', [
            'employeeIds' => [$employeeId1, $employeeId2],
            'enabled' => true,
        ], ['employee_write'], Response::HTTP_NO_CONTENT);

        $this->assertTrue($this->getEmployeeActiveStatus($employeeId1));
        $this->assertTrue($this->getEmployeeActiveStatus($employeeId2));
    }

    public function testBulkDelete(): void
    {
        $employeeId1 = $this->createEmployee('bulkdelete1.actions@prestashop.com', true);
        $employeeId2 = $this->createEmployee('bulkdelete2.actions@prestashop.com', true);

        $this->bulkDeleteItems('/employees/bulk-delete', [
            'employeeIds' => [$employeeId1, $employeeId2],
        ], ['employee_write']);

        $this->assertFalse(\Validate::isLoadedObject(new \Employee($employeeId1)));
        $this->assertFalse(\Validate::isLoadedObject(new \Employee($employeeId2)));
    }

    /**
     * Seeds an employee directly through the legacy object model. AddEmployeeCommand cannot be
     * used here because it checks that the context employee can grant the profile, and there is
     * no authenticated employee in the API/test context.
     */
    private function createEmployee(string $email, bool $active): int
    {
        $employee = new \Employee();
        $employee->id_profile = self::$profileId;
        $employee->id_lang = self::$langId;
        $employee->firstname = 'John';
        $employee->lastname = 'Doe';
        $employee->email = $email;
        $employee->passwd = (new Hashing())->hash('Pr3st@Sh0p!Test');
        $employee->active = $active;
        $employee->add();

        return (int) $employee->id;
    }

    private function getEmployeeActiveStatus(int $employeeId): bool
    {
        return (bool) (new \Employee($employeeId))->active;
    }
}
