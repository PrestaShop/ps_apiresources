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
        self::createApiClient(['search_engine_write', 'search_engine_read']);
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        DatabaseDump::restoreTables(['search_engine']);
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'list endpoint' => [
            'GET',
            '/search-engines',
        ];

        yield 'create endpoint' => [
            'POST',
            '/search-engines',
        ];

        yield 'get endpoint' => [
            'GET',
            '/search-engines/1',
        ];

        yield 'update endpoint' => [
            'PATCH',
            '/search-engines/1',
        ];

        yield 'delete endpoint' => [
            'DELETE',
            '/search-engines/1',
        ];

        yield 'bulk delete endpoint' => [
            'DELETE',
            '/search-engines/bulk-delete',
        ];
    }

    public function testAddSearchEngine(): int
    {
        $postData = [
            'server' => 'google',
            'queryKey' => 'q',
        ];

        $searchEngine = $this->createItem('/search-engines', $postData, ['search_engine_write']);
        $this->assertArrayHasKey('searchEngineId', $searchEngine);
        $searchEngineId = $searchEngine['searchEngineId'];

        $expectedData = [
            'searchEngineId' => $searchEngineId,
            'server' => 'google',
            'queryKey' => 'q',
        ];
        $this->assertEquals($expectedData, $searchEngine);

        return $searchEngineId;
    }

    public function testAddSearchEngineValidationErrors(): void
    {
        // Test with missing required fields
        $invalidData = [
            'server' => '',
            'queryKey' => '',
        ];

        $validationErrorsResponse = $this->createItem('/search-engines', $invalidData, ['search_engine_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertIsArray($validationErrorsResponse);
        $this->assertValidationErrors([
            [
                'propertyPath' => 'server',
                'message' => 'This value should not be blank.',
            ],
            [
                'propertyPath' => 'server',
                'message' => 'This value is too short. It should have 1 character or more.',
            ],
            [
                'propertyPath' => 'queryKey',
                'message' => 'This value should not be blank.',
            ],
            [
                'propertyPath' => 'queryKey',
                'message' => 'This value is too short. It should have 1 character or more.',
            ],
        ], $validationErrorsResponse);
    }

    public function testAddSearchEngineServerTooLong(): void
    {
        // Server exceeding 255 characters should fail validation
        $invalidData = [
            'server' => str_repeat('a', 256),
            'queryKey' => 'q',
        ];

        $validationErrorsResponse = $this->createItem('/search-engines', $invalidData, ['search_engine_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertIsArray($validationErrorsResponse);
        $this->assertValidationErrors([
            [
                'propertyPath' => 'server',
                'message' => 'This value is too long. It should have 255 characters or less.',
            ],
        ], $validationErrorsResponse);
    }

    public function testAddSearchEngineQueryKeyTooLong(): void
    {
        // QueryKey exceeding 255 characters should fail validation
        $invalidData = [
            'server' => 'google',
            'queryKey' => str_repeat('a', 256),
        ];

        $validationErrorsResponse = $this->createItem('/search-engines', $invalidData, ['search_engine_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertIsArray($validationErrorsResponse);
        $this->assertValidationErrors([
            [
                'propertyPath' => 'queryKey',
                'message' => 'This value is too long. It should have 255 characters or less.',
            ],
        ], $validationErrorsResponse);
    }

    /**
     * @depends testAddSearchEngine
     */
    public function testGetSearchEngine(int $searchEngineId): void
    {
        $searchEngine = $this->getItem('/search-engines/' . $searchEngineId, ['search_engine_read']);

        $this->assertArrayHasKey('searchEngineId', $searchEngine);
        $this->assertEquals($searchEngineId, $searchEngine['searchEngineId']);
        $this->assertArrayHasKey('server', $searchEngine);
        $this->assertArrayHasKey('queryKey', $searchEngine);
        $this->assertEquals('google', $searchEngine['server']);
        $this->assertEquals('q', $searchEngine['queryKey']);
    }

    public function testGetSearchEngineNotFound(): void
    {
        $this->requestApi('GET', '/search-engines/99999', null, ['search_engine_read'], Response::HTTP_NOT_FOUND);
    }

    /**
     * @depends testAddSearchEngine
     */
    public function testPartialUpdateSearchEngine(int $searchEngineId): int
    {
        // Update only server
        $updatedSearchEngine = $this->partialUpdateItem('/search-engines/' . $searchEngineId, [
            'server' => 'bing',
        ], ['search_engine_write']);

        $this->assertEquals($searchEngineId, $updatedSearchEngine['searchEngineId']);
        $this->assertEquals('bing', $updatedSearchEngine['server']);
        $this->assertEquals('q', $updatedSearchEngine['queryKey']);

        return $searchEngineId;
    }

    /**
     * @depends testPartialUpdateSearchEngine
     */
    public function testPartialUpdateSearchEngineMultipleFields(int $searchEngineId): int
    {
        $updatedSearchEngine = $this->partialUpdateItem('/search-engines/' . $searchEngineId, [
            'server' => 'duckduckgo',
            'queryKey' => 'query',
        ], ['search_engine_write']);

        $this->assertEquals($searchEngineId, $updatedSearchEngine['searchEngineId']);
        $this->assertEquals('duckduckgo', $updatedSearchEngine['server']);
        $this->assertEquals('query', $updatedSearchEngine['queryKey']);

        return $searchEngineId;
    }

    public function testPartialUpdateSearchEngineNotFound(): void
    {
        $this->partialUpdateItem('/search-engines/99999', [
            'server' => 'test',
        ], ['search_engine_write'], Response::HTTP_NOT_FOUND);
    }

    public function testPartialUpdateSearchEngineValidationErrors(): void
    {
        // Create a search engine first
        $searchEngine = $this->createItem('/search-engines', [
            'server' => 'validation-test',
            'queryKey' => 'q',
        ], ['search_engine_write']);
        $searchEngineId = $searchEngine['searchEngineId'];

        // Try to update with server exceeding 255 characters
        $validationErrorsResponse = $this->partialUpdateItem('/search-engines/' . $searchEngineId, [
            'server' => str_repeat('a', 256),
        ], ['search_engine_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertIsArray($validationErrorsResponse);
        $this->assertValidationErrors([
            [
                'propertyPath' => 'server',
                'message' => 'This value should satisfy at least one of the following constraints: [1] This value should be blank. [2] This value is too long. It should have 255 characters or less.',
            ],
        ], $validationErrorsResponse);
    }

    /**
     * @depends testPartialUpdateSearchEngineMultipleFields
     */
    public function testListSearchEngines(int $searchEngineId): int
    {
        $response = $this->listItems('/search-engines', ['search_engine_read']);

        $this->assertGreaterThan(0, $response['totalItems']);
        $this->assertIsArray($response['items']);
        $this->assertNotEmpty($response['items']);

        // Check item structure
        $firstItem = $response['items'][0];
        $this->assertArrayHasKey('searchEngineId', $firstItem);
        $this->assertArrayHasKey('server', $firstItem);
        $this->assertArrayHasKey('queryKey', $firstItem);

        return $searchEngineId;
    }

    public function testListSearchEnginesWithSorting(): void
    {
        $response = $this->listItems('/search-engines?orderBy=searchEngineId&sortOrder=desc', ['search_engine_read']);

        $this->assertGreaterThan(0, $response['totalItems']);
        $this->assertIsArray($response['items']);

        // Verify sorting - IDs should be in descending order
        $ids = array_column($response['items'], 'searchEngineId');
        $sortedIds = $ids;
        rsort($sortedIds);
        $this->assertEquals($sortedIds, $ids, 'Items should be sorted by searchEngineId in descending order');
    }

    public function testListSearchEnginesWithFilters(): void
    {
        // Create a search engine with a unique name for filtering
        $this->createItem('/search-engines', [
            'server' => 'unique-filter-test-engine',
            'queryKey' => 'filterkey',
        ], ['search_engine_write']);

        $response = $this->listItems('/search-engines', ['search_engine_read'], ['server' => 'unique-filter-test-engine']);

        $this->assertGreaterThanOrEqual(1, $response['totalItems']);
        $this->assertIsArray($response['items']);
    }

    public function testDeleteSearchEngine(): void
    {
        // Create a search engine to delete
        $searchEngine = $this->createItem('/search-engines', [
            'server' => 'to-delete',
            'queryKey' => 'q',
        ], ['search_engine_write']);
        $searchEngineId = $searchEngine['searchEngineId'];

        // Delete it
        $this->requestApi('DELETE', '/search-engines/' . $searchEngineId, null, ['search_engine_write'], Response::HTTP_NO_CONTENT);

        // Verify it is deleted (GET should return 404)
        $this->requestApi('GET', '/search-engines/' . $searchEngineId, null, ['search_engine_read'], Response::HTTP_NOT_FOUND);
    }

    public function testDeleteSearchEngineNotFound(): void
    {
        $this->requestApi('DELETE', '/search-engines/99999', null, ['search_engine_write'], Response::HTTP_NOT_FOUND);
    }

    public function testBulkDeleteSearchEngines(): void
    {
        // Create multiple search engines for bulk delete
        $searchEngineIds = [];
        $servers = ['bulk-delete-one', 'bulk-delete-two', 'bulk-delete-three'];
        foreach ($servers as $server) {
            $searchEngine = $this->createItem('/search-engines', [
                'server' => $server,
                'queryKey' => 'q',
            ], ['search_engine_write']);
            $searchEngineIds[] = $searchEngine['searchEngineId'];
        }

        // Bulk delete
        $bulkDeleteData = [
            'searchEngineIds' => $searchEngineIds,
        ];
        $this->bulkDeleteItems('/search-engines/bulk-delete', $bulkDeleteData, ['search_engine_write']);

        // Verify all search engines are deleted
        foreach ($searchEngineIds as $searchEngineId) {
            $this->requestApi('GET', '/search-engines/' . $searchEngineId, null, ['search_engine_read'], Response::HTTP_NOT_FOUND);
        }
    }

    public function testBulkDeleteSearchEnginesValidationErrors(): void
    {
        // Test with empty searchEngineIds - should return 422 validation error
        $bulkDeleteData = [
            'searchEngineIds' => [],
        ];
        $this->bulkDeleteItems('/search-engines/bulk-delete', $bulkDeleteData, ['search_engine_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testBulkDeleteSearchEnginesNotFound(): void
    {
        // Try to bulk delete non-existent search engines
        $bulkDeleteData = [
            'searchEngineIds' => [99999, 99998],
        ];
        // @todo: Fix the bulk delete command to use AbstractBulkCommandHandler, the new proper way for handling bulk actions.
        $this->bulkDeleteItems('/search-engines/bulk-delete', $bulkDeleteData, ['search_engine_write'], Response::HTTP_NOT_FOUND);
    }
}
