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

class WebserviceKeyDeleteEndpointTest extends ApiTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::createApiClient(['webservice_key_write']);
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        DatabaseDump::restoreTables(['webservice_account', 'webservice_account_shop', 'webservice_permission']);
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'delete endpoint' => ['DELETE', '/webservice-keys/999999'];
        yield 'bulk delete endpoint' => ['DELETE', '/webservice-keys/bulk-delete'];
    }

    public function testDeleteWebserviceKey(): void
    {
        $seededId = $this->seedWebserviceKey('a' . str_repeat('0', 31));

        $this->requestApi(
            'DELETE',
            '/webservice-keys/' . $seededId,
            null,
            ['webservice_key_write'],
            Response::HTTP_NO_CONTENT
        );

        $stillThere = (int) \Db::getInstance()->getValue(
            'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'webservice_account` WHERE `id_webservice_account` = ' . $seededId
        );
        $this->assertSame(0, $stillThere);
    }

    public function testBulkDeleteWebserviceKeys(): void
    {
        $id1 = $this->seedWebserviceKey('b' . str_repeat('0', 31));
        $id2 = $this->seedWebserviceKey('c' . str_repeat('0', 31));

        $this->requestApi(
            'DELETE',
            '/webservice-keys/bulk-delete',
            ['webserviceKeyIds' => [$id1, $id2]],
            ['webservice_key_write'],
            Response::HTTP_NO_CONTENT
        );

        $stillThere = (int) \Db::getInstance()->getValue(
            'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'webservice_account` WHERE `id_webservice_account` IN (' . $id1 . ', ' . $id2 . ')'
        );
        $this->assertSame(0, $stillThere);
    }

    private function seedWebserviceKey(string $key): int
    {
        \Db::getInstance()->insert('webservice_account', [
            'key' => $key,
            'active' => 1,
            'description' => 'seed for delete test',
            'class_name' => 'WebserviceRequest',
        ]);

        return (int) \Db::getInstance()->Insert_ID();
    }
}
