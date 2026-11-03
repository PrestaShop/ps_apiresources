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
use Tests\Resources\Resetter\LanguageResetter;

class CmsPageCategoryEndpointTest extends ApiTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        LanguageResetter::resetLanguages();
        self::addLanguageByLocale('fr-FR');
        self::resetTables();
        self::createApiClient(['cms_page_category_read', 'cms_page_category_write']);
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        LanguageResetter::resetLanguages();
        self::resetTables();
    }

    protected static function resetTables(): void
    {
        DatabaseDump::restoreTables([
            'cms_category',
            'cms_category_lang',
            'cms_category_shop',
        ]);
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'get endpoint' => [
            'GET',
            '/cms-page-categories/1',
        ];

        yield 'create endpoint' => [
            'POST',
            '/cms-page-categories',
        ];

        yield 'patch endpoint' => [
            'PATCH',
            '/cms-page-categories/1',
        ];

        yield 'toggle-status endpoint' => [
            'PUT',
            '/cms-page-categories/1/toggle-status',
        ];

        yield 'delete endpoint' => [
            'DELETE',
            '/cms-page-categories/1',
        ];

        yield 'list endpoint' => [
            'GET',
            '/cms-page-categories',
        ];

        yield 'bulk delete endpoint' => [
            'DELETE',
            '/cms-page-categories/bulk-delete',
        ];

        yield 'bulk enable endpoint' => [
            'PUT',
            '/cms-page-categories/bulk-enable',
        ];

        yield 'bulk disable endpoint' => [
            'PUT',
            '/cms-page-categories/bulk-disable',
        ];
    }

    public function testAddCmsPageCategory(): int
    {
        $itemsCount = $this->countItems('/cms-page-categories', ['cms_page_category_read']);

        $postData = [
            'names' => [
                'en-US' => 'Test Category EN',
                'fr-FR' => 'Catégorie test FR',
            ],
            'friendlyUrls' => [
                'en-US' => 'test-category-en',
                'fr-FR' => 'categorie-test-fr',
            ],
            'enabled' => true,
            'parentId' => 1,
            'descriptions' => [
                'en-US' => 'Description EN',
                'fr-FR' => 'Description FR',
            ],
            'metaTitles' => [
                'en-US' => 'Meta Title EN',
                'fr-FR' => 'Meta Title FR',
            ],
            'metaDescriptions' => [
                'en-US' => 'Meta Description EN',
                'fr-FR' => 'Meta Description FR',
            ],
            'shopIds' => [1],
        ];

        $category = $this->createItem('/cms-page-categories', $postData, ['cms_page_category_write']);
        $this->assertArrayHasKey('cmsPageCategoryId', $category);
        $cmsPageCategoryId = $category['cmsPageCategoryId'];

        $this->assertEquals(
            ['cmsPageCategoryId' => $cmsPageCategoryId] + $postData,
            $category
        );

        $newItemsCount = $this->countItems('/cms-page-categories', ['cms_page_category_read']);
        $this->assertEquals($itemsCount + 1, $newItemsCount);

        return $cmsPageCategoryId;
    }

    /**
     * @depends testAddCmsPageCategory
     */
    public function testGetCmsPageCategory(int $cmsPageCategoryId): int
    {
        $category = $this->getItem('/cms-page-categories/' . $cmsPageCategoryId, ['cms_page_category_read']);

        $this->assertEquals([
            'cmsPageCategoryId' => $cmsPageCategoryId,
            'names' => [
                'en-US' => 'Test Category EN',
                'fr-FR' => 'Catégorie test FR',
            ],
            'friendlyUrls' => [
                'en-US' => 'test-category-en',
                'fr-FR' => 'categorie-test-fr',
            ],
            'enabled' => true,
            'parentId' => 1,
            'descriptions' => [
                'en-US' => 'Description EN',
                'fr-FR' => 'Description FR',
            ],
            'metaTitles' => [
                'en-US' => 'Meta Title EN',
                'fr-FR' => 'Meta Title FR',
            ],
            'metaDescriptions' => [
                'en-US' => 'Meta Description EN',
                'fr-FR' => 'Meta Description FR',
            ],
            'shopIds' => [1],
        ], $category);

        return $cmsPageCategoryId;
    }

    /**
     * @depends testGetCmsPageCategory
     */
    public function testPartialUpdateCmsPageCategory(int $cmsPageCategoryId): int
    {
        $patchData = [
            'names' => [
                'en-US' => 'Updated Category EN',
                'fr-FR' => 'Catégorie mise à jour FR',
            ],
            'friendlyUrls' => [
                'en-US' => 'updated-category-en',
                'fr-FR' => 'categorie-mise-a-jour-fr',
            ],
            'enabled' => false,
            'parentId' => 1,
            'descriptions' => [
                'en-US' => 'Updated Description EN',
                'fr-FR' => 'Description mise à jour FR',
            ],
            'metaTitles' => [
                'en-US' => 'Updated Meta Title EN',
                'fr-FR' => 'Meta Title mis à jour FR',
            ],
            'metaDescriptions' => [
                'en-US' => 'Updated Meta Description EN',
                'fr-FR' => 'Meta Description mise à jour FR',
            ],
            'shopIds' => [1],
        ];

        $updated = $this->partialUpdateItem(
            '/cms-page-categories/' . $cmsPageCategoryId,
            $patchData,
            ['cms_page_category_write']
        );
        $this->assertEquals(['cmsPageCategoryId' => $cmsPageCategoryId] + $patchData, $updated);

        // Verify GET reflects the update
        $fetched = $this->getItem('/cms-page-categories/' . $cmsPageCategoryId, ['cms_page_category_read']);
        $this->assertEquals(['cmsPageCategoryId' => $cmsPageCategoryId] + $patchData, $fetched);

        return $cmsPageCategoryId;
    }

    /**
     * @depends testPartialUpdateCmsPageCategory
     */
    public function testToggleCmsPageCategoryStatus(int $cmsPageCategoryId): int
    {
        // Category is currently disabled (from previous test), toggle should enable it
        $toggled = $this->updateItem(
            '/cms-page-categories/' . $cmsPageCategoryId . '/toggle-status',
            [],
            ['cms_page_category_write']
        );
        $this->assertTrue($toggled['enabled']);

        // Toggle again — should disable
        $toggled = $this->updateItem(
            '/cms-page-categories/' . $cmsPageCategoryId . '/toggle-status',
            [],
            ['cms_page_category_write']
        );
        $this->assertFalse($toggled['enabled']);

        return $cmsPageCategoryId;
    }

    /**
     * @depends testToggleCmsPageCategoryStatus
     */
    public function testListCmsPageCategories(int $cmsPageCategoryId): int
    {
        $paginated = $this->listItems(
            '/cms-page-categories?orderBy=cmsPageCategoryId&sortOrder=desc',
            ['cms_page_category_read']
        );
        $this->assertGreaterThanOrEqual(1, $paginated['totalItems']);

        // The test category should be first (highest ID)
        $first = $paginated['items'][0];
        $this->assertEquals($cmsPageCategoryId, $first['cmsPageCategoryId']);
        $this->assertArrayHasKey('name', $first);
        $this->assertArrayHasKey('enabled', $first);
        $this->assertArrayHasKey('parentId', $first);
        $this->assertArrayHasKey('position', $first);

        // Filter by ID
        $filtered = $this->listItems('/cms-page-categories', ['cms_page_category_read'], [
            'cmsPageCategoryId' => $cmsPageCategoryId,
        ]);
        $this->assertEquals(1, $filtered['totalItems']);
        $this->assertEquals($cmsPageCategoryId, $filtered['items'][0]['cmsPageCategoryId']);

        return $cmsPageCategoryId;
    }

    /**
     * @depends testListCmsPageCategories
     */
    public function testDeleteCmsPageCategory(int $cmsPageCategoryId): void
    {
        $this->deleteItem('/cms-page-categories/' . $cmsPageCategoryId, ['cms_page_category_write']);

        $this->getItem(
            '/cms-page-categories/' . $cmsPageCategoryId,
            ['cms_page_category_read'],
            Response::HTTP_NOT_FOUND
        );
    }

    public function testBulkEnableDisableCmsPageCategories(): void
    {
        [$id1, $id2] = $this->createTwoCategories();

        // Bulk disable
        $this->updateItem(
            '/cms-page-categories/bulk-disable',
            ['cmsPageCategoryIds' => [$id1, $id2]],
            ['cms_page_category_write'],
            Response::HTTP_NO_CONTENT
        );

        foreach ([$id1, $id2] as $id) {
            $category = $this->getItem('/cms-page-categories/' . $id, ['cms_page_category_read']);
            $this->assertFalse($category['enabled']);
        }

        // Bulk enable
        $this->updateItem(
            '/cms-page-categories/bulk-enable',
            ['cmsPageCategoryIds' => [$id1, $id2]],
            ['cms_page_category_write'],
            Response::HTTP_NO_CONTENT
        );

        foreach ([$id1, $id2] as $id) {
            $category = $this->getItem('/cms-page-categories/' . $id, ['cms_page_category_read']);
            $this->assertTrue($category['enabled']);
        }
    }

    public function testBulkDeleteCmsPageCategories(): void
    {
        [$id1, $id2] = $this->createTwoCategories();

        $this->bulkDeleteItems(
            '/cms-page-categories/bulk-delete',
            ['cmsPageCategoryIds' => [$id1, $id2]],
            ['cms_page_category_write']
        );

        foreach ([$id1, $id2] as $id) {
            $this->getItem('/cms-page-categories/' . $id, ['cms_page_category_read'], Response::HTTP_NOT_FOUND);
        }
    }

    public function testInvalidCmsPageCategory(): void
    {
        // Missing required names (default language) and friendlyUrls
        $invalidData = [
            'names' => [
                'fr-FR' => 'Name with invalid char <',
            ],
            'friendlyUrls' => [
                'fr-FR' => 'invalid url with spaces',
            ],
            'enabled' => true,
            'parentId' => 1,
        ];

        $response = $this->createItem(
            '/cms-page-categories',
            $invalidData,
            ['cms_page_category_write'],
            Response::HTTP_UNPROCESSABLE_ENTITY
        );

        $this->assertValidationErrors([
            [
                'propertyPath' => 'names',
                'message' => 'The field names is required at least in your default language.',
            ],
            [
                'propertyPath' => 'names[fr-FR]',
                'message' => '"Name with invalid char <" is invalid',
            ],
            [
                'propertyPath' => 'friendlyUrls',
                'message' => 'The field friendlyUrls is required at least in your default language.',
            ],
            [
                'propertyPath' => 'friendlyUrls[fr-FR]',
                'message' => '"invalid url with spaces" is invalid',
            ],
        ], $response);
    }

    /**
     * @return int[] Two newly created category IDs
     */
    private function createTwoCategories(): array
    {
        $base = [
            'enabled' => true,
            'parentId' => 1,
            'shopIds' => [1],
            'descriptions' => [],
            'metaTitles' => [],
            'metaDescriptions' => [],
        ];

        $cat1 = $this->createItem('/cms-page-categories', $base + [
            'names' => ['en-US' => 'Bulk Test Cat 1'],
            'friendlyUrls' => ['en-US' => 'bulk-test-cat-1'],
        ], ['cms_page_category_write']);

        $cat2 = $this->createItem('/cms-page-categories', $base + [
            'names' => ['en-US' => 'Bulk Test Cat 2'],
            'friendlyUrls' => ['en-US' => 'bulk-test-cat-2'],
        ], ['cms_page_category_write']);

        return [$cat1['cmsPageCategoryId'], $cat2['cmsPageCategoryId']];
    }
}
