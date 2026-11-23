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

class DatabaseTablesEndpointTest extends ApiTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::createApiClient(['sql_management_read']);
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'get database tables endpoint' => ['GET', '/sql-requests/database-tables'];
        yield 'get database table fields endpoint' => ['GET', '/sql-requests/database-tables/' . _DB_PREFIX_ . 'product/fields'];
    }

    public function testGetDatabaseTables(): void
    {
        $result = $this->getItem('/sql-requests/database-tables', ['sql_management_read']);

        $this->assertEquals(['tables'], array_keys($result));
        $this->assertContainsOnly('string', $result['tables']);
        $this->assertContains(_DB_PREFIX_ . 'request_sql', $result['tables']);
    }

    public function testGetDatabaseTableFields(): void
    {
        $tableName = _DB_PREFIX_ . 'request_sql';
        $result = $this->getItem('/sql-requests/database-tables/' . $tableName . '/fields', ['sql_management_read']);

        // The column types are those MySQL reports (int(11) or int depending on the server version): their format is
        // asserted, then copied into the expected payload
        $this->assertEquals(['tableName', 'fields'], array_keys($result));
        $this->assertCount(3, $result['fields']);
        foreach ($result['fields'] as $field) {
            $this->assertIsString($field['type']);
        }
        $this->assertEquals(
            [
                'tableName' => $tableName,
                'fields' => [
                    ['name' => 'id_request_sql', 'type' => $result['fields'][0]['type']],
                    ['name' => 'name', 'type' => $result['fields'][1]['type']],
                    ['name' => 'sql', 'type' => $result['fields'][2]['type']],
                ],
            ],
            $result
        );
        $this->assertStringStartsWith('int', $result['fields'][0]['type']);
        $this->assertSame('varchar(200)', $result['fields'][1]['type']);
        $this->assertSame('mediumtext', $result['fields'][2]['type']);
    }

    /*
     * No test for an unknown table: GetDatabaseTableFieldsListHandler runs DESCRIBE without checking that the table
     * exists, so the database exception escapes as a 500. The check belongs to the core handler (a not-found
     * exception this resource would map to 404).
     */
}
