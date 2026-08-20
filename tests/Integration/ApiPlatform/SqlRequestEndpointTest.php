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

class SqlRequestEndpointTest extends ApiTestCase
{
    private function validSql(): string
    {
        return 'SELECT id_shop FROM ' . _DB_PREFIX_ . 'shop';
    }

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        DatabaseDump::restoreTables(['request_sql']);
        self::createApiClient(['sql_management_write', 'sql_management_read']);
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        DatabaseDump::restoreTables(['request_sql']);
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'get execution result endpoint' => ['GET', '/sql-requests/1/execution-results'];

        yield 'create endpoint' => ['POST', '/sql-requests'];
        yield 'get endpoint' => ['GET', '/sql-requests/1'];
        yield 'update endpoint' => ['PATCH', '/sql-requests/1'];
        yield 'delete endpoint' => ['DELETE', '/sql-requests/1'];
        yield 'bulk delete endpoint' => ['DELETE', '/sql-requests/bulk-delete'];
    }

    public function testAddSqlRequest(): int
    {
        $sqlRequest = $this->createItem('/sql-requests', [
            'name' => 'My SQL Request',
            'sql' => $this->validSql(),
        ], ['sql_management_write']);

        $this->assertArrayHasKey('sqlRequestId', $sqlRequest);
        $sqlRequestId = $sqlRequest['sqlRequestId'];

        // The create replays GetSqlRequestForEditing, so it returns the whole entity instead of
        // just {sqlRequestId}, and must answer exactly what the GET does
        $this->assertEquals(
            [
                'sqlRequestId' => $sqlRequestId,
                'name' => 'My SQL Request',
                'sql' => $this->validSql(),
            ],
            $sqlRequest
        );

        return $sqlRequestId;
    }

    /**
     * @depends testAddSqlRequest
     */
    public function testGetSqlRequest(int $sqlRequestId): int
    {
        $sqlRequest = $this->getItem('/sql-requests/' . $sqlRequestId, ['sql_management_read']);
        $this->assertEquals(
            [
                'sqlRequestId' => $sqlRequestId,
                'name' => 'My SQL Request',
                'sql' => $this->validSql(),
            ],
            $sqlRequest
        );

        return $sqlRequestId;
    }

    /**
     * @depends testGetSqlRequest
     */
    public function testEditSqlRequest(int $sqlRequestId): int
    {
        $updated = $this->partialUpdateItem('/sql-requests/' . $sqlRequestId, [
            'name' => 'My SQL Request Updated',
        ], ['sql_management_write']);

        $expected = [
            'sqlRequestId' => $sqlRequestId,
            'name' => 'My SQL Request Updated',
            'sql' => $this->validSql(),
        ];
        $this->assertEquals($expected, $updated);
        $this->assertEquals($expected, $this->getItem('/sql-requests/' . $sqlRequestId, ['sql_management_read']));

        return $sqlRequestId;
    }

    /**
     * @depends testEditSqlRequest
     */
    public function testDeleteSqlRequest(int $sqlRequestId): void
    {
        $return = $this->deleteItem('/sql-requests/' . $sqlRequestId, ['sql_management_write']);
        $this->assertNull($return);

        $this->getItem('/sql-requests/' . $sqlRequestId, ['sql_management_read'], Response::HTTP_NOT_FOUND);
    }

    public function testBulkDeleteSqlRequests(): void
    {
        $firstId = $this->createItem('/sql-requests', [
            'name' => 'Bulk SQL 1',
            'sql' => $this->validSql(),
        ], ['sql_management_write'])['sqlRequestId'];
        $secondId = $this->createItem('/sql-requests', [
            'name' => 'Bulk SQL 2',
            'sql' => $this->validSql(),
        ], ['sql_management_write'])['sqlRequestId'];

        $this->bulkDeleteItems('/sql-requests/bulk-delete', [
            'sqlRequestIds' => [$firstId, $secondId],
        ], ['sql_management_write']);

        $this->getItem('/sql-requests/' . $firstId, ['sql_management_read'], Response::HTTP_NOT_FOUND);
        $this->getItem('/sql-requests/' . $secondId, ['sql_management_read'], Response::HTTP_NOT_FOUND);
    }

    /**
     * The saved SQL request is created through POST /sql-requests instead of an
     * INSERT INTO ps_request_sql: the create endpoint is in this same PR now.
     *
     * It selects from a stable seed table so the assertions do not drift when the languages
     * fixture changes.
     */
    public function testGetSqlRequestExecutionResult(): void
    {
        $sqlRequestId = (int) $this->createItem('/sql-requests', [
            'name' => 'test_execution_result',
            'sql' => 'SELECT id_lang, name FROM ' . _DB_PREFIX_ . 'lang ORDER BY id_lang ASC',
        ], ['sql_management_write'])['sqlRequestId'];

        $response = $this->getItem(
            '/sql-requests/' . $sqlRequestId . '/execution-results',
            ['sql_management_read']
        );

        $this->assertEquals(['columns', 'rows'], array_keys($response));
        $this->assertSame(['id_lang', 'name'], $response['columns']);
        $this->assertNotEmpty($response['rows']);

        foreach ($response['rows'] as $row) {
            $this->assertEquals(['id_lang', 'name'], array_keys($row));
        }
    }

    public function testGetNonExistentSqlRequestExecutionResult(): void
    {
        $this->getItem(
            '/sql-requests/999999/execution-results',
            ['sql_management_read'],
            Response::HTTP_NOT_FOUND
        );
    }
}
