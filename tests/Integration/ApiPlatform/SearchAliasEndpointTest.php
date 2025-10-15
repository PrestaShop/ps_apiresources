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

class SearchAliasEndpointTest extends ApiTestCase
{
    private static string $testSearchTerm = 'test-dress';
    private static string $testSearchTerm2 = 'test-phone';

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        DatabaseDump::restoreTables(['alias']);
        // Pre-create the API Client with the needed scopes
        self::createApiClient(['search_alias_write', 'search_alias_read']);

        // Clean up any existing test data
        self::cleanupTestData();
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        // Clean up test data and reset DB
        self::cleanupTestData();
        DatabaseDump::restoreTables(['alias']);
    }

    protected function setUp(): void
    {
        parent::setUp();
        // Clean up before each test
        self::cleanupTestData();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        // Clean up after each test
        self::cleanupTestData();
    }

    private static function cleanupTestData(): void
    {
        // Remove any test aliases that might exist
        $connection = static::getContainer()->get('doctrine.dbal.default_connection');
        $connection->executeStatement(
            'DELETE FROM ' . _DB_PREFIX_ . 'alias WHERE search IN (?, ?)',
            [self::$testSearchTerm, self::$testSearchTerm2]
        );
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'get endpoint' => [
            'GET',
            '/search-alias/dress',
        ];

        yield 'create endpoint' => [
            'POST',
            '/search-alias',
        ];

        yield 'update endpoint' => [
            'PUT',
            '/search-alias/dress',
        ];

        yield 'delete endpoint' => [
            'DELETE',
            '/search-alias/dress',
        ];

        yield 'list endpoint' => [
            'GET',
            '/search-aliases',
        ];

        yield 'bulk delete endpoint' => [
            'DELETE',
            '/search-aliases/delete',
        ];
    }

    public function testCreateSearchAlias(): void
    {
        $postData = [
            'search' => self::$testSearchTerm,
            'aliases' => [
                [
                    'alias' => 'test-dres',
                    'active' => true,
                ],
                [
                    'alias' => 'test-clothing',
                    'active' => true,
                ],
                [
                    'alias' => 'test-garment',
                    'active' => false,
                ],
            ],
        ];

        $response = $this->createItem('/search-alias', $postData, ['search_alias_write']);

        // CREATE returns 201 with empty resource confirmation (as per our implementation)
        $this->assertEquals('', $response['search']);
        $this->assertEquals([], $response['aliases']);

        // Verify the aliases were actually created in the database
        $this->assertSearchAliasExistsInDatabase(self::$testSearchTerm, 'test-dres', true);
        $this->assertSearchAliasExistsInDatabase(self::$testSearchTerm, 'test-clothing', true);
        $this->assertSearchAliasExistsInDatabase(self::$testSearchTerm, 'test-garment', false);
    }

    public function testCreateSearchAliasWithInvalidData(): void
    {
        // Test with empty search term - should return 422 with validation errors
        $postData = [
            'search' => '',
            'aliases' => [
                [
                    'alias' => 'test-alias',
                    'active' => true,
                ],
            ],
        ];

        $validationErrorsResponse = $this->createItem('/search-alias', $postData, ['search_alias_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertIsArray($validationErrorsResponse);
        $this->assertValidationErrors([
            [
                'propertyPath' => 'search',
                'message' => 'This value should not be blank.',
            ],
            [
                'propertyPath' => 'search',
                'message' => 'This value is too short. It should have 1 character or more.',
            ],
        ], $validationErrorsResponse);

        // Test with empty aliases - should return 422 with validation errors
        $postData = [
            'search' => 'test-search',
            'aliases' => [],
        ];

        $validationErrorsResponse = $this->createItem('/search-alias', $postData, ['search_alias_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertIsArray($validationErrorsResponse);
        $this->assertValidationErrors([
            [
                'propertyPath' => 'aliases',
                'message' => 'This value should not be blank.',
            ],
            [
                'propertyPath' => 'aliases',
                'message' => 'This collection should contain 1 element or more.',
            ],
        ], $validationErrorsResponse);

        // Test with missing alias field - should return 422 with validation errors
        $postData = [
            'search' => 'test-search',
            'aliases' => [
                [
                    'active' => true,
                ],
            ],
        ];

        $validationErrorsResponse = $this->createItem('/search-alias', $postData, ['search_alias_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertIsArray($validationErrorsResponse);

        // The response is a direct array of violations
        $this->assertNotEmpty($validationErrorsResponse);

        // Look for validation error about missing 'alias' field
        $hasAliasError = false;
        foreach ($validationErrorsResponse as $violation) {
            if (strpos($violation['propertyPath'], 'aliases') !== false
                && strpos($violation['message'], 'This field is missing') !== false) {
                $hasAliasError = true;
                break;
            }
        }
        $this->assertTrue($hasAliasError, 'Should have validation error about missing alias field');
    }

    public function testGetSearchAlias(): void
    {
        // First create a search alias
        $this->createTestSearchAlias();

        // Now get it
        $response = $this->getItem('/search-alias/' . self::$testSearchTerm, ['search_alias_read']);

        $this->assertEquals(self::$testSearchTerm, $response['search']);
        $this->assertIsArray($response['aliases']);
        $this->assertCount(2, $response['aliases']);

        // Check aliases content (order might vary, so check both aliases exist)
        $aliases = $response['aliases'];
        $aliasNames = array_column($aliases, 'alias');
        $this->assertContains('test-dres', $aliasNames);
        $this->assertContains('test-clothing', $aliasNames);

        // Find and check specific aliases (active is returned as int, not boolean)
        foreach ($aliases as $alias) {
            if ($alias['alias'] === 'test-dres' || $alias['alias'] === 'test-clothing') {
                $this->assertEquals(1, $alias['active']); // Active is stored as int in DB
            }
        }
    }

    public function testGetNonExistentSearchAlias(): void
    {
        // In PrestaShop, non-existent search terms return 200 with empty aliases array
        $response = $this->getItem('/search-alias/non-existent-term', ['search_alias_read']);

        $this->assertEquals('non-existent-term', $response['search']);
        $this->assertEquals([], $response['aliases']);
    }

    public function testUpdateSearchAlias(): void
    {
        // First create a search alias
        $this->createTestSearchAlias();

        // Update it
        $updateData = [
            'aliases' => [
                [
                    'alias' => 'updated-alias',
                    'active' => true,
                ],
                [
                    'alias' => 'another-updated-alias',
                    'active' => false,
                ],
            ],
        ];

        $this->updateItem('/search-alias/' . self::$testSearchTerm, $updateData, ['search_alias_write']);

        // Verify the update
        $response = $this->getItem('/search-alias/' . self::$testSearchTerm, ['search_alias_read']);
        $this->assertCount(2, $response['aliases']);

        // Check that the aliases were updated (order might vary)
        $aliasNames = array_column($response['aliases'], 'alias');
        $this->assertContains('updated-alias', $aliasNames);
        $this->assertContains('another-updated-alias', $aliasNames);
    }

    public function testDeleteSearchAlias(): void
    {
        // First create a search alias
        $this->createTestSearchAlias();

        // Verify it exists
        $this->getItem('/search-alias/' . self::$testSearchTerm, ['search_alias_read']);

        // Delete it
        $this->deleteItem('/search-alias/' . self::$testSearchTerm, ['search_alias_write']);

        // Verify it's deleted (returns empty aliases, not 404)
        $response = $this->getItem('/search-alias/' . self::$testSearchTerm, ['search_alias_read']);
        $this->assertEquals([], $response['aliases']);
    }

    public function testListSearchAliases(): void
    {
        // Create multiple search aliases
        $this->createTestSearchAlias();
        $this->createTestSearchAlias2();

        // List them
        $response = $this->listItems('/search-aliases', ['search_alias_read']);

        $this->assertGreaterThanOrEqual(2, $response['totalItems']);
        $this->assertIsArray($response['items']);

        // Find our test items
        $testItems = array_filter($response['items'], function ($item) {
            return in_array($item['search'], [self::$testSearchTerm, self::$testSearchTerm2]);
        });

        $this->assertCount(2, $testItems);
    }

    public function testListSearchAliasesWithFilters(): void
    {
        // Create test data
        $this->createTestSearchAlias();
        $this->createTestSearchAlias2();

        // Filter by search term
        $response = $this->listItems('/search-aliases', ['search_alias_read'], ['search' => self::$testSearchTerm]);

        $this->assertGreaterThanOrEqual(1, $response['totalItems']);

        // All returned items should match our filter
        foreach ($response['items'] as $item) {
            $this->assertStringContainsString(self::$testSearchTerm, $item['search']);
        }
    }

    public function testBulkDeleteSearchAliases(): void
    {
        // Create multiple search aliases
        $this->createTestSearchAlias();
        $this->createTestSearchAlias2();

        // Verify they exist
        $this->getItem('/search-alias/' . self::$testSearchTerm, ['search_alias_read']);
        $this->getItem('/search-alias/' . self::$testSearchTerm2, ['search_alias_read']);

        // Bulk delete
        $bulkDeleteData = [
            'searchTerms' => [self::$testSearchTerm, self::$testSearchTerm2],
        ];

        $this->deleteItem('/search-aliases/delete', ['search_alias_write'], Response::HTTP_NO_CONTENT, [
            'json' => $bulkDeleteData,
        ]);

        // Verify they're deleted (return empty aliases, not 404)
        $response1 = $this->getItem('/search-alias/' . self::$testSearchTerm, ['search_alias_read']);
        $response2 = $this->getItem('/search-alias/' . self::$testSearchTerm2, ['search_alias_read']);
        $this->assertEquals([], $response1['aliases']);
        $this->assertEquals([], $response2['aliases']);
    }

    public function testSearchAliasPermissions(): void
    {
        // Test that endpoints require proper authentication (401 without token)
        $this->getItem('/search-alias/test', [], Response::HTTP_UNAUTHORIZED);
        $this->createItem('/search-alias', [], [], Response::HTTP_UNAUTHORIZED);
        $this->updateItem('/search-alias/test', [], [], Response::HTTP_UNAUTHORIZED);
        $this->deleteItem('/search-alias/test', [], Response::HTTP_UNAUTHORIZED);

        // Test list endpoint with insufficient scope returns 403
        try {
            $this->listItems('/search-aliases', []);
            $this->fail('Expected 403 Forbidden response');
        } catch (\PHPUnit\Framework\AssertionFailedError $e) {
            // Check that it's a 403 error as expected
            $this->assertStringContainsString('403', $e->getMessage());
        }
    }

    private function createTestSearchAlias(): void
    {
        $postData = [
            'search' => self::$testSearchTerm,
            'aliases' => [
                [
                    'alias' => 'test-dres',
                    'active' => true,
                ],
                [
                    'alias' => 'test-clothing',
                    'active' => true,
                ],
            ],
        ];

        $this->createItem('/search-alias', $postData, ['search_alias_write']);
    }

    private function createTestSearchAlias2(): void
    {
        $postData = [
            'search' => self::$testSearchTerm2,
            'aliases' => [
                [
                    'alias' => 'test-telephone',
                    'active' => true,
                ],
                [
                    'alias' => 'test-mobile',
                    'active' => false,
                ],
            ],
        ];

        $this->createItem('/search-alias', $postData, ['search_alias_write']);
    }

    private function assertSearchAliasExistsInDatabase(string $searchTerm, string $alias, bool $active): void
    {
        $connection = static::getContainer()->get('doctrine.dbal.default_connection');
        $result = $connection->fetchAssociative(
            'SELECT * FROM ' . _DB_PREFIX_ . 'alias WHERE search = ? AND alias = ?',
            [$searchTerm, $alias]
        );

        $this->assertNotFalse($result, "Search alias '$alias' for term '$searchTerm' should exist in database");
        $this->assertEquals($active ? 1 : 0, (int) $result['active'], "Search alias '$alias' should have active = " . ($active ? 'true' : 'false'));
    }
}
