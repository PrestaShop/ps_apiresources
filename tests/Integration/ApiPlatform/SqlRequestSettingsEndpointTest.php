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

class SqlRequestSettingsEndpointTest extends ApiTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::createApiClient(['sql_management_read', 'sql_management_write']);
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        DatabaseDump::restoreTables(['configuration']);
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'get sql request settings endpoint' => ['GET', '/sql-request-settings'];
        yield 'save sql request settings endpoint' => ['PUT', '/sql-request-settings'];
    }

    /**
     * The settings are a singleton resource, so the initial GET is the only fixture this test
     * needs: everything else is built from the API itself.
     */
    public function testGetSqlRequestSettings(): array
    {
        $settings = $this->getItem('/sql-request-settings', ['sql_management_read']);

        $this->assertEquals(['fileEncoding', 'fileSeparator'], array_keys($settings));

        return $settings;
    }

    /**
     * @depends testGetSqlRequestSettings
     */
    public function testSaveSqlRequestSettings(array $initialSettings): array
    {
        $updatedSettings = [
            'fileEncoding' => 'iso-8859-1',
            'fileSeparator' => ',',
        ];
        $this->assertNotEquals($initialSettings, $updatedSettings);

        // The update returns the updated settings, built by replaying the GET query. The encoding
        // is stored as an int in the configuration and mapped back to its string value on read,
        // so this also pins the round-trip.
        $response = $this->updateItem(
            '/sql-request-settings',
            $updatedSettings,
            ['sql_management_write'],
            Response::HTTP_OK
        );

        $this->assertEquals($updatedSettings, $response);

        return $updatedSettings;
    }

    /**
     * @depends testSaveSqlRequestSettings
     */
    public function testGetUpdatedSqlRequestSettings(array $updatedSettings): void
    {
        $settings = $this->getItem('/sql-request-settings', ['sql_management_read']);

        $this->assertEquals($updatedSettings, $settings);
    }

    /**
     * @depends testSaveSqlRequestSettings
     */
    public function testSaveUnsupportedFileEncodingIsRejected(array $updatedSettings): void
    {
        $this->updateItem(
            '/sql-request-settings',
            ['fileEncoding' => 'utf-32', 'fileSeparator' => ','],
            ['sql_management_write'],
            Response::HTTP_UNPROCESSABLE_ENTITY
        );

        // The rejected update must not have changed anything
        $this->assertEquals($updatedSettings, $this->getItem('/sql-request-settings', ['sql_management_read']));
    }
}
