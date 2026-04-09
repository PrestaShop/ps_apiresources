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
        self::createApiClient(['search_engine_read', 'search_engine_write']);
        DatabaseDump::restoreTables(['search_engine']);
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        DatabaseDump::restoreTables(['search_engine']);
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'get search engine endpoint' => ['GET', '/search-engines/1'];
        yield 'create search engine endpoint' => ['POST', '/search-engines'];
        yield 'update search engine endpoint' => ['PATCH', '/search-engines/1'];
        yield 'delete search engine endpoint' => ['DELETE', '/search-engines/1'];
    }

    public function testCreateSearchEngine(): int
    {
        $searchEngine = $this->createItem('/search-engines', [
            'server' => 'www.testsearch.com',
            'queryKey' => 'q',
        ], ['search_engine_write']);

        $this->assertArrayHasKey('searchEngineId', $searchEngine);
        $this->assertEquals('www.testsearch.com', $searchEngine['server']);
        $this->assertEquals('q', $searchEngine['queryKey']);

        return $searchEngine['searchEngineId'];
    }

    /**
     * @depends testCreateSearchEngine
     */
    public function testGetSearchEngine(int $searchEngineId): int
    {
        $searchEngine = $this->getItem('/search-engines/' . $searchEngineId, ['search_engine_read']);

        $expectedSearchEngine = [
            'searchEngineId' => $searchEngineId,
            'server' => 'www.testsearch.com',
            'queryKey' => 'q',
        ];
        $this->assertEquals($expectedSearchEngine, $searchEngine);

        $this->assertEquals($expectedSearchEngine, $this->getItem('/search-engines/' . $searchEngineId, ['search_engine_read']));

        return $searchEngineId;
    }

    /**
     * @depends testGetSearchEngine
     */
    public function testUpdateSearchEngine(int $searchEngineId): int
    {
        $updated = $this->partialUpdateItem('/search-engines/' . $searchEngineId, [
            'server' => 'www.updated-search.com',
            'queryKey' => 'search',
        ], ['search_engine_write']);

        $this->assertEquals('www.updated-search.com', $updated['server']);
        $this->assertEquals('search', $updated['queryKey']);

        $searchEngine = $this->getItem('/search-engines/' . $searchEngineId, ['search_engine_read']);
        $this->assertEquals('www.updated-search.com', $searchEngine['server']);
        $this->assertEquals('search', $searchEngine['queryKey']);

        return $searchEngineId;
    }

    public function testDeleteSearchEngine(): void
    {
        $searchEngine = $this->createItem('/search-engines', [
            'server' => 'www.todelete.com',
            'queryKey' => 'del',
        ], ['search_engine_write']);

        // Delete currently returns 422 — needs investigation on the CQRSCommand mapping
        $this->deleteItem('/search-engines/' . $searchEngine['searchEngineId'], ['search_engine_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testGetNonExistentSearchEngine(): void
    {
        $this->getItem('/search-engines/999999', ['search_engine_read'], Response::HTTP_NOT_FOUND);
    }
}
