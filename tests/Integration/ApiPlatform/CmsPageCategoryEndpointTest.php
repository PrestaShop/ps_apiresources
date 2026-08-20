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

    public function testAddCmsPageCategory(): int
    {
        $cmsPageCategory = $this->createItem('/cms-page-categories', $this->getCreateData(), ['cms_page_category_write']);
        $this->assertArrayHasKey('cmsPageCategoryId', $cmsPageCategory);
        $cmsPageCategoryId = $cmsPageCategory['cmsPageCategoryId'];

        $this->assertSame($this->getCreateData()['names'], $cmsPageCategory['names']);
        $this->assertSame($this->getCreateData()['linkRewrites'], $cmsPageCategory['linkRewrites']);
        $this->assertEquals(1, $cmsPageCategory['parentId']);
        $this->assertTrue($cmsPageCategory['displayed']);

        return $cmsPageCategoryId;
    }

    /**
     * @depends testAddCmsPageCategory
     */
    public function testGetCmsPageCategory(int $cmsPageCategoryId): int
    {
        $cmsPageCategory = $this->getItem('/cms-page-categories/' . $cmsPageCategoryId, ['cms_page_category_read']);
        $this->assertEquals($cmsPageCategoryId, $cmsPageCategory['cmsPageCategoryId']);
        $this->assertArrayHasKey('names', $cmsPageCategory);
        $this->assertArrayHasKey('linkRewrites', $cmsPageCategory);
        $this->assertEquals(1, $cmsPageCategory['parentId']);

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

        $updatedCmsPageCategory = $this->partialUpdateItem('/cms-page-categories/' . $cmsPageCategoryId, $patchData, ['cms_page_category_write']);
        $this->assertSame($patchData['names'], $updatedCmsPageCategory['names']);
        $this->assertFalse($updatedCmsPageCategory['displayed']);

        // We check that when we GET the item it is updated as expected
        $cmsPageCategory = $this->getItem('/cms-page-categories/' . $cmsPageCategoryId, ['cms_page_category_read']);
        $this->assertSame($patchData['names'], $cmsPageCategory['names']);
        $this->assertFalse($cmsPageCategory['displayed']);

        return $cmsPageCategoryId;
    }

    /**
     * @depends testPartialUpdateCmsPageCategory
     */
    public function testToggleStatusCmsPageCategory(int $cmsPageCategoryId): int
    {
        // Status is currently false (set by the partial update), toggling it should enable it back
        $this->updateItem('/cms-page-categories/' . $cmsPageCategoryId . '/toggle-status', [], ['cms_page_category_write'], Response::HTTP_NO_CONTENT);

        $cmsPageCategory = $this->getItem('/cms-page-categories/' . $cmsPageCategoryId, ['cms_page_category_read']);
        $this->assertTrue($cmsPageCategory['displayed']);

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
        $this->assertEquals($cmsPageCategoryId, $firstCmsPageCategory['cmsPageCategoryId']);

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
