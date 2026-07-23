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
        yield 'create endpoint' => ['POST', '/sql-requests'];
        yield 'get endpoint' => ['GET', '/sql-requests/1'];
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
        $this->assertEquals(['sqlRequestId' => $sqlRequestId], $sqlRequest);

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
}
