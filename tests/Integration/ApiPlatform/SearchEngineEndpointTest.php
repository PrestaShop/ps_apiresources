<?php

/**
 * For the full copyright and license information, please view the
 * docs/licenses/LICENSE.txt file that was distributed with this source code.
 */

declare(strict_types=1);

namespace PsApiResourcesTest\Integration\ApiPlatform;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Tests\Resources\DatabaseDump;

class SearchEngineEndpointTest extends ApiTestCase
{
    private const SEARCH_ENGINE_ID = 100;
    private const SECOND_SEARCH_ENGINE_ID = 101;

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
        yield 'delete endpoint' => [
            'DELETE',
            '/search-engines/1',
        ];

        yield 'bulk delete endpoint' => [
            'DELETE',
            '/search-engines/bulk-delete',
        ];
    }

    public function testDeleteSearchEngine(): void
    {
        $this->insertSearchEngineInDatabase(self::SEARCH_ENGINE_ID, 'Google', 'q');
        $this->assertSearchEngineExistsInDatabase(self::SEARCH_ENGINE_ID);

        $this->requestApi(
            Request::METHOD_DELETE,
            '/search-engines/' . self::SEARCH_ENGINE_ID,
            [],
            ['search_engine_write'],
            Response::HTTP_NO_CONTENT
        );

        $this->assertSearchEngineDoesNotExistInDatabase(self::SEARCH_ENGINE_ID);
    }

    public function testBulkDeleteSearchEngines(): void
    {
        $ids = [self::SEARCH_ENGINE_ID, self::SECOND_SEARCH_ENGINE_ID];
        foreach ($ids as $id) {
            $this->insertSearchEngineInDatabase($id, "Engine $id", 'v');
        }

        $bulkDeleteData = [
            'searchEngineIds' => $ids,
        ];

        $this->requestApi(
            Request::METHOD_DELETE,
            '/search-engines/bulk-delete',
            $bulkDeleteData,
            ['search_engine_write'],
            Response::HTTP_NO_CONTENT
        );

        foreach ($ids as $id) {
            $this->assertSearchEngineDoesNotExistInDatabase($id);
        }
    }

    public function testDeleteNonExistentSearchEngine(): void
    {
        $this->requestApi(
            Request::METHOD_DELETE,
            '/search-engines/9999',
            [],
            ['search_engine_write'],
            Response::HTTP_NOT_FOUND
        );
    }

    public function testDeleteSearchEngineWithInvalidIdFormat(): void
    {
        $this->requestApi(
            Request::METHOD_DELETE,
            '/search-engines/abc',
            [],
            ['search_engine_write'],
            Response::HTTP_NOT_FOUND
        );
    }

    private function insertSearchEngineInDatabase(int $id, string $server, string $getvar): void
    {
        $connection = static::getContainer()->get('doctrine.dbal.default_connection');
        $connection->executeStatement(
            'INSERT INTO ' . _DB_PREFIX_ . 'search_engine (id_search_engine, server, getvar) VALUES (?, ?, ?)',
            [$id, $server, $getvar]
        );
    }

    private function assertSearchEngineExistsInDatabase(int $id): void
    {
        $connection = static::getContainer()->get('doctrine.dbal.default_connection');
        $result = $connection->fetchOne(
            'SELECT COUNT(*) FROM ' . _DB_PREFIX_ . 'search_engine WHERE id_search_engine = ?',
            [$id]
        );
        $this->assertEquals(1, (int) $result);
    }

    private function assertSearchEngineDoesNotExistInDatabase(int $id): void
    {
        $connection = static::getContainer()->get('doctrine.dbal.default_connection');
        $result = $connection->fetchOne(
            'SELECT COUNT(*) FROM ' . _DB_PREFIX_ . 'search_engine WHERE id_search_engine = ?',
            [$id]
        );
        $this->assertEquals(0, (int) $result);
    }
}
