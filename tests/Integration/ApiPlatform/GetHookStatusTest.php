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

class GetHookStatusTest extends ApiTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        DatabaseDump::restoreTables(['hook']);
        self::createApiClient(['hook_write', 'hook_read']);
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        DatabaseDump::restoreTables(['hook']);
    }

    public function getProtectedEndpoints(): iterable
    {
        yield 'put endpoint' => [
            'PUT',
            '/hook-status',
        ];
    }

    public function testDisableHook(): void
    {
        $hook = new \Hook();
        $hook->name = 'disableHook';
        $hook->active = true;
        $hook->add();

        $bearerToken = $this->getBearerToken([
            'hook_read',
            'hook_write',
        ]);
        static::createClient()->request('PUT', '/hook-status', [
            'auth_bearer' => $bearerToken,
            'json' => ['id' => (int) $hook->id, 'active' => false],
        ]);
        self::assertResponseStatusCodeSame(200);

        $response = static::createClient()->request('GET', '/hook/' . (int) $hook->id, ['auth_bearer' => $bearerToken]);
        self::assertEquals(json_decode($response->getContent())->active, false);
        self::assertResponseStatusCodeSame(200);
    }

    public function testEnableHook(): void
    {
        $hook = new \Hook();
        $hook->name = 'enableHook';
        $hook->active = false;
        $hook->add();

        $bearerToken = $this->getBearerToken([
            'hook_read',
            'hook_write',
        ]);
        static::createClient()->request('PUT', '/hook-status', [
            'auth_bearer' => $bearerToken,
            'json' => ['id' => (int) $hook->id, 'active' => true],
        ]);
        self::assertResponseStatusCodeSame(200);

        $response = static::createClient()->request('GET', '/hook/' . (int) $hook->id, ['auth_bearer' => $bearerToken]);
        self::assertEquals(json_decode($response->getContent())->active, true);
        self::assertResponseStatusCodeSame(200);
    }
}
