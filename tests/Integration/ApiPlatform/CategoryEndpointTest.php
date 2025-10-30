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
use Tests\Resources\Resetter\LanguageResetter;

class CategoryEndpointTest extends ApiTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        // Add the fr-FR language to test multi lang values accurately
        LanguageResetter::resetLanguages();
        self::addLanguageByLocale('fr-FR');
        self::resetTables();
        // Pre-create API client with needed scopes
        self::createApiClient(['category_read', 'category_write']);
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        // Reset DB as it was before this test
        LanguageResetter::resetLanguages();
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
            '/category/3',
        ];

        yield 'create endpoint' => [
            'POST',
            '/category',
        ];

        yield 'patch endpoint' => [
            'PATCH',
            '/category/10',
        ];

        yield 'bulk toggle endpoint' => [
            'PUT',
            '/categories/toggle-status',
        ];

        yield 'delete endpoint' => [
            'DELETE',
            '/category/10',
        ];

        yield 'update status endpoint' => [
            'PATCH',
            '/category/3/status',
        ];

        yield 'delete thumbnail endpoint' => [
            'DELETE',
            '/category/3/thumbnail',
        ];

        yield 'delete cover endpoint' => [
            'DELETE',
            '/category/4/thumbnail',
        ];

        yield 'bulk delete endpoint' => [
            'PUT',
            '/categories/delete',
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

        $category = $this->createItem('/category', $postData, ['category_write']);
        $categoryId = $category['categoryId'];

        $this->assertArrayHasKey('categoryId', $category);

        $this->assertSame($postData['names'], $category['names']);
        $this->assertSame($postData['linkRewrites'], $category['linkRewrites']);

        return $categoryId;
    }

    /**
     * @depends testAddCategory
     */
    public function testGetCategory(int $categoryId): int
    {
        $category = $this->getItem('/category/' . $categoryId, ['category_read']);

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
        $this->assertArrayHasKey('active', $first);
        $this->assertEquals($categoryId, $first['categoryId']);

        return $categoryId;
    }

    /**
     * @depends testListCategories
     */
    public function testDeleteCategory(int $categoryId): void
    {
        // Delete the item
        $this->requestApi(
            Request::METHOD_DELETE,
            '/category/' . $categoryId,
            ['mode' => 'associate_and_disable'],
            ['category_write']
        );

        // Fetching the item returns a 404 indicatjng it no longer exists
        $this->getItem('/category/' . $categoryId, ['category_read'], Response::HTTP_NOT_FOUND);
    }

    public function testUpdateCategoryStatus(): void
    {
        // Disable the category and assert the change is effective
        $this->requestApi(
            Request::METHOD_PATCH,
            '/category/3/status',
            ['isEnabled' => false],
            ['category_write'],
            Response::HTTP_OK
        );

        $category = $this->getItem('/category/3', ['category_read']);

        $this->assertFalse($category['active']);

        // Re-enable the category to avoid leaving side effects for other tests
        $this->requestApi(
            Request::METHOD_PATCH,
            '/category/3/status',
            ['isEnabled' => true],
            ['category_write'],
            Response::HTTP_OK
        );

        $category = $this->getItem('/category/3', ['category_read']);
        $this->assertTrue($category['active']);
    }

    public function testDeleteCategoryThumbnail(): void
    {
        // This test checks the happy path of the "delete thumbnail" endpoint.
        // We use a predefined category (ID 3) that is known
        // to have a cover image, so the DELETE request must succeed and return 204 (No Content).
        $this->requestApi(
            Request::METHOD_DELETE,
            '/category/3/thumbnail',
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
            '/category/4/cover',
            [],
            ['category_write'],
            Response::HTTP_NO_CONTENT
        );
    }

    public function testBulkUpdateStatus(): array
    {
        $bulkCategories = $this->createTemporaryCategories();

        // Perform bulk disable on the selected categories
        $this->updateItem('/categories/toggle-status', [
            'categoryIds' => $bulkCategories,
            'enabled' => false,
        ], ['category_write'], Response::HTTP_NO_CONTENT);

        // Assert that the selected categories have been successfully disabled
        foreach ($bulkCategories as $categoryId) {
            $category = $this->getItem('/category/' . $categoryId, ['category_read']);
            $this->assertEquals(false, $category['active']);
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
        $this->updateItem('/categories/delete', [
            'categoryIds' => $bulkCategories,
            'enabled' => false,
            'mode' => 'associate_and_disable',
        ], ['category_write'], Response::HTTP_NO_CONTENT);

        // Assert the provided categories have been removed
        foreach ($bulkCategories as $categoryId) {
            $this->getItem('/category/' . $categoryId, ['category_read'], Response::HTTP_NOT_FOUND);
        }
    }

    /**
     * Create two temporary categories for bulk operation tests.
     *
     * @return int[] The IDs of the created categories
     */
    private function createTemporaryCategories(): array
    {
        $cat1 = $this->createItem('/category', [
            'names' => ['en-US' => 'TempCat 1'],
            'linkRewrites' => [
                'en-US' => 'temp-cat-2',
                'fr-FR' => 'temp-cat-2',
            ],
            'isActive' => true,
            'parentCategoryId' => 2,
            'shopIds' => [1],
        ], ['category_write']);

        $cat2 = $this->createItem('/category', [
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
