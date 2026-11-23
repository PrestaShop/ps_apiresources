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
        // An operation filtered by its minVersion answers 404, not 401
        if (self::isVersionAtLeast('9.3.0')) {
            yield 'update endpoint' => ['PATCH', '/sql-requests/1'];
            yield 'delete endpoint' => ['DELETE', '/sql-requests/1'];
        }
        yield 'bulk delete endpoint' => ['DELETE', '/sql-requests/bulk-delete'];
    }

    private function expectedSqlRequest(int $sqlRequestId, array $overrides = []): array
    {
        return array_replace([
            'sqlRequestId' => $sqlRequestId,
            'name' => 'My SQL Request',
            'sql' => $this->validSql(),
        ], $overrides);
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
        $this->assertEquals($this->expectedSqlRequest($sqlRequestId), $sqlRequest);

        return $sqlRequestId;
    }

    /**
     * @depends testAddSqlRequest
     */
    public function testGetSqlRequest(int $sqlRequestId): int
    {
        $sqlRequest = $this->getItem('/sql-requests/' . $sqlRequestId, ['sql_management_read']);
        $this->assertEquals($this->expectedSqlRequest($sqlRequestId), $sqlRequest);

        return $sqlRequestId;
    }

    /**
     * EditSqlRequestCommand::__construct() only accepts a SqlRequestId value object up to PrestaShop 9.2, so the
     * command cannot be built from the scalar id of the URI: the PATCH and the DELETE need PrestaShop/PrestaShop#41742,
     * which targets 9.3.0. The develop leg of the CI stays red on these two tests until it is merged.
     *
     * @depends testGetSqlRequest
     */
    public function testEditSqlRequest(int $sqlRequestId): int
    {
        $this->markTestSkippedByMinVersion('9.3.0');

        $expected = $this->expectedSqlRequest($sqlRequestId, ['name' => 'My SQL Request Updated']);
        $this->assertEquals($expected, $this->partialUpdateItem('/sql-requests/' . $sqlRequestId, [
            'name' => 'My SQL Request Updated',
        ], ['sql_management_write']));
        $this->assertEquals($expected, $this->getItem('/sql-requests/' . $sqlRequestId, ['sql_management_read']));

        // An empty value is refused field by field, and nothing changes
        $response = $this->partialUpdateItem('/sql-requests/' . $sqlRequestId, [
            'name' => '',
            'sql' => '',
        ], ['sql_management_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertValidationErrors([
            ['propertyPath' => 'name', 'message' => 'This value should not be blank.'],
            ['propertyPath' => 'sql', 'message' => 'This value should not be blank.'],
        ], $response);
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

    public function testInvalidBulkDeleteSqlRequests(): void
    {
        // The command casts nothing: a non integer id reached the SqlRequestId value object and answered 500
        $response = $this->bulkDeleteItems('/sql-requests/bulk-delete', ['sqlRequestIds' => ['99999abc']], ['sql_management_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertValidationErrors([
            ['propertyPath' => 'sqlRequestIds[0]', 'message' => 'This value should be of type integer.'],
        ], $response);

        $response = $this->bulkDeleteItems('/sql-requests/bulk-delete', ['sqlRequestIds' => []], ['sql_management_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertValidationErrors([
            ['propertyPath' => 'sqlRequestIds', 'message' => 'This value should not be blank.'],
        ], $response);
    }

    public function testInvalidSqlRequest(): void
    {
        $response = $this->createItem('/sql-requests', [
            'name' => '',
            'sql' => '',
        ], ['sql_management_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertValidationErrors([
            ['propertyPath' => 'name', 'message' => 'This value should not be blank.'],
            ['propertyPath' => 'sql', 'message' => 'This value should not be blank.'],
        ], $response);

        // The core validator refuses anything but a single SELECT on existing tables (no field list)
        $this->createItem('/sql-requests', [
            'name' => 'Not a select',
            'sql' => 'DELETE FROM ' . _DB_PREFIX_ . 'shop',
        ], ['sql_management_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testGetNonExistentSqlRequest(): void
    {
        $this->getItem('/sql-requests/999999', ['sql_management_read'], Response::HTTP_NOT_FOUND);
        // SqlRequestId refuses 0
        $this->getItem('/sql-requests/0', ['sql_management_read'], Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}
