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

class HookEndpointTest extends ApiTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        DatabaseDump::restoreTables(['hook']);

        // Pre-create the API Client with the needed scopes, this way we reduce the number of created API Clients
        self::createApiClient(['hook_read', 'hook_write']);
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        // Reset DB as it was before this test
        DatabaseDump::restoreTables(['hook']);
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'get endpoint' => [
            'GET',
            '/hook/1',
        ];

        yield 'put endpoint' => [
            'PUT',
            '/hook/1/status',
        ];

        yield 'list endpoint' => [
            'GET',
            '/hooks',
        ];
    }

    public function testGetHook(): int
    {
        // Create a hook that will be used for tests (because we don't have a POST endpoint so far)
        $testHook = new \Hook();
        $testHook->name = 'testHook';
        $testHook->title = 'Test Hook';
        $testHook->description = 'Hook created only for API test';
        $testHook->active = true;
        $testHook->add();
        $hookId = (int) $testHook->id;

        $hook = $this->getItem('/hook/' . $hookId, ['hook_read']);
        $this->assertEquals([
            'hookId' => $hookId,
            'name' => 'testHook',
            'title' => 'Test Hook',
            'description' => 'Hook created only for API test',
            'active' => true,
        ], $hook);

        return $hookId;
    }

    /**
     * @depends testGetHook
     */
    public function testUpdateHookStatus(int $hookId): int
    {
        $updatedHook = $this->updateItem('/hook/' . $hookId . '/status', ['active' => false], ['hook_write']);
        $expectedHook = [
            'hookId' => $hookId,
            'name' => 'testHook',
            'title' => 'Test Hook',
            'description' => 'Hook created only for API test',
            'active' => false,
        ];
        $this->assertEquals($expectedHook, $updatedHook);
        $this->assertEquals($expectedHook, $this->getItem('/hook/' . $hookId, ['hook_read']));

        $updatedHook = $this->updateItem('/hook/' . $hookId . '/status', ['active' => true], ['hook_write']);
        $expectedHook['active'] = true;
        $this->assertEquals($expectedHook, $updatedHook);
        $this->assertEquals($expectedHook, $this->getItem('/hook/' . $hookId, ['hook_read']));

        return $hookId;
    }

    /**
     * @depends testUpdateHookStatus
     */
    public function testListHooks(int $hookId): void
    {
        // List with most recent to get the test hook created last
        $hooks = $this->listItems('/hooks?orderBy=hookId&sortOrder=desc', ['hook_read']);

        $testHook = null;
        foreach ($hooks['items'] as $hook) {
            if ($hook['hookId'] === $hookId) {
                $testHook = $hook;
                break;
            }
        }
        $this->assertNotNull($testHook);
        $this->assertEquals([
            'hookId' => $hookId,
            'name' => 'testHook',
            'title' => 'Test Hook',
            'description' => 'Hook created only for API test',
            'active' => true,
        ], $testHook);
    }
}
