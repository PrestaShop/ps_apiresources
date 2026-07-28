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

class EmployeePasswordResetConfirmationEndpointTest extends ApiTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::createApiClient(['employee_write']);
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        DatabaseDump::restoreTables(['employee']);
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'reset employee password endpoint' => ['POST', '/employees/password-reset-confirmations'];
    }

    public function testResetEmployeePassword(): void
    {
        $employeeId = (int) \Db::getInstance()->getValue(
            'SELECT `id_employee` FROM `' . _DB_PREFIX_ . 'employee` ORDER BY `id_employee` ASC'
        );
        $token = bin2hex(random_bytes(20)); // 40 chars, matches DB column length
        $futureValidity = date('Y-m-d H:i:s', strtotime('+1 hour'));

        \Db::getInstance()->update('employee', [
            'reset_password_token' => $token,
            'reset_password_validity' => $futureValidity,
        ], 'id_employee = ' . $employeeId);

        $passwordBefore = (string) \Db::getInstance()->getValue(
            'SELECT `passwd` FROM `' . _DB_PREFIX_ . 'employee` WHERE `id_employee` = ' . $employeeId
        );

        $this->requestApi(
            'POST',
            '/employees/password-reset-confirmations',
            ['resetToken' => $token, 'password' => 'NewStrongPassword-9!'],
            ['employee_write'],
            Response::HTTP_CREATED
        );

        $passwordAfter = (string) \Db::getInstance()->getValue(
            'SELECT `passwd` FROM `' . _DB_PREFIX_ . 'employee` WHERE `id_employee` = ' . $employeeId
        );
        $this->assertNotSame($passwordBefore, $passwordAfter);
    }

    public function testInvalidTokenRejected(): void
    {
        $this->requestApi(
            'POST',
            '/employees/password-reset-confirmations',
            ['resetToken' => 'never-existed-in-db-' . uniqid(), 'password' => 'NewStrongPassword-9!'],
            ['employee_write'],
            Response::HTTP_UNPROCESSABLE_ENTITY
        );
    }
}
