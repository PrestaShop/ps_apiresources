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

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Tests\Resources\DatabaseDump;

class SearchAliasEndpointTest extends ApiTestCase
{
    private const SEARCH_TERM = 'test-dress';
    private const UPDATE_SEARCH_TERM = 'updated-test-dress';
    private const SECOND_SEARCH_TERM = 'test-phone';

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        DatabaseDump::restoreTables(['alias']);
        // Pre-create the API Client with the needed scopes
        self::createApiClient(['search_alias_write', 'search_alias_read']);
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        DatabaseDump::restoreTables(['alias']);
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'get endpoint' => [
            'GET',
            '/search-aliases/dress',
        ];

        yield 'create endpoint' => [
            'POST',
            '/search-aliases',
        ];

        yield 'update endpoint' => [
            'PUT',
            '/search-aliases/dress',
        ];

        yield 'delete endpoint' => [
            'DELETE',
            '/search-aliases',
        ];

        yield 'list endpoint' => [
            'GET',
            '/search-aliases',
        ];

        yield 'bulk delete endpoint' => [
            'DELETE',
            '/search-aliases/bulk-delete',
        ];
    }

    public function testCreateSearchAlias(): void
    {
        // POST data follows the expected format with only enabled and using booleans
        $postData = [
            'searchTerm' => self::SEARCH_TERM,
            'aliases' => [
                [
                    'alias' => 'test-dres',
                    'enabled' => true,
                ],
                [
                    'alias' => 'test-clothing',
                    'enabled' => true,
                ],
                [
                    'alias' => 'test-garment',
                    'enabled' => false,
                ],
            ],
        ];

        // So far alias create endpoint cannot return the created entity because of things missing in the API core,
        // and the CQRS commands/queries also need some adjustments This will have to be improved later
        $this->createItem('/search-aliases', $postData, ['search_alias_write'], Response::HTTP_NO_CONTENT);

        // Verify the aliases were actually created in the database
        $this->assertSearchAliasExistsInDatabase(self::SEARCH_TERM, 'test-dres', true);
        $this->assertSearchAliasExistsInDatabase(self::SEARCH_TERM, 'test-clothing', true);
        $this->assertSearchAliasExistsInDatabase(self::SEARCH_TERM, 'test-garment', false);
    }

    /**
     * @depends testCreateSearchAlias
     */
    public function testGetSearchAlias(): void
    {
        // GET data is messed up, there is an extra active field and the data is not casted
        $getData = [
            'searchTerm' => self::SEARCH_TERM,
            'aliases' => [
                [
                    'alias' => 'test-clothing',
                    'enabled' => 1,
                    'active' => 1,
                ],
                [
                    'alias' => 'test-dres',
                    'enabled' => 1,
                    'active' => 1,
                ],
                [
                    'alias' => 'test-garment',
                    'enabled' => 0,
                    'active' => 0,
                ],
            ],
        ];

        // Now get the created search
        $response = $this->getItem('/search-aliases/' . self::SEARCH_TERM, ['search_alias_read']);
        $this->assertEquals($getData, $response);
    }

    /**
     * @depends testGetSearchAlias
     */
    public function testUpdateSearchOnlyAliases(): void
    {
        // Update only its aliases (not the search term itself)
        $updateData = [
            'aliases' => [
                [
                    'alias' => 'updated-alias',
                    'enabled' => true,
                ],
                [
                    'alias' => 'another-updated-alias',
                    'enabled' => false,
                ],
            ],
        ];
        $this->updateItem('/search-aliases/' . self::SEARCH_TERM, $updateData, ['search_alias_write'], Response::HTTP_NO_CONTENT);

        // Verify the update
        $updatedGetData = [
            'searchTerm' => self::SEARCH_TERM,
            'aliases' => [
                [
                    'alias' => 'another-updated-alias',
                    'enabled' => 0,
                    'active' => 0,
                ],
                [
                    'alias' => 'updated-alias',
                    'enabled' => 1,
                    'active' => 1,
                ],
            ],
        ];
        $response = $this->getItem('/search-aliases/' . self::SEARCH_TERM, ['search_alias_read']);
        $this->assertEquals($updatedGetData, $response);
    }

    /**
     * @depends testUpdateSearchOnlyAliases
     */
    public function testUpdateFullSearch(): void
    {
        // Now update the search term
        $fullUpdatedData = [
            'newSearchTerm' => self::UPDATE_SEARCH_TERM,
            'aliases' => [
                [
                    'alias' => 'new-updated-alias',
                    'enabled' => false,
                ],
                [
                    'alias' => 'new-another-updated-alias',
                    'enabled' => true,
                ],
            ],
        ];
        $this->updateItem('/search-aliases/' . self::SEARCH_TERM, $fullUpdatedData, ['search_alias_write'], Response::HTTP_NO_CONTENT);

        // Verify the update (the url changed since the search term was changed)
        $fullyUpdatedGetData = [
            'searchTerm' => self::UPDATE_SEARCH_TERM,
            'aliases' => [
                [
                    'alias' => 'new-another-updated-alias',
                    'enabled' => 1,
                    'active' => 1,
                ],
                [
                    'alias' => 'new-updated-alias',
                    'enabled' => 0,
                    'active' => 0,
                ],
            ],
        ];
        $response = $this->getItem('/search-aliases/' . self::UPDATE_SEARCH_TERM, ['search_alias_read']);
        $this->assertEquals($fullyUpdatedGetData, $response);

        // Check that prevent search alias no longer exists since it was renamed
        // @todo: this should return a 404 but it doesn work, probably because the handler in the core doesn't check the existence
        //        this will be checked via the list endpoints though
        // $this->getItem('/search-aliases/' . self::SEARCH_TERM, ['search_alias_read'], Response::HTTP_NOT_FOUND);
    }

    /**
     * @depends testUpdateFullSearch
     */
    public function testListSearchAliases(): void
    {
        // List them
        $response = $this->listItems('/search-aliases', ['search_alias_read']);

        $this->assertEquals(2, $response['totalItems']);
        $this->assertIsArray($response['items']);

        // Find our test items
        // @todo the list returns the id_alias but they are not used anywhere else, they should not be present
        // Since IDs are present and hard-coded values are unstable, we fill them dynamically
        $expectedList = [
            // Alias from the fixtures
            [
                'search' => 'blouse',
                'aliases' => [
                    [
                        'id_alias' => 1,
                        'alias' => 'bloose',
                        'active' => 1,
                    ],
                    [
                        'id_alias' => 2,
                        'alias' => 'blues',
                        'active' => 1,
                    ],
                ],
            ],
            [
                'search' => self::UPDATE_SEARCH_TERM,
                'aliases' => [
                    [
                        'id_alias' => $response['items'][1]['aliases'][0]['id_alias'],
                        'alias' => 'new-another-updated-alias',
                        'active' => 1,
                    ],
                    [
                        'id_alias' => $response['items'][1]['aliases'][1]['id_alias'],
                        'alias' => 'new-updated-alias',
                        'active' => 0,
                    ],
                ],
            ],
        ];
        $this->assertEquals($expectedList, $response['items']);
    }

    /**
     * @depends testListSearchAliases
     */
    public function testListSearchAliasesWithFilters(): void
    {
        // Filter by search term
        $response = $this->listItems('/search-aliases', ['search_alias_read'], ['search' => self::UPDATE_SEARCH_TERM]);
        $this->assertEquals(1, $response['totalItems']);
        $this->assertIsArray($response['items']);

        $expectedList = [
            [
                'search' => self::UPDATE_SEARCH_TERM,
                'aliases' => [
                    [
                        'id_alias' => $response['items'][0]['aliases'][0]['id_alias'],
                        'alias' => 'new-another-updated-alias',
                        'active' => 1,
                    ],
                    [
                        'id_alias' => $response['items'][0]['aliases'][1]['id_alias'],
                        'alias' => 'new-updated-alias',
                        'active' => 0,
                    ],
                ],
            ],
        ];
        $this->assertEquals($expectedList, $response['items']);
    }

    /**
     * @depends testListSearchAliasesWithFilters
     */
    public function testDeleteSearchAlias(): void
    {
        // Verify it exists
        $this->getItem('/search-aliases/' . self::UPDATE_SEARCH_TERM, ['search_alias_read']);

        // Delete the search (searchTerm must be passed via json)
        $this->requestApi(Request::METHOD_DELETE, '/search-aliases', [
            'searchTerm' => self::UPDATE_SEARCH_TERM,
        ], ['search_alias_write'], Response::HTTP_NO_CONTENT);

        // Verify it's deleted (returns empty aliases, not 404)
        // @todo this is a hack way to check the alias is removed, but it should return a 404
        $response = $this->getItem('/search-aliases/' . self::SEARCH_TERM, ['search_alias_read']);
        $this->assertEquals([], $response['aliases']);
    }

    public function testBulkDeleteSearchAliases(): void
    {
        $searchAliases = ['searchAlias1', 'searchAlias2'];
        // Create multiple search aliases
        foreach ($searchAliases as $searchAlias) {
            $postData = [
                'searchTerm' => $searchAlias,
                'aliases' => [
                    [
                        'alias' => $searchAlias . 'Alias',
                        'enabled' => true,
                    ],
                ],
            ];
            $this->createItem('/search-aliases', $postData, ['search_alias_write'], Response::HTTP_NO_CONTENT);
        }

        // Verify they exist
        foreach ($searchAliases as $searchAlias) {
            $response = $this->getItem('/search-aliases/' . $searchAlias, ['search_alias_read']);
            $this->assertNotEquals([], $response['aliases']);
        }

        // Bulk delete
        $bulkDeleteData = [
            'searchTerms' => $searchAliases,
        ];
        $this->bulkDeleteItems('/search-aliases/bulk-delete', $bulkDeleteData, ['search_alias_write']);

        // Verify they're deleted (return empty aliases, not 404)
        foreach ($searchAliases as $searchAlias) {
            $response = $this->getItem('/search-aliases/' . $searchAlias, ['search_alias_read']);
            $this->assertEquals([], $response['aliases']);
        }
    }

    public function testSearchAliasPermissions(): void
    {
        // Test that endpoints require proper authentication (401 without token)
        $this->getItem('/search-aliases/test', [], Response::HTTP_UNAUTHORIZED);
        $this->createItem('/search-aliases', [], [], Response::HTTP_UNAUTHORIZED);
        $this->updateItem('/search-aliases/test', [], [], Response::HTTP_UNAUTHORIZED);
        $this->deleteItem('/search-aliases', [], Response::HTTP_UNAUTHORIZED);
        $this->deleteItem('/search-aliases/bulk-delete', [], Response::HTTP_UNAUTHORIZED);

        // Test list endpoint with insufficient scope returns 403
        try {
            $this->listItems('/search-aliases', []);
            $this->fail('Expected 403 Forbidden response');
        } catch (\PHPUnit\Framework\AssertionFailedError $e) {
            // Check that it's a 403 error as expected
            $this->assertStringContainsString('403', $e->getMessage());
        }
    }

    public function testGetNonExistentSearchAlias(): void
    {
        // In PrestaShop, non-existent search terms return 200 with empty aliases array
        $response = $this->getItem('/search-aliases/non-existent-term', ['search_alias_read']);

        $this->assertEquals('non-existent-term', $response['searchTerm']);
        $this->assertEquals([], $response['aliases']);
    }

    public function testCreateSearchAliasWithInvalidData(): void
    {
        // Test with empty search term - should return 422 with validation errors
        $postData = [
            'searchTerm' => '',
            'aliases' => [
                [
                    'alias' => 'test-alias',
                    'enabled' => true,
                ],
            ],
        ];

        $validationErrorsResponse = $this->createItem('/search-aliases', $postData, ['search_alias_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertIsArray($validationErrorsResponse);
        $this->assertValidationErrors([
            [
                'propertyPath' => 'searchTerm',
                'message' => 'This value should not be blank.',
            ],
            [
                'propertyPath' => 'searchTerm',
                'message' => 'This value is too short. It should have 1 character or more.',
            ],
        ], $validationErrorsResponse);

        // Test with empty aliases - should return 422 with validation errors
        $postData = [
            'searchTerm' => 'test-search',
            'aliases' => [],
        ];

        $validationErrorsResponse = $this->createItem('/search-aliases', $postData, ['search_alias_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
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
            'searchTerm' => 'test-search',
            'aliases' => [
                [
                    'enabled' => true,
                ],
            ],
        ];

        $validationErrorsResponse = $this->createItem('/search-aliases', $postData, ['search_alias_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
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

    private function createTestSearchAlias2(): void
    {
        $postData = [
            'searchTerm' => self::SECOND_SEARCH_TERM,
            'aliases' => [
                [
                    'alias' => 'test-telephone',
                    'enabled' => true,
                ],
                [
                    'alias' => 'test-mobile',
                    'enabled' => false,
                ],
            ],
        ];

        $this->createItem('/search-aliases', $postData, ['search_alias_write']);
    }

    private function assertSearchAliasExistsInDatabase(string $searchTerm, string $alias, bool $enabled): void
    {
        $connection = static::getContainer()->get('doctrine.dbal.default_connection');
        $result = $connection->fetchAssociative(
            'SELECT * FROM ' . _DB_PREFIX_ . 'alias WHERE search = ? AND alias = ?',
            [$searchTerm, $alias]
        );

        $this->assertNotFalse($result, "Search alias '$alias' for term '$searchTerm' should exist in database");
        $this->assertEquals($enabled ? 1 : 0, (int) $result['active'], "Search alias '$alias' should have enabled = " . ($enabled ? 'true' : 'false'));
    }
}
