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

class CmsPageEndpointTest extends ApiTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::resetTables();
        self::createApiClient(['cms_page_read', 'cms_page_write']);
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        self::resetTables();
    }

    protected static function resetTables(): void
    {
        DatabaseDump::restoreTables([
            'cms',
            'cms_lang',
            'cms_shop',
        ]);
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'get endpoint' => [
            'GET',
            '/cms-pages/1',
        ];

        yield 'create endpoint' => [
            'POST',
            '/cms-pages',
        ];

        yield 'patch endpoint' => [
            'PATCH',
            '/cms-pages/1',
        ];

        yield 'list endpoint' => [
            'GET',
            '/cms-pages',
        ];
    }

    private function getCreateData(): array
    {
        return [
            'cmsPageCategoryId' => 1,
            'titles' => [
                'en-US' => 'Page EN',
                'fr-FR' => 'Page FR',
            ],
            'metaTitles' => [
                'en-US' => 'Meta title EN',
                'fr-FR' => 'Meta title FR',
            ],
            'metaDescriptions' => [
                'en-US' => '',
                'fr-FR' => '',
            ],
            'linkRewrites' => [
                'en-US' => 'page-en',
                'fr-FR' => 'page-fr',
            ],
            'contents' => [
                'en-US' => '<p>Content EN</p>',
                'fr-FR' => '<p>Content FR</p>',
            ],
            'indexedForSearch' => true,
            'displayed' => true,
            'shopIds' => [1],
        ];
    }

    public function testAddCmsPage(): int
    {
        $cmsPage = $this->createItem('/cms-pages', $this->getCreateData(), ['cms_page_write']);
        $this->assertArrayHasKey('cmsPageId', $cmsPage);
        $cmsPageId = $cmsPage['cmsPageId'];

        $this->assertSame($this->getCreateData()['titles'], $cmsPage['titles']);
        $this->assertSame($this->getCreateData()['linkRewrites'], $cmsPage['linkRewrites']);
        $this->assertEquals(1, $cmsPage['cmsPageCategoryId']);
        $this->assertTrue($cmsPage['displayed']);

        return $cmsPageId;
    }

    /**
     * @depends testAddCmsPage
     */
    public function testGetCmsPage(int $cmsPageId): int
    {
        $cmsPage = $this->getItem('/cms-pages/' . $cmsPageId, ['cms_page_read']);
        $this->assertEquals($cmsPageId, $cmsPage['cmsPageId']);
        $this->assertArrayHasKey('titles', $cmsPage);
        $this->assertArrayHasKey('linkRewrites', $cmsPage);
        $this->assertEquals(1, $cmsPage['cmsPageCategoryId']);

        return $cmsPageId;
    }

    /**
     * @depends testGetCmsPage
     */
    public function testPartialUpdateCmsPage(int $cmsPageId): int
    {
        $patchData = [
            'titles' => [
                'en-US' => 'Updated page EN',
                'fr-FR' => 'Updated page FR',
            ],
            'displayed' => false,
        ];

        $updatedCmsPage = $this->partialUpdateItem('/cms-pages/' . $cmsPageId, $patchData, ['cms_page_write']);
        $this->assertSame($patchData['titles'], $updatedCmsPage['titles']);
        $this->assertFalse($updatedCmsPage['displayed']);

        // We check that when we GET the item it is updated as expected
        $cmsPage = $this->getItem('/cms-pages/' . $cmsPageId, ['cms_page_read']);
        $this->assertSame($patchData['titles'], $cmsPage['titles']);
        $this->assertFalse($cmsPage['displayed']);

        return $cmsPageId;
    }

    /**
     * @depends testPartialUpdateCmsPage
     */
    public function testToggleStatusCmsPage(int $cmsPageId): int
    {
        // Status is currently false (set by the partial update), toggling it should enable it back
        $this->updateItem('/cms-pages/' . $cmsPageId . '/toggle-status', [], ['cms_page_write'], Response::HTTP_NO_CONTENT);

        $cmsPage = $this->getItem('/cms-pages/' . $cmsPageId, ['cms_page_read']);
        $this->assertTrue($cmsPage['displayed']);

        return $cmsPageId;
    }

    /**
     * @depends testToggleStatusCmsPage
     */
    public function testListCmsPages(int $cmsPageId): int
    {
        $paginatedCmsPages = $this->listItems('/cms-pages?orderBy=cmsPageId&sortOrder=desc', ['cms_page_read']);
        $this->assertGreaterThanOrEqual(1, $paginatedCmsPages['totalItems']);
        $this->assertEquals('cmsPageId', $paginatedCmsPages['orderBy']);

        $firstCmsPage = $paginatedCmsPages['items'][0];
        $this->assertEquals($cmsPageId, $firstCmsPage['cmsPageId']);

        return $cmsPageId;
    }

    /**
     * @depends testListCmsPages
     */
    public function testDeleteCmsPage(int $cmsPageId): void
    {
        $return = $this->deleteItem('/cms-pages/' . $cmsPageId, ['cms_page_write']);
        // This endpoint returns an empty response and a 204 HTTP code
        $this->assertNull($return);

        // Getting the item should result in a 404 now
        $this->getItem('/cms-pages/' . $cmsPageId, ['cms_page_read'], Response::HTTP_NOT_FOUND);
    }

    /**
     * @depends testDeleteCmsPage
     */
    public function testBulkUpdateStatusCmsPages(): void
    {
        $bulkIds = $this->createSeveral(['Bulk status A', 'Bulk status B']);

        // Disable both
        $this->updateItem('/cms-pages/bulk-disable', [
            'cmsPageIds' => $bulkIds,
        ], ['cms_page_write'], Response::HTTP_NO_CONTENT);
        foreach ($bulkIds as $id) {
            $this->assertFalse($this->getItem('/cms-pages/' . $id, ['cms_page_read'])['displayed']);
        }

        // Enable both back
        $this->updateItem('/cms-pages/bulk-enable', [
            'cmsPageIds' => $bulkIds,
        ], ['cms_page_write'], Response::HTTP_NO_CONTENT);
        foreach ($bulkIds as $id) {
            $this->assertTrue($this->getItem('/cms-pages/' . $id, ['cms_page_read'])['displayed']);
        }
    }

    /**
     * @depends testBulkUpdateStatusCmsPages
     */
    public function testBulkDeleteCmsPages(): void
    {
        $bulkIds = $this->createSeveral(['Bulk delete A', 'Bulk delete B']);

        $this->bulkDeleteItems('/cms-pages/bulk-delete', [
            'cmsPageIds' => $bulkIds,
        ], ['cms_page_write']);

        foreach ($bulkIds as $id) {
            $this->getItem('/cms-pages/' . $id, ['cms_page_read'], Response::HTTP_NOT_FOUND);
        }
    }

    public function testInvalidCmsPage(): void
    {
        $invalidData = $this->getCreateData();
        $invalidData['titles'] = [
            'fr-FR' => 'Titre FR uniquement',
        ];
        $invalidData['linkRewrites'] = [
            'fr-FR' => 'titre-fr-uniquement',
        ];

        $validationErrorsResponse = $this->createItem('/cms-pages', $invalidData, ['cms_page_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertIsArray($validationErrorsResponse);

        $this->assertValidationErrors([
            [
                'propertyPath' => 'titles',
                'message' => 'The field titles is required at least in your default language.',
            ],
            [
                'propertyPath' => 'linkRewrites',
                'message' => 'The field linkRewrites is required at least in your default language.',
            ],
        ], $validationErrorsResponse);
    }

    /**
     * @param string[] $titles
     *
     * @return int[]
     */
    private function createSeveral(array $titles): array
    {
        $ids = [];
        foreach ($titles as $index => $title) {
            $data = $this->getCreateData();
            $data['titles'] = [
                'en-US' => $title,
                'fr-FR' => $title,
            ];
            $data['linkRewrites'] = [
                'en-US' => 'bulk-' . $index . '-en',
                'fr-FR' => 'bulk-' . $index . '-fr',
            ];
            $created = $this->createItem('/cms-pages', $data, ['cms_page_write']);
            $ids[] = $created['cmsPageId'];
        }

        return $ids;
    }
}
