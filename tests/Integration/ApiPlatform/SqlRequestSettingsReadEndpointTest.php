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

class SqlRequestSettingsReadEndpointTest extends ApiTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::createApiClient(['sql_management_read']);
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'get sql request settings endpoint' => ['GET', '/sql-request-settings'];
    }

    public function testGetSqlRequestSettings(): void
    {
        $settings = $this->getItem('/sql-request-settings', ['sql_management_read']);

        $this->assertArrayHasKey('fileEncoding', $settings);
        $this->assertArrayHasKey('fileSeparator', $settings);
    }
}
