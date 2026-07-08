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

class EmployeeEmailEndpointTest extends ApiTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::createApiClient(['employee_read']);
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'get employee email endpoint' => ['GET', '/employees/1/emails'];
    }

    public function testGetEmployeeEmail(): void
    {
        $row = \Db::getInstance()->getRow(
            'SELECT `id_employee`, `email` FROM `' . _DB_PREFIX_ . 'employee` ORDER BY `id_employee` ASC'
        );
        $employeeId = (int) $row['id_employee'];
        $email = (string) $row['email'];

        $result = $this->getItem('/employees/' . $employeeId . '/emails', ['employee_read']);

        $this->assertArrayHasKey('employeeId', $result);
        $this->assertSame($employeeId, $result['employeeId']);
        $this->assertArrayHasKey('email', $result);
        $this->assertSame($email, $result['email']);
    }

    public function testGetUnknownEmployeeEmailReturnsNotFound(): void
    {
        $this->requestApi('GET', '/employees/9999999/emails', null, ['employee_read'], Response::HTTP_NOT_FOUND);
    }
}
