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

use Db;
use Tests\Resources\DatabaseDump;

class TabEndpointTest extends ApiTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        // Pre-create the API Client with the needed scopes, this way we reduce the number of created API Clients
        self::createApiClient(['tab_write']);
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        // Reset DB as it was before this test
        DatabaseDump::restoreTables(['tab', 'tab_lang', 'tab_module_preference']);
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'update endpoint' => [
            'PUT',
            '/tab/string',
        ];
    }

    public function testUpdateTab(): void
    {
        $className = 'AdminShopGroup';

        self::assertEquals(0, $this->isActiveTabClassName($className));
        $this->updateItem('/tab/' . $className, [
            'enabled' => true,
        ], ['tab_write']);
        self::assertEquals(1, $this->isActiveTabClassName($className));
    }

    private function isActiveTabClassName(string $className): int
    {
        return \Db::getInstance()->getValue('SELECT active FROM `' . _DB_PREFIX_ . 'tab` WHERE class_name="' . pSQL($className) . '"');
    }
}
