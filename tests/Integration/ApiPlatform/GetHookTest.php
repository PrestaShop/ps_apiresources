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

class GetHookTest extends ApiTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        DatabaseDump::restoreTables(['hook']);
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        DatabaseDump::restoreTables(['hook']);
    }

    public function getProtectedEndpoints(): iterable
    {
        yield 'get endpoint' => [
            'GET',
            '/hooks/1',
        ];
    }

    public function testGetHook(): void
    {
        $hook = new \Hook();
        $hook->name = 'testHook';
        $hook->active = true;
        $hook->add();

        $bearerToken = $this->getBearerToken([
            'hook_read',
            'hook_write',
        ]);

        $response = static::createClient()->request('GET', '/hooks/' . (int) $hook->id, ['auth_bearer' => $bearerToken]);
        self::assertEquals(json_decode($response->getContent())->active, $hook->active);
        self::assertResponseStatusCodeSame(200);

        static::createClient()->request('GET', '/hooks/' . 9999, ['auth_bearer' => $bearerToken]);
        self::assertResponseStatusCodeSame(404);

        static::createClient()->request('GET', '/hooks/' . $hook->id);
        self::assertResponseStatusCodeSame(401);

        $hook->delete();
    }

    public function testListHooks(): void
    {
        $hooks = $this->generateHooks();
        $bearerToken = $this->getBearerToken([
            'hook_read',
            'hook_write',
        ]);

        $response = static::createClient()->request('GET', '/hooks', ['auth_bearer' => $bearerToken]);
        self::assertResponseStatusCodeSame(200);
        self::assertCount(50, json_decode($response->getContent())->items);
        $totalItems = json_decode($response->getContent())->totalItems;

        $response = static::createClient()->request('GET', '/hooks?limit=10', ['auth_bearer' => $bearerToken]);
        self::assertResponseStatusCodeSame(200);
        self::assertCount(10, json_decode($response->getContent())->items);

        $response = static::createClient()->request('GET', '/hooks?limit=1&orderBy=id_hook&sortOrder=desc', ['auth_bearer' => $bearerToken]);
        self::assertResponseStatusCodeSame(200);
        self::assertCount(1, json_decode($response->getContent())->items);
        $returnedHook = json_decode($response->getContent());
        self::assertEquals('id_hook', $returnedHook->orderBy);
        self::assertEquals('desc', $returnedHook->sortOrder);
        self::assertEquals(1, $returnedHook->limit);
        self::assertEquals([], $returnedHook->filters);
        self::assertEquals('testHook50', $returnedHook->items[0]->name);
        self::assertTrue($returnedHook->items[0]->active);

        $response = static::createClient()->request('GET', '/hooks?filters[name]=testHook', ['auth_bearer' => $bearerToken]);
        self::assertResponseStatusCodeSame(200);
        self::assertCount(50, json_decode($response->getContent())->items);
        foreach (json_decode($response->getContent())->items as $key => $item) {
            self::assertEquals('testHook' . $key, $item->name);
        }

        $newHook = new \Hook();
        $newHook->name = 'testHook51';
        $newHook->active = true;
        $newHook->add();
        $hooks[] = $newHook;

        $response = static::createClient()->request('GET', '/hooks', ['auth_bearer' => $bearerToken]);
        self::assertResponseStatusCodeSame(200);
        self::assertEquals($totalItems + 1, json_decode($response->getContent())->totalItems);

        static::createClient()->request('GET', '/hooks');
        self::assertResponseStatusCodeSame(401);

        foreach ($hooks as $hook) {
            $hook->delete();
        }
    }

    /**
     * @return \Hook[]
     */
    protected function generateHooks(): array
    {
        $hooks = [];
        for ($i = 0; $i <= 50; ++$i) {
            $hook = new \Hook();
            $hook->name = 'testHook' . $i;
            $hook->active = true;
            $hook->add();
            $hooks[] = $hook;
        }

        return $hooks;
    }
}
