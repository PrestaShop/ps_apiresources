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

    /**
     * Sessions are created by logging in, the domain exposes no "add session" command, so this
     * is the one thing these tests cannot get from the API. Everything they assert goes back
     * through the list endpoint.
     *
     * $ageInSeconds moves date_upd into the past: a session older than PS_COOKIE_LIFETIME_FO /
     * PS_COOKIE_LIFETIME_BO (480 hours by default) is what "outdated" means for the core.
     */
    private function seedCustomerSession(int $idCustomer = 1, string $age = 'now'): int
    {
        \Db::getInstance()->insert('customer_session', [
            'id_customer' => $idCustomer,
            'token' => bin2hex(random_bytes(16)),
            'date_add' => date('Y-m-d H:i:s', strtotime($age)),
            'date_upd' => date('Y-m-d H:i:s', strtotime($age)),
        ]);

        return (int) \Db::getInstance()->Insert_ID();
    }

    private function seedEmployeeSession(int $idEmployee = 1, string $age = 'now'): int
    {
        \Db::getInstance()->insert('employee_session', [
            'id_employee' => $idEmployee,
            'token' => bin2hex(random_bytes(16)),
            'date_add' => date('Y-m-d H:i:s', strtotime($age)),
            'date_upd' => date('Y-m-d H:i:s', strtotime($age)),
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
        yield 'customer clear outdated endpoint' => ['DELETE', '/customer-sessions/bulk-clear-outdated'];
        yield 'employee clear outdated endpoint' => ['DELETE', '/employee-sessions/bulk-clear-outdated'];
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
        $this->assertEquals(['sessionId', 'customerId', 'firstName', 'lastName', 'email', 'dateUpd'], array_keys($first));
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
        $this->assertEquals(['sessionId', 'employeeId', 'firstName', 'lastName', 'email', 'dateUpd'], array_keys($first));
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

    public function testClearOutdatedCustomerSessions(): void
    {
        $outdatedId = $this->seedCustomerSession(1, '-1 year');
        $currentId = $this->seedCustomerSession(1);

        $listUrl = '/customer-sessions?orderBy=sessionId&sortOrder=desc';
        $this->assertContains($outdatedId, $this->listedIds($listUrl, ['customer_session_read']));

        $this->requestApi(
            'DELETE',
            '/customer-sessions/bulk-clear-outdated',
            null,
            ['customer_session_write'],
            Response::HTTP_NO_CONTENT
        );

        // Only the outdated one is cleared, the current session survives
        $listed = $this->listedIds($listUrl, ['customer_session_read']);
        $this->assertNotContains($outdatedId, $listed);
        $this->assertContains($currentId, $listed);
    }

    public function testClearOutdatedEmployeeSessions(): void
    {
        $outdatedId = $this->seedEmployeeSession(1, '-1 year');
        $currentId = $this->seedEmployeeSession(1);

        $listUrl = '/employee-sessions?orderBy=sessionId&sortOrder=desc';
        $this->assertContains($outdatedId, $this->listedIds($listUrl, ['employee_session_read']));

        $this->requestApi(
            'DELETE',
            '/employee-sessions/bulk-clear-outdated',
            null,
            ['employee_session_write'],
            Response::HTTP_NO_CONTENT
        );

        $listed = $this->listedIds($listUrl, ['employee_session_read']);
        $this->assertNotContains($outdatedId, $listed);
        $this->assertContains($currentId, $listed);
    }

    public static function getSessionTypes(): iterable
    {
        yield 'customer' => ['customer'];
        yield 'employee' => ['employee'];
    }

    private function seedSession(string $type, string $age = 'now'): int
    {
        return 'customer' === $type ? $this->seedCustomerSession(1, $age) : $this->seedEmployeeSession(1, $age);
    }

    /**
     * @dataProvider getSessionTypes
     */
    public function testListSessionsFilteredByOwner(string $type): void
    {
        $sessionId = $this->seedSession($type);
        $scopes = [$type . '_session_read'];

        $this->assertContains(
            $sessionId,
            array_column($this->listItems('/' . $type . '-sessions', $scopes, [$type . 'Id' => 1])['items'], 'sessionId')
        );
        $this->assertSame(0, $this->countItems('/' . $type . '-sessions', $scopes, [$type . 'Id' => 99999]));
    }

    /**
     * @dataProvider getSessionTypes
     */
    public function testInvalidSessionIdsAreRejected(string $type): void
    {
        $sessionId = $this->seedSession($type);
        $scopes = [$type . '_session_write'];

        // The session id value object refuses 0
        $this->deleteItem('/' . $type . '-sessions/0', $scopes, Response::HTTP_UNPROCESSABLE_ENTITY);

        $this->bulkDeleteItems('/' . $type . '-sessions/bulk-delete', ['sessionIds' => []], $scopes, Response::HTTP_UNPROCESSABLE_ENTITY);

        // The command casts each id with (int): "<id>abc" would delete the session <id>
        $response = $this->bulkDeleteItems(
            '/' . $type . '-sessions/bulk-delete',
            ['sessionIds' => [0, $sessionId . 'abc']],
            $scopes,
            Response::HTTP_UNPROCESSABLE_ENTITY
        );
        $this->assertValidationErrors([
            ['propertyPath' => 'sessionIds[0]', 'message' => 'This value should be positive.'],
            ['propertyPath' => 'sessionIds[1]', 'message' => 'This value should be of type integer.'],
        ], $response);

        $this->assertContains($sessionId, $this->listedIds('/' . $type . '-sessions?orderBy=sessionId&sortOrder=desc', [$type . '_session_read']));
    }

    /**
     * The repository deletes the sessions one by one and stops at the first unknown id: the ones before it
     * are deleted, the ones after it are kept.
     *
     * @dataProvider getSessionTypes
     */
    public function testBulkDeleteStopsAtFirstUnknownSession(string $type): void
    {
        $deletedId = $this->seedSession($type);
        $keptId = $this->seedSession($type);

        $this->bulkDeleteItems(
            '/' . $type . '-sessions/bulk-delete',
            ['sessionIds' => [$deletedId, 99999, $keptId]],
            [$type . '_session_write'],
            Response::HTTP_NOT_FOUND
        );

        $listed = $this->listedIds('/' . $type . '-sessions?orderBy=sessionId&sortOrder=desc', [$type . '_session_read']);
        $this->assertNotContains($deletedId, $listed);
        $this->assertContains($keptId, $listed);
    }
}
