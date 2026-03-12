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

class SearchEngineEndpointTest extends ApiTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        DatabaseDump::restoreTables(['search_engine']);
        self::createApiClient(['search_engine_write']);
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        DatabaseDump::restoreTables(['search_engine']);
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'create endpoint' => [
            'POST',
            '/search-engines',
        ];
    }

    public function testCreateSearchEngine(): void
    {
        $postData = [
            'server' => 'google.com',
            'queryKey' => 'q',
        ];

        $this->createItem('/search-engines', $postData, ['search_engine_write'], Response::HTTP_CREATED);
        $this->assertSearchEngineExistsInDatabase($postData['server'], $postData['queryKey']);
    }

    public function testCreateSearchEngineWithInvalidData(): void
    {
        $this->createItem('/search-engines', ['server' => '', 'queryKey' => 'q'], ['search_engine_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->createItem('/search-engines', ['server' => 'google', 'queryKey' => ''], ['search_engine_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    private function assertSearchEngineExistsInDatabase(string $server, string $queryKey): void
    {
        $connection = static::getContainer()->get('doctrine.dbal.default_connection');
        $result = $connection->fetchOne(
            'SELECT COUNT(*) FROM ' . _DB_PREFIX_ . 'search_engine WHERE server = ? AND getvar = ?',
            [$server, $queryKey]
        );

        $this->assertEquals(1, (int) $result, "Search engine with server $server and query key $queryKey should exist");
    }
}
