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

use Tests\Resources\DatabaseDump;

class SecuritySessionEndpointTest extends ApiTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::resetTables();
        self::createApiClient([
            'customer_session_read', 'customer_session_write',
            'employee_session_read', 'employee_session_write',
        ]);
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        self::resetTables();
    }

    protected static function resetTables(): void
    {
        DatabaseDump::restoreTables([
            'customer_session',
            'employee_session',
        ]);
    }

    private function seedCustomerSession(int $idCustomer = 1): int
    {
        \Db::getInstance()->insert('customer_session', [
            'id_customer' => $idCustomer,
            'token' => bin2hex(random_bytes(16)),
            'date_add' => date('Y-m-d H:i:s'),
            'date_upd' => date('Y-m-d H:i:s'),
        ]);

        return (int) \Db::getInstance()->Insert_ID();
    }

    private function seedEmployeeSession(int $idEmployee = 1): int
    {
        \Db::getInstance()->insert('employee_session', [
            'id_employee' => $idEmployee,
            'token' => bin2hex(random_bytes(16)),
            'date_add' => date('Y-m-d H:i:s'),
            'date_upd' => date('Y-m-d H:i:s'),
        ]);

        return (int) \Db::getInstance()->Insert_ID();
    }

    /**
     * @param string[] $scopes
     *
     * @return int[]
     */
    private function listedIds(string $url, array $scopes): array
    {
        return array_column($this->listItems($url, $scopes)['items'], 'sessionId');
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'customer list endpoint' => ['GET', '/customer-sessions'];
        yield 'customer delete endpoint' => ['DELETE', '/customer-sessions/1'];
        yield 'employee list endpoint' => ['GET', '/employee-sessions'];
        yield 'employee delete endpoint' => ['DELETE', '/employee-sessions/1'];
    }

    public function testListCustomerSessions(): void
    {
        $sessionId = $this->seedCustomerSession();

        $paginated = $this->listItems('/customer-sessions?orderBy=sessionId&sortOrder=desc', ['customer_session_read']);
        $this->assertGreaterThanOrEqual(1, $paginated['totalItems']);
        $this->assertEquals('sessionId', $paginated['orderBy']);

        $first = $paginated['items'][0];
        $this->assertEquals($sessionId, $first['sessionId']);
        $this->assertEquals(1, $first['customerId']);
        $this->assertArrayHasKey('firstname', $first);
        $this->assertArrayHasKey('email', $first);
    }

    public function testDeleteCustomerSession(): void
    {
        $sessionId = $this->seedCustomerSession();

        $return = $this->deleteItem('/customer-sessions/' . $sessionId, ['customer_session_write']);
        // This endpoint returns an empty response and a 204 HTTP code
        $this->assertNull($return);

        $this->assertNotContains(
            $sessionId,
            $this->listedIds('/customer-sessions?orderBy=sessionId&sortOrder=desc', ['customer_session_read'])
        );
    }

    public function testBulkDeleteCustomerSessions(): void
    {
        $bulkIds = [$this->seedCustomerSession(), $this->seedCustomerSession()];

        $this->bulkDeleteItems('/customer-sessions/bulk-delete', [
            'sessionIds' => $bulkIds,
        ], ['customer_session_write']);

        $listed = $this->listedIds('/customer-sessions?orderBy=sessionId&sortOrder=desc', ['customer_session_read']);
        foreach ($bulkIds as $sessionId) {
            $this->assertNotContains($sessionId, $listed);
        }
    }

    public function testListEmployeeSessions(): void
    {
        $sessionId = $this->seedEmployeeSession();

        $paginated = $this->listItems('/employee-sessions?orderBy=sessionId&sortOrder=desc', ['employee_session_read']);
        $this->assertGreaterThanOrEqual(1, $paginated['totalItems']);
        $this->assertEquals('sessionId', $paginated['orderBy']);

        $first = $paginated['items'][0];
        $this->assertEquals($sessionId, $first['sessionId']);
        $this->assertEquals(1, $first['employeeId']);
        $this->assertArrayHasKey('firstname', $first);
        $this->assertArrayHasKey('email', $first);
    }

    public function testDeleteEmployeeSession(): void
    {
        $sessionId = $this->seedEmployeeSession();

        $return = $this->deleteItem('/employee-sessions/' . $sessionId, ['employee_session_write']);
        $this->assertNull($return);

        $this->assertNotContains(
            $sessionId,
            $this->listedIds('/employee-sessions?orderBy=sessionId&sortOrder=desc', ['employee_session_read'])
        );
    }

    public function testBulkDeleteEmployeeSessions(): void
    {
        $bulkIds = [$this->seedEmployeeSession(), $this->seedEmployeeSession()];

        $this->bulkDeleteItems('/employee-sessions/bulk-delete', [
            'sessionIds' => $bulkIds,
        ], ['employee_session_write']);

        $listed = $this->listedIds('/employee-sessions?orderBy=sessionId&sortOrder=desc', ['employee_session_read']);
        foreach ($bulkIds as $sessionId) {
            $this->assertNotContains($sessionId, $listed);
        }
    }
}
