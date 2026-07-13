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

class QuickAccessEndpointTest extends ApiTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        DatabaseDump::restoreTables(['quick_access', 'quick_access_lang']);
        self::createApiClient(['quick_access_write', 'quick_access_read']);
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        DatabaseDump::restoreTables(['quick_access', 'quick_access_lang']);
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'create endpoint' => ['POST', '/quick-accesses'];
        yield 'get endpoint' => ['GET', '/quick-accesses/1'];
        yield 'update endpoint' => ['PATCH', '/quick-accesses/1'];
        yield 'delete endpoint' => ['DELETE', '/quick-accesses/1'];
        yield 'bulk delete endpoint' => ['DELETE', '/quick-accesses/bulk-delete'];
        yield 'toggle new window endpoint' => ['PUT', '/quick-accesses/1/new-window-toggles'];
    }

    public function testCreateQuickAccess(): int
    {
        $result = $this->requestApi(
            'POST',
            '/quick-accesses',
            [
                'localizedNames' => [1 => 'Test link'],
                'link' => 'index.php?controller=AdminModules',
                'newWindow' => false,
            ],
            ['quick_access_write'],
            Response::HTTP_CREATED
        );

        $this->assertArrayHasKey('quickAccessId', $result);
        $this->assertIsInt($result['quickAccessId']);
        $this->assertGreaterThan(0, $result['quickAccessId']);

        return $result['quickAccessId'];
    }

    /**
     * @depends testCreateQuickAccess
     */
    public function testGetQuickAccess(int $quickAccessId): int
    {
        $result = $this->getItem('/quick-accesses/' . $quickAccessId, ['quick_access_read']);
        $this->assertSame($quickAccessId, $result['quickAccessId']);
        $this->assertArrayHasKey('link', $result);
        $this->assertArrayHasKey('newWindow', $result);
        $this->assertArrayHasKey('localizedNames', $result);

        return $quickAccessId;
    }

    /**
     * @depends testGetQuickAccess
     */
    public function testEditQuickAccess(int $quickAccessId): int
    {
        $this->requestApi(
            'PATCH',
            '/quick-accesses/' . $quickAccessId,
            ['link' => 'index.php?controller=AdminEmployees'],
            ['quick_access_write'],
            Response::HTTP_OK
        );

        $link = (string) \Db::getInstance()->getValue(
            'SELECT `link` FROM `' . _DB_PREFIX_ . 'quick_access` WHERE `id_quick_access` = ' . $quickAccessId
        );
        $this->assertSame('index.php?controller=AdminEmployees', $link);

        return $quickAccessId;
    }

    /**
     * @depends testEditQuickAccess
     */
    public function testToggleNewWindow(int $quickAccessId): int
    {
        $before = (int) \Db::getInstance()->getValue(
            'SELECT `new_window` FROM `' . _DB_PREFIX_ . 'quick_access` WHERE `id_quick_access` = ' . $quickAccessId
        );

        $this->requestApi(
            'PUT',
            '/quick-accesses/' . $quickAccessId . '/new-window-toggles',
            null,
            ['quick_access_write'],
            Response::HTTP_OK
        );

        $after = (int) \Db::getInstance()->getValue(
            'SELECT `new_window` FROM `' . _DB_PREFIX_ . 'quick_access` WHERE `id_quick_access` = ' . $quickAccessId
        );
        $this->assertNotSame($before, $after);

        return $quickAccessId;
    }

    /**
     * @depends testToggleNewWindow
     */
    public function testDeleteQuickAccess(int $quickAccessId): void
    {
        $this->requestApi(
            'DELETE',
            '/quick-accesses/' . $quickAccessId,
            null,
            ['quick_access_write'],
            Response::HTTP_NO_CONTENT
        );

        $stillThere = (int) \Db::getInstance()->getValue(
            'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'quick_access` WHERE `id_quick_access` = ' . $quickAccessId
        );
        $this->assertSame(0, $stillThere);
    }

    public function testBulkDeleteQuickAccesses(): void
    {
        $ids = [];
        foreach (['A', 'B'] as $suffix) {
            $result = $this->requestApi(
                'POST',
                '/quick-accesses',
                [
                    'localizedNames' => [1 => 'Seed ' . $suffix],
                    'link' => 'index.php?controller=AdminModules&seed=' . $suffix,
                    'newWindow' => false,
                ],
                ['quick_access_write'],
                Response::HTTP_CREATED
            );
            $ids[] = $result['quickAccessId'];
        }

        $this->requestApi(
            'DELETE',
            '/quick-accesses/bulk-delete',
            ['quickAccessIds' => $ids],
            ['quick_access_write'],
            Response::HTTP_NO_CONTENT
        );

        $stillThere = (int) \Db::getInstance()->getValue(
            'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'quick_access` WHERE `id_quick_access` IN (' . implode(', ', $ids) . ')'
        );
        $this->assertSame(0, $stillThere);
    }
}
