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

class CmsPageCategoryEndpointTest extends ApiTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::resetTables();
        self::createApiClient(['cms_page_category_read', 'cms_page_category_write']);
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
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

        yield 'list endpoint' => [
            'GET',
            '/cms-page-categories',
        ];

        yield 'toggle status endpoint' => ['PUT', '/cms-page-categories/1/toggle-status'];
        yield 'delete endpoint' => ['DELETE', '/cms-page-categories/1'];
        yield 'bulk enable endpoint' => ['PUT', '/cms-page-categories/bulk-enable'];
        yield 'bulk disable endpoint' => ['PUT', '/cms-page-categories/bulk-disable'];
        yield 'bulk delete endpoint' => ['DELETE', '/cms-page-categories/bulk-delete'];
    }

    private function getCreateData(): array
    {
        return [
            'names' => [
                'en-US' => 'Category EN',
                'fr-FR' => 'Category FR',
            ],
            'linkRewrites' => [
                'en-US' => 'category-en',
                'fr-FR' => 'category-fr',
            ],
            'parentId' => 1,
            'displayed' => true,
            'descriptions' => [
                'en-US' => 'Description EN',
                'fr-FR' => 'Description FR',
            ],
            'metaTitles' => [
                'en-US' => 'Meta title EN',
                'fr-FR' => 'Meta title FR',
            ],
            'metaDescriptions' => [
                'en-US' => '',
                'fr-FR' => '',
            ],
            'shopIds' => [1],
        ];
    }

    /**
     * The payload the API returns for a category created with getCreateData()
     */
    private function expectedCmsPageCategory(int $cmsPageCategoryId, array $overrides = []): array
    {
        return array_replace(['cmsPageCategoryId' => $cmsPageCategoryId], $this->getCreateData(), $overrides);
    }

    public function testAddCmsPageCategory(): int
    {
        $cmsPageCategory = $this->createItem('/cms-page-categories', $this->getCreateData(), ['cms_page_category_write']);
        $this->assertIsInt($cmsPageCategory['cmsPageCategoryId']);
        $cmsPageCategoryId = $cmsPageCategory['cmsPageCategoryId'];
        $this->assertEquals($this->expectedCmsPageCategory($cmsPageCategoryId), $cmsPageCategory);

        return $cmsPageCategoryId;
    }

    /**
     * @depends testAddCmsPageCategory
     */
    public function testGetCmsPageCategory(int $cmsPageCategoryId): int
    {
        $cmsPageCategory = $this->getItem('/cms-page-categories/' . $cmsPageCategoryId, ['cms_page_category_read']);
        $this->assertEquals($this->expectedCmsPageCategory($cmsPageCategoryId), $cmsPageCategory);

        return $cmsPageCategoryId;
    }

    /**
     * @depends testGetCmsPageCategory
     */
    public function testPartialUpdateCmsPageCategory(int $cmsPageCategoryId): int
    {
        $patchData = [
            'names' => [
                'en-US' => 'Updated category EN',
                'fr-FR' => 'Updated category FR',
            ],
            'displayed' => false,
        ];

        // The fields and languages not sent are untouched
        $updatedCmsPageCategory = $this->partialUpdateItem('/cms-page-categories/' . $cmsPageCategoryId, $patchData, ['cms_page_category_write']);
        $this->assertEquals($this->expectedCmsPageCategory($cmsPageCategoryId, $patchData), $updatedCmsPageCategory);

        // We check that when we GET the item it is updated as expected
        $cmsPageCategory = $this->getItem('/cms-page-categories/' . $cmsPageCategoryId, ['cms_page_category_read']);
        $this->assertEquals($this->expectedCmsPageCategory($cmsPageCategoryId, $patchData), $cmsPageCategory);

        return $cmsPageCategoryId;
    }

    /**
     * @depends testPartialUpdateCmsPageCategory
     */
    public function testToggleStatusCmsPageCategory(int $cmsPageCategoryId): int
    {
        // Status is currently false (set by the partial update), toggling it should enable it back.
        // The toggle replays the query of the GET, so it answers with the entity it flipped.
        $expectedCmsPageCategory = $this->expectedCmsPageCategory($cmsPageCategoryId, [
            'names' => [
                'en-US' => 'Updated category EN',
                'fr-FR' => 'Updated category FR',
            ],
            'displayed' => true,
        ]);
        $toggled = $this->updateItem('/cms-page-categories/' . $cmsPageCategoryId . '/toggle-status', [], ['cms_page_category_write']);
        $this->assertEquals($expectedCmsPageCategory, $toggled);

        $cmsPageCategory = $this->getItem('/cms-page-categories/' . $cmsPageCategoryId, ['cms_page_category_read']);
        $this->assertEquals($expectedCmsPageCategory, $cmsPageCategory);

        return $cmsPageCategoryId;
    }

    /**
     * @depends testToggleStatusCmsPageCategory
     */
    public function testListCmsPageCategories(int $cmsPageCategoryId): int
    {
        $paginatedCmsPageCategories = $this->listItems('/cms-page-categories?orderBy=cmsPageCategoryId&sortOrder=desc', ['cms_page_category_read']);
        $this->assertGreaterThanOrEqual(1, $paginatedCmsPageCategories['totalItems']);
        $this->assertEquals('cmsPageCategoryId', $paginatedCmsPageCategories['orderBy']);

        $firstCmsPageCategory = $paginatedCmsPageCategories['items'][0];
        $this->assertEquals(
            [
                'cmsPageCategoryId' => $cmsPageCategoryId,
                'name' => 'Updated category EN',
                'description' => 'Description EN',
                'parentId' => 1,
                'displayed' => true,
                'position' => 0,
            ],
            $firstCmsPageCategory
        );

        return $cmsPageCategoryId;
    }

    /**
     * @depends testListCmsPageCategories
     */
    public function testDeleteCmsPageCategory(int $cmsPageCategoryId): void
    {
        $return = $this->deleteItem('/cms-page-categories/' . $cmsPageCategoryId, ['cms_page_category_write']);
        // This endpoint returns an empty response and a 204 HTTP code
        $this->assertNull($return);

        // Getting the item should result in a 404 now
        $this->getItem('/cms-page-categories/' . $cmsPageCategoryId, ['cms_page_category_read'], Response::HTTP_NOT_FOUND);
    }

    /**
     * @depends testDeleteCmsPageCategory
     */
    public function testBulkUpdateStatusCmsPageCategories(): void
    {
        $bulkIds = $this->createSeveral(['Bulk status A', 'Bulk status B']);

        // Disable both
        $this->updateItem('/cms-page-categories/bulk-disable', [
            'cmsPageCategoryIds' => $bulkIds,
        ], ['cms_page_category_write'], Response::HTTP_NO_CONTENT);
        foreach ($bulkIds as $id) {
            $this->assertFalse($this->getItem('/cms-page-categories/' . $id, ['cms_page_category_read'])['displayed']);
        }

        // Enable both back
        $this->updateItem('/cms-page-categories/bulk-enable', [
            'cmsPageCategoryIds' => $bulkIds,
        ], ['cms_page_category_write'], Response::HTTP_NO_CONTENT);
        foreach ($bulkIds as $id) {
            $this->assertTrue($this->getItem('/cms-page-categories/' . $id, ['cms_page_category_read'])['displayed']);
        }
    }

    /**
     * @depends testBulkUpdateStatusCmsPageCategories
     */
    public function testBulkDeleteCmsPageCategories(): void
    {
        $bulkIds = $this->createSeveral(['Bulk delete A', 'Bulk delete B']);

        $this->bulkDeleteItems('/cms-page-categories/bulk-delete', [
            'cmsPageCategoryIds' => $bulkIds,
        ], ['cms_page_category_write']);

        foreach ($bulkIds as $id) {
            $this->getItem('/cms-page-categories/' . $id, ['cms_page_category_read'], Response::HTTP_NOT_FOUND);
        }
    }

    public function testBulkActionsWithInvalidCmsPageCategoryId(): void
    {
        // The constraints of cmsPageCategoryIds reject the invalid ids before the command is built
        $invalidIds = [
            [[0], 'cmsPageCategoryIds[0]', 'This value should be positive.'],
            [['3abc'], 'cmsPageCategoryIds[0]', 'This value should be of type integer.'],
            [[], 'cmsPageCategoryIds', 'This value should not be blank.'],
        ];
        foreach ($invalidIds as [$ids, $propertyPath, $message]) {
            $expectedErrors = [['propertyPath' => $propertyPath, 'message' => $message]];
            $this->assertValidationErrors($expectedErrors, $this->bulkDeleteItems('/cms-page-categories/bulk-delete', [
                'cmsPageCategoryIds' => $ids,
            ], ['cms_page_category_write'], Response::HTTP_UNPROCESSABLE_ENTITY));

            foreach (['bulk-enable', 'bulk-disable'] as $action) {
                $this->assertValidationErrors($expectedErrors, $this->updateItem('/cms-page-categories/' . $action, [
                    'cmsPageCategoryIds' => $ids,
                ], ['cms_page_category_write'], Response::HTTP_UNPROCESSABLE_ENTITY));
            }
        }
    }

    public function testBulkActionsWithUnknownCmsPageCategory(): void
    {
        // The handlers stop at the first unknown category: the categories listed before it are already changed
        [$firstId, $lastId] = $this->createSeveral(['Bulk unknown A', 'Bulk unknown B']);

        $this->updateItem('/cms-page-categories/bulk-disable', [
            'cmsPageCategoryIds' => [$firstId, 999999, $lastId],
        ], ['cms_page_category_write'], Response::HTTP_NOT_FOUND);
        $this->assertFalse($this->getItem('/cms-page-categories/' . $firstId, ['cms_page_category_read'])['displayed']);
        $this->assertTrue($this->getItem('/cms-page-categories/' . $lastId, ['cms_page_category_read'])['displayed']);

        $this->updateItem('/cms-page-categories/bulk-enable', [
            'cmsPageCategoryIds' => [$firstId, 999999],
        ], ['cms_page_category_write'], Response::HTTP_NOT_FOUND);
        $this->assertTrue($this->getItem('/cms-page-categories/' . $firstId, ['cms_page_category_read'])['displayed']);

        $this->bulkDeleteItems('/cms-page-categories/bulk-delete', [
            'cmsPageCategoryIds' => [$firstId, 999999, $lastId],
        ], ['cms_page_category_write'], Response::HTTP_NOT_FOUND);
        $this->getItem('/cms-page-categories/' . $firstId, ['cms_page_category_read'], Response::HTTP_NOT_FOUND);
        $this->getItem('/cms-page-categories/' . $lastId, ['cms_page_category_read']);
    }

    public function testInvalidCmsPageCategory(): void
    {
        $invalidData = $this->getCreateData();
        $invalidData['names'] = [
            'fr-FR' => 'Nom FR uniquement',
        ];
        $invalidData['linkRewrites'] = [
            'fr-FR' => 'nom-fr-uniquement',
        ];

        $validationErrorsResponse = $this->createItem('/cms-page-categories', $invalidData, ['cms_page_category_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertIsArray($validationErrorsResponse);

        $this->assertValidationErrors([
            [
                'propertyPath' => 'names',
                'message' => 'The field names is required at least in your default language.',
            ],
            [
                'propertyPath' => 'linkRewrites',
                'message' => 'The field linkRewrites is required at least in your default language.',
            ],
        ], $validationErrorsResponse);
    }

    public function testInvalidCmsPageCategoryParent(): void
    {
        $data = $this->getCreateData();
        unset($data['parentId']);
        $this->assertValidationErrors([
            ['propertyPath' => 'parentId', 'message' => 'This value should not be null.'],
        ], $this->createItem('/cms-page-categories', $data, ['cms_page_category_write'], Response::HTTP_UNPROCESSABLE_ENTITY));

        $data['parentId'] = 0;
        $this->assertValidationErrors([
            ['propertyPath' => 'parentId', 'message' => 'This value should be positive.'],
        ], $this->createItem('/cms-page-categories', $data, ['cms_page_category_write'], Response::HTTP_UNPROCESSABLE_ENTITY));
    }

    public function testInvalidPartialUpdateCmsPageCategory(): void
    {
        [$cmsPageCategoryId] = $this->createSeveral(['Invalid update category']);

        // The Update group validates the PATCH: the default language cannot be emptied, the parent and the shops
        // are checked like on the creation
        $validationErrorsResponse = $this->partialUpdateItem('/cms-page-categories/' . $cmsPageCategoryId, [
            'names' => ['en-US' => ''],
            'parentId' => 0,
            'shopIds' => ['3abc'],
        ], ['cms_page_category_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertValidationErrors([
            ['propertyPath' => 'names', 'message' => 'The field names is required at least in your default language.'],
            ['propertyPath' => 'parentId', 'message' => 'This value should be positive.'],
            ['propertyPath' => 'shopIds[0]', 'message' => 'This value should be of type integer.'],
        ], $validationErrorsResponse);

        // Nothing was changed
        $this->assertSame(
            ['en-US' => 'Invalid update category', 'fr-FR' => 'Invalid update category'],
            $this->getItem('/cms-page-categories/' . $cmsPageCategoryId, ['cms_page_category_read'])['names']
        );
    }

    public function testInvalidCmsPageCategoryIds(): void
    {
        // CmsPageCategoryId rejects 0 with the base exception (422), an unknown category answers 404
        foreach ([999999 => Response::HTTP_NOT_FOUND, 0 => Response::HTTP_UNPROCESSABLE_ENTITY] as $categoryId => $status) {
            $this->getItem('/cms-page-categories/' . $categoryId, ['cms_page_category_read'], $status);
            $this->partialUpdateItem('/cms-page-categories/' . $categoryId, ['displayed' => true], ['cms_page_category_write'], $status);
            $this->updateItem('/cms-page-categories/' . $categoryId . '/toggle-status', [], ['cms_page_category_write'], $status);
            $this->deleteItem('/cms-page-categories/' . $categoryId, ['cms_page_category_write'], $status);
        }
    }

    public function testAddCmsPageCategoryWithUnknownParent(): void
    {
        $data = $this->getCreateData();
        $data['parentId'] = 999999;

        if (self::isVersionUnder('9.2.1')) {
            // The core checks the parent from PrestaShop 9.2.1, older versions store the unknown parent
            $this->assertSame(999999, $this->createItem('/cms-page-categories', $data, ['cms_page_category_write'])['parentId']);

            return;
        }

        $this->createItem('/cms-page-categories', $data, ['cms_page_category_write'], Response::HTTP_NOT_FOUND);
    }

    public function testAddCmsPageCategoryWithDefaultValues(): void
    {
        $data = [
            'names' => ['en-US' => 'Default category EN'],
            'linkRewrites' => ['en-US' => 'default-category-en'],
            'parentId' => 1,
            'shopIds' => [1],
        ];

        if (self::isVersionUnder('9.2.0')) {
            // The defaults are applied from PrestaShop 9.2.0, the fields are required before
            $validationErrorsResponse = $this->createItem('/cms-page-categories', $data, ['cms_page_category_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
            $this->assertValidationErrors([
                ['propertyPath' => 'displayed', 'message' => 'This value should not be null.'],
                ['propertyPath' => 'descriptions', 'message' => 'This value should not be null.'],
            ], $validationErrorsResponse);

            return;
        }

        $cmsPageCategory = $this->createItem('/cms-page-categories', $data, ['cms_page_category_write']);
        $empty = ['en-US' => '', 'fr-FR' => ''];
        $this->assertEquals([
            'cmsPageCategoryId' => $cmsPageCategory['cmsPageCategoryId'],
            // The missing languages of the required fields take the value of the default language
            'names' => ['en-US' => 'Default category EN', 'fr-FR' => 'Default category EN'],
            'linkRewrites' => ['en-US' => 'default-category-en', 'fr-FR' => 'default-category-en'],
            'parentId' => 1,
            'displayed' => true,
            'descriptions' => $empty,
            'metaTitles' => $empty,
            'metaDescriptions' => $empty,
            'shopIds' => [1],
        ], $cmsPageCategory);
    }

    public function testPartialUpdateOneLanguageReplacesTheOthers(): void
    {
        [$cmsPageCategoryId] = $this->createSeveral(['One language category']);

        // The edit handler replaces every language of a localized field: the missing one becomes empty
        $cmsPageCategory = $this->partialUpdateItem('/cms-page-categories/' . $cmsPageCategoryId, [
            'descriptions' => ['en-US' => 'Patched EN'],
        ], ['cms_page_category_write']);
        $this->assertSame(['en-US' => 'Patched EN', 'fr-FR' => ''], $cmsPageCategory['descriptions']);
        // The fields not sent keep their languages
        $this->assertSame($this->getCreateData()['metaTitles'], $cmsPageCategory['metaTitles']);
    }

    public function testDeleteCmsPageCategoryDeletesItsSubcategories(): void
    {
        [$parentId] = $this->createSeveral(['Cascade parent']);
        $data = $this->getCreateData();
        $data['parentId'] = $parentId;
        $data['linkRewrites'] = ['en-US' => 'cascade-child-en', 'fr-FR' => 'cascade-child-fr'];
        $childId = $this->createItem('/cms-page-categories', $data, ['cms_page_category_write'])['cmsPageCategoryId'];

        $this->deleteItem('/cms-page-categories/' . $parentId, ['cms_page_category_write']);
        $this->getItem('/cms-page-categories/' . $childId, ['cms_page_category_read'], Response::HTTP_NOT_FOUND);
    }

    public function testGetRootCmsPageCategory(): void
    {
        // The core builds the root category without parent from PrestaShop 9.2.1 (PrestaShop/PrestaShop#43269),
        // reading it answered 500 before
        $this->markTestSkippedByMinVersion('9.2.1');

        $rootCategory = $this->getItem('/cms-page-categories/1', ['cms_page_category_read']);
        $this->assertSame(1, $rootCategory['cmsPageCategoryId']);
        $this->assertArrayHasKey('parentId', $rootCategory);
        $this->assertNull($rootCategory['parentId']);

        // The list returns null too, while the grid gives 0
        $list = $this->listItems('/cms-page-categories', ['cms_page_category_read'], ['cmsPageCategoryId' => 1]);
        $this->assertSame(1, $list['totalItems']);
        $this->assertArrayHasKey('parentId', $list['items'][0]);
        $this->assertNull($list['items'][0]['parentId']);
    }

    public function testDeleteRootCmsPageCategory(): void
    {
        // The root category cannot be deleted: CannotDeleteCmsPageCategoryException extends the base exception
        $this->deleteItem('/cms-page-categories/1', ['cms_page_category_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testAddCmsPageCategoryInContextShops(): void
    {
        $this->markTestSkippedByMinVersion('9.2.0');

        $data = $this->getCreateData();
        unset($data['shopIds']);
        $data['linkRewrites'] = ['en-US' => 'context-shops-en', 'fr-FR' => 'context-shops-fr'];

        $cmsPageCategory = $this->createItem('/cms-page-categories', $data, ['cms_page_category_write']);
        $this->assertSame([1], $cmsPageCategory['shopIds']);
    }

    public function testInvalidCmsPageCategoryShopIds(): void
    {
        $data = $this->getCreateData();
        $data['shopIds'] = ['3abc'];

        $validationErrorsResponse = $this->createItem('/cms-page-categories', $data, ['cms_page_category_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertValidationErrors([
            [
                'propertyPath' => 'shopIds[0]',
                'message' => 'This value should be of type integer.',
            ],
        ], $validationErrorsResponse);
    }

    /**
     * @param string[] $names
     *
     * @return int[]
     */
    private function createSeveral(array $names): array
    {
        $ids = [];
        foreach ($names as $index => $name) {
            $data = $this->getCreateData();
            $data['names'] = [
                'en-US' => $name,
                'fr-FR' => $name,
            ];
            $data['linkRewrites'] = [
                'en-US' => 'bulk-' . $index . '-en',
                'fr-FR' => 'bulk-' . $index . '-fr',
            ];
            $created = $this->createItem('/cms-page-categories', $data, ['cms_page_category_write']);
            $ids[] = $created['cmsPageCategoryId'];
        }

        return $ids;
    }
}
