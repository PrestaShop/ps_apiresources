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

class ClearOutdatedSessionsEndpointTest extends ApiTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::createApiClient(['customer_session_write', 'employee_session_write']);
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'clear outdated customer sessions endpoint' => ['DELETE', '/customer-sessions/bulk-clear-outdated'];
        yield 'clear outdated employee sessions endpoint' => ['DELETE', '/employee-sessions/bulk-clear-outdated'];
    }

    public function testClearOutdatedCustomerSessions(): void
    {
        // Seed one expired customer session (date_upd = yesterday).
        \Db::getInstance()->insert('customer_session', [
            'id_customer' => 0,
            'token' => bin2hex(random_bytes(16)),
            'date_add' => date('Y-m-d H:i:s', strtotime('-1 year')),
            'date_upd' => date('Y-m-d H:i:s', strtotime('-1 year')),
        ]);
        $seededId = (int) \Db::getInstance()->Insert_ID();

        $this->requestApi(
            'DELETE',
            '/customer-sessions/bulk-clear-outdated',
            null,
            ['customer_session_write'],
            Response::HTTP_NO_CONTENT
        );

        $stillThere = (int) \Db::getInstance()->getValue(
            'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'customer_session` WHERE `id_customer_session` = ' . $seededId
        );
        $this->assertSame(0, $stillThere);
    }

    public function testClearOutdatedEmployeeSessions(): void
    {
        \Db::getInstance()->insert('employee_session', [
            'id_employee' => 0,
            'token' => bin2hex(random_bytes(16)),
            'date_add' => date('Y-m-d H:i:s', strtotime('-1 year')),
            'date_upd' => date('Y-m-d H:i:s', strtotime('-1 year')),
        ]);
        $seededId = (int) \Db::getInstance()->Insert_ID();

        $this->requestApi(
            'DELETE',
            '/employee-sessions/bulk-clear-outdated',
            null,
            ['employee_session_write'],
            Response::HTTP_NO_CONTENT
        );

        $stillThere = (int) \Db::getInstance()->getValue(
            'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'employee_session` WHERE `id_employee_session` = ' . $seededId
        );
        $this->assertSame(0, $stillThere);
    }
}
