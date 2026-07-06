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

class CategoryEndpointTest extends ApiTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::resetTables();
        // Pre-create API client with needed scopes
        self::createApiClient(['category_read', 'category_write']);
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        self::resetTables();
    }

    protected static function resetTables(): void
    {
        DatabaseDump::restoreTables([
            'category',
            'category_lang',
            'category_group',
            'category_shop',
            'category_product',
        ]);
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'get endpoint' => [
            'GET',
            '/categories/3',
        ];

        yield 'create endpoint' => [
            'POST',
            '/categories',
        ];

        yield 'patch endpoint' => [
            'PATCH',
            '/categories/10',
        ];

        yield 'bulk toggle endpoint' => [
            'PUT',
            '/categories/bulk-update-status',
        ];

        yield 'delete endpoint' => [
            'DELETE',
            '/categories/10/associate_and_disable',
        ];

        yield 'update status endpoint' => [
            'PATCH',
            '/categories/3/status',
        ];

        yield 'delete thumbnail endpoint' => [
            'DELETE',
            '/categories/3/thumbnail',
        ];

        yield 'delete cover endpoint' => [
            'DELETE',
            '/categories/4/thumbnail',
        ];

        yield 'bulk delete endpoint' => [
            'DELETE',
            '/categories/bulk-delete/associate_and_disable',
        ];

        yield 'create root endpoint' => [
            'POST',
            '/categories/root',
        ];

        yield 'patch root endpoint' => [
            'PATCH',
            '/categories/root/2',
        ];

        yield 'update position endpoint' => [
            'PUT',
            '/categories/3/position',
        ];
    }

    public function testAddCategory(): int
    {
        $postData = [
            'names' => [
                'en-US' => 'Category EN',
                'fr-FR' => 'Catégorie FR',
            ],
            'linkRewrites' => [
                'en-US' => 'category-en',
                'fr-FR' => 'categorie-fr',
            ],
            'isActive' => true,
            'parentCategoryId' => 2,
            'shopIds' => [1],
        ];

        $category = $this->createItem('/categories', $postData, ['category_write']);
        $categoryId = $category['categoryId'];

        $this->assertArrayHasKey('categoryId', $category);

        $this->assertSame($postData['names'], $category['names']);
        $this->assertSame($postData['linkRewrites'], $category['linkRewrites']);
        $this->assertSame($postData['parentCategoryId'], $category['parentCategoryId']);

        return $categoryId;
    }

    /**
     * @depends testAddCategory
     */
    public function testGetCategory(int $categoryId): int
    {
        $category = $this->getItem('/categories/' . $categoryId, ['category_read']);

        $this->assertSame(
            $category['names'],
            [
                'en-US' => 'Category EN',
                'fr-FR' => 'Catégorie FR',
            ]
        );

        $this->assertSame(
            $category['linkRewrites'],
            [
                'en-US' => 'category-en',
                'fr-FR' => 'categorie-fr',
            ]
        );

        $this->assertSame(2, $category['parentCategoryId']);

        return $categoryId;
    }

    /**
     * @depends testGetCategory
     */
    public function testListCategories(int $categoryId): int
    {
        $paginated = $this->listItems('/categories?orderBy=categoryId&sortOrder=desc', ['category_read']);

        $this->assertGreaterThanOrEqual(10, $paginated['totalItems']);

        // First item should be our test category
        $first = $paginated['items'][0];

        $this->assertArrayHasKey('categoryId', $first);
        $this->assertArrayHasKey('name', $first);
        $this->assertArrayHasKey('enabled', $first);
        $this->assertEquals($categoryId, $first['categoryId']);

        return $categoryId;
    }

    /**
     * @depends testListCategories
     */
    public function testDeleteCategory(int $categoryId): void
    {
        // Delete the item
        $this->deleteItem(
            '/categories/' . $categoryId . '/associate_and_disable',
            ['category_write']
        );

        // Fetching the item returns a 404 indicatjng it no longer exists
        $this->getItem('/categories/' . $categoryId, ['category_read'], Response::HTTP_NOT_FOUND);
    }

    public function testUpdateCategoryStatus(): void
    {
        // Disable the category and assert the change is effective
        $this->requestApi(
            Request::METHOD_PATCH,
            '/categories/3/status',
            ['enabled' => false],
            ['category_write'],
            Response::HTTP_OK
        );

        $category = $this->getItem('/categories/3', ['category_read']);

        $this->assertFalse($category['enabled']);

        // Re-enable the category to avoid leaving side effects for other tests
        $this->requestApi(
            Request::METHOD_PATCH,
            '/categories/3/status',
            ['enabled' => true],
            ['category_write'],
            Response::HTTP_OK
        );

        $category = $this->getItem('/categories/3', ['category_read']);
        $this->assertTrue($category['enabled']);
    }

    public function testDeleteCategoryThumbnail(): void
    {
        // This test checks the happy path of the "delete thumbnail" endpoint.
        // We use a predefined category (ID 3) that is known
        // to have a cover image, so the DELETE request must succeed and return 204 (No Content).
        $this->requestApi(
            Request::METHOD_DELETE,
            '/categories/3/thumbnail',
            [],
            ['category_write'],
            Response::HTTP_NO_CONTENT
        );
    }

    public function testDeleteCategoryCover(): void
    {
        // This test checks the happy path of the "delete cover" endpoint.
        // We use a predefined category (ID 4) that is known
        // to have a cover image, so the DELETE request must succeed and return 204 (No Content).
        $this->requestApi(
            Request::METHOD_DELETE,
            '/categories/4/cover',
            [],
            ['category_write'],
            Response::HTTP_NO_CONTENT
        );
    }

    public function testBulkUpdateStatus(): array
    {
        $bulkCategories = $this->createTemporaryCategories();

        // Perform bulk disable on the selected categories
        $this->updateItem('/categories/bulk-update-status', [
            'categoryIds' => $bulkCategories,
            'enabled' => false,
        ], ['category_write'], Response::HTTP_NO_CONTENT);

        // Assert that the selected categories have been successfully disabled
        foreach ($bulkCategories as $categoryId) {
            $category = $this->getItem('/categories/' . $categoryId, ['category_read']);
            $this->assertEquals(false, $category['enabled']);
        }

        // Return IDs so they can be reused by testBulkDelete
        return $bulkCategories;
    }

    /**
     * @depends testBulkUpdateStatus
     */
    public function testBulkDelete(array $bulkCategories): void
    {
        // Bulk delete with deleteMode
        $this->bulkDeleteItems('/categories/bulk-delete/associate_and_disable', [
            'categoryIds' => $bulkCategories,
        ], ['category_write']);

        foreach ($bulkCategories as $categoryId) {
            $this->getItem('/categories/' . $categoryId, ['category_read'], Response::HTTP_NOT_FOUND);
        }
    }

    public function testAddRootCategory(): int
    {
        $postData = [
            'names' => [
                'en-US' => 'Root Category EN',
                'fr-FR' => 'Catégorie racine FR',
            ],
            'linkRewrites' => [
                'en-US' => 'root-category-en',
                'fr-FR' => 'categorie-racine-fr',
            ],
            'enabled' => true,
            'shopIds' => [1],
        ];

        $rootCategory = $this->createItem('/categories/root', $postData, ['category_write']);

        $this->assertArrayHasKey('categoryId', $rootCategory);
        $this->assertSame($postData['names'], $rootCategory['names']);
        $this->assertSame($postData['linkRewrites'], $rootCategory['linkRewrites']);

        return $rootCategory['categoryId'];
    }

    /**
     * @depends testAddRootCategory
     */
    public function testEditRootCategory(int $categoryId): int
    {
        $patchData = [
            'names' => [
                'en-US' => 'Root Category EN edited',
                'fr-FR' => 'Catégorie racine FR modifiée',
            ],
        ];

        $updated = $this->partialUpdateItem('/categories/root/' . $categoryId, $patchData, ['category_write']);
        $this->assertSame($patchData['names'], $updated['names']);

        // Verify the GET reflects the change too
        $fetched = $this->getItem('/categories/' . $categoryId, ['category_read']);
        $this->assertSame($patchData['names'], $fetched['names']);

        return $categoryId;
    }

    public function testEditUnknownRootCategoryReturnsNotFound(): void
    {
        $unknownCategoryId = 1 + (int) \Db::getInstance()->getValue(
            'SELECT MAX(`id_category`) FROM `' . _DB_PREFIX_ . 'category`'
        );

        $this->partialUpdateItem(
            '/categories/root/' . $unknownCategoryId,
            ['names' => ['en-US' => 'Does not matter']],
            ['category_write'],
            Response::HTTP_NOT_FOUND
        );
    }

    public function testUpdateCategoryPosition(): void
    {
        // Create a parent category with two children so we can reorder them
        $parentId = $this->createItem('/categories', [
            'names' => ['en-US' => 'Position Parent'],
            'linkRewrites' => ['en-US' => 'position-parent'],
            'isActive' => true,
            'parentCategoryId' => 2,
            'shopIds' => [1],
        ], ['category_write'])['categoryId'];

        $childIds = [];
        foreach (['a', 'b'] as $suffix) {
            $childIds[] = $this->createItem('/categories', [
                'names' => ['en-US' => 'Position Child ' . $suffix],
                'linkRewrites' => ['en-US' => 'position-child-' . $suffix],
                'isActive' => true,
                'parentCategoryId' => $parentId,
                'shopIds' => [1],
            ], ['category_write'])['categoryId'];
        }

        [$a, $b] = $childIds;

        // Move the last child up to the first position. "positions" is the ordered list of
        // the parent's children, each entry formatted as "{rowId}_{parentId}_{categoryId}"
        // (see the legacy CategoryController::updatePositionAction and the core Behat scenarios
        // in category_management.feature which cover the reordering behaviour itself).
        $this->requestApi(
            Request::METHOD_PUT,
            '/categories/' . $b . '/position',
            [
                'parentCategoryId' => $parentId,
                'way' => 0,
                'positions' => [
                    'tr_' . $parentId . '_' . $b,
                    'tr_' . $parentId . '_' . $a,
                ],
                'foundFirst' => false,
            ],
            ['category_write'],
            Response::HTTP_NO_CONTENT
        );
    }

    /**
     * Create two temporary categories for bulk operation tests.
     *
     * @return int[] The IDs of the created categories
     */
    private function createTemporaryCategories(): array
    {
        $cat1 = $this->createItem('/categories', [
            'names' => ['en-US' => 'TempCat 1'],
            'linkRewrites' => [
                'en-US' => 'temp-cat-2',
                'fr-FR' => 'temp-cat-2',
            ],
            'isActive' => true,
            'parentCategoryId' => 2,
            'shopIds' => [1],
        ], ['category_write']);

        $cat2 = $this->createItem('/categories', [
            'names' => ['en-US' => 'TempCat 2'],
            'linkRewrites' => [
                'en-US' => 'temp-cat-2',
                'fr-FR' => 'temp-cat-2',
            ],
            'isActive' => true,
            'parentCategoryId' => 2,
            'shopIds' => [1],
        ], ['category_write']);

        return [$cat1['categoryId'], $cat2['categoryId']];
    }
}
