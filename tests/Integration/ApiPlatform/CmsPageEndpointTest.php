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

        yield 'toggle status endpoint' => ['PUT', '/cms-pages/1/toggle-status'];
        yield 'delete endpoint' => ['DELETE', '/cms-pages/1'];
        yield 'bulk enable endpoint' => ['PUT', '/cms-pages/bulk-enable'];
        yield 'bulk disable endpoint' => ['PUT', '/cms-pages/bulk-disable'];
        yield 'bulk delete endpoint' => ['DELETE', '/cms-pages/bulk-delete'];
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

    /**
     * The payload the API returns for a page created with getCreateData()
     */
    private function expectedCmsPage(int $cmsPageId, array $overrides = []): array
    {
        return array_replace(['cmsPageId' => $cmsPageId], $this->getCreateData(), $overrides);
    }

    public function testAddCmsPage(): int
    {
        $cmsPage = $this->createItem('/cms-pages', $this->getCreateData(), ['cms_page_write']);
        $this->assertIsInt($cmsPage['cmsPageId']);
        $cmsPageId = $cmsPage['cmsPageId'];
        $this->assertEquals($this->expectedCmsPage($cmsPageId), $cmsPage);

        return $cmsPageId;
    }

    /**
     * @depends testAddCmsPage
     */
    public function testGetCmsPage(int $cmsPageId): int
    {
        $cmsPage = $this->getItem('/cms-pages/' . $cmsPageId, ['cms_page_read']);
        $this->assertEquals($this->expectedCmsPage($cmsPageId), $cmsPage);

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

        // The fields and languages not sent are untouched
        $updatedCmsPage = $this->partialUpdateItem('/cms-pages/' . $cmsPageId, $patchData, ['cms_page_write']);
        $this->assertEquals($this->expectedCmsPage($cmsPageId, $patchData), $updatedCmsPage);

        // We check that when we GET the item it is updated as expected
        $cmsPage = $this->getItem('/cms-pages/' . $cmsPageId, ['cms_page_read']);
        $this->assertEquals($this->expectedCmsPage($cmsPageId, $patchData), $cmsPage);

        return $cmsPageId;
    }

    /**
     * @depends testPartialUpdateCmsPage
     */
    public function testToggleStatusCmsPage(int $cmsPageId): int
    {
        // Status is currently false (set by the partial update), toggling it should enable it back.
        // The toggle replays the query of the GET, so it answers with the entity it flipped.
        $expectedCmsPage = $this->expectedCmsPage($cmsPageId, [
            'titles' => [
                'en-US' => 'Updated page EN',
                'fr-FR' => 'Updated page FR',
            ],
            'displayed' => true,
        ]);
        $toggled = $this->updateItem('/cms-pages/' . $cmsPageId . '/toggle-status', [], ['cms_page_write']);
        $this->assertEquals($expectedCmsPage, $toggled);

        $cmsPage = $this->getItem('/cms-pages/' . $cmsPageId, ['cms_page_read']);
        $this->assertEquals($expectedCmsPage, $cmsPage);

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
        // The position depends on the pages the fixtures ship, the rest is the page this class created
        $this->assertIsInt($firstCmsPage['position']);
        $this->assertEquals(
            [
                'cmsPageId' => $cmsPageId,
                'title' => 'Updated page EN',
                'linkRewrite' => 'page-en',
                'metaTitle' => 'Meta title EN',
                'cmsPageCategoryId' => 1,
                'displayed' => true,
            ],
            array_diff_key($firstCmsPage, ['position' => true])
        );

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

    public function testBulkActionsWithInvalidCmsPageId(): void
    {
        // The constraints of cmsPageIds reject the invalid ids before the command is built
        $invalidIds = [
            [[0], 'cmsPageIds[0]', 'This value should be positive.'],
            [['3abc'], 'cmsPageIds[0]', 'This value should be of type integer.'],
            [[], 'cmsPageIds', 'This value should not be blank.'],
        ];
        foreach ($invalidIds as [$ids, $propertyPath, $message]) {
            $expectedErrors = [['propertyPath' => $propertyPath, 'message' => $message]];
            $this->assertValidationErrors($expectedErrors, $this->bulkDeleteItems('/cms-pages/bulk-delete', [
                'cmsPageIds' => $ids,
            ], ['cms_page_write'], Response::HTTP_UNPROCESSABLE_ENTITY));

            foreach (['bulk-enable', 'bulk-disable'] as $action) {
                $this->assertValidationErrors($expectedErrors, $this->updateItem('/cms-pages/' . $action, [
                    'cmsPageIds' => $ids,
                ], ['cms_page_write'], Response::HTTP_UNPROCESSABLE_ENTITY));
            }
        }
    }

    public function testBulkActionsWithUnknownCmsPage(): void
    {
        // The handlers stop at the first unknown page: the pages listed before it are already changed
        [$firstId, $lastId] = $this->createSeveral(['Bulk unknown A', 'Bulk unknown B']);

        $this->updateItem('/cms-pages/bulk-disable', [
            'cmsPageIds' => [$firstId, 999999, $lastId],
        ], ['cms_page_write'], Response::HTTP_NOT_FOUND);
        $this->assertFalse($this->getItem('/cms-pages/' . $firstId, ['cms_page_read'])['displayed']);
        $this->assertTrue($this->getItem('/cms-pages/' . $lastId, ['cms_page_read'])['displayed']);

        $this->updateItem('/cms-pages/bulk-enable', [
            'cmsPageIds' => [$firstId, 999999],
        ], ['cms_page_write'], Response::HTTP_NOT_FOUND);
        $this->assertTrue($this->getItem('/cms-pages/' . $firstId, ['cms_page_read'])['displayed']);

        $this->bulkDeleteItems('/cms-pages/bulk-delete', [
            'cmsPageIds' => [$firstId, 999999, $lastId],
        ], ['cms_page_write'], Response::HTTP_NOT_FOUND);
        $this->getItem('/cms-pages/' . $firstId, ['cms_page_read'], Response::HTTP_NOT_FOUND);
        $this->getItem('/cms-pages/' . $lastId, ['cms_page_read']);
    }

    public function testInvalidPartialUpdateCmsPage(): void
    {
        [$cmsPageId] = $this->createSeveral(['Invalid update page']);

        // The Update group validates the PATCH: the default language cannot be emptied, the category and the shops
        // are checked like on the creation
        $validationErrorsResponse = $this->partialUpdateItem('/cms-pages/' . $cmsPageId, [
            'titles' => ['en-US' => ''],
            'cmsPageCategoryId' => 0,
            'shopIds' => ['3abc'],
        ], ['cms_page_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertValidationErrors([
            ['propertyPath' => 'cmsPageCategoryId', 'message' => 'This value should be positive.'],
            ['propertyPath' => 'titles', 'message' => 'The field titles is required at least in your default language.'],
            ['propertyPath' => 'shopIds[0]', 'message' => 'This value should be of type integer.'],
        ], $validationErrorsResponse);

        // Nothing was changed
        $this->assertSame(
            ['en-US' => 'Invalid update page', 'fr-FR' => 'Invalid update page'],
            $this->getItem('/cms-pages/' . $cmsPageId, ['cms_page_read'])['titles']
        );
    }

    public function testUnknownAndInvalidCmsPageIds(): void
    {
        // CmsPageId rejects 0 with the base exception (422), an unknown page answers 404
        foreach ([999999 => Response::HTTP_NOT_FOUND, 0 => Response::HTTP_UNPROCESSABLE_ENTITY] as $cmsPageId => $status) {
            $this->getItem('/cms-pages/' . $cmsPageId, ['cms_page_read'], $status);
            $this->partialUpdateItem('/cms-pages/' . $cmsPageId, ['displayed' => true], ['cms_page_write'], $status);
            $this->updateItem('/cms-pages/' . $cmsPageId . '/toggle-status', [], ['cms_page_write'], $status);
            $this->deleteItem('/cms-pages/' . $cmsPageId, ['cms_page_write'], $status);
        }
    }

    public function testAddCmsPageWithDefaultValues(): void
    {
        $data = [
            'cmsPageCategoryId' => 1,
            'titles' => ['en-US' => 'Default page EN'],
            'linkRewrites' => ['en-US' => 'default-page-en'],
            'shopIds' => [1],
        ];

        if (self::isVersionUnder('9.2.0')) {
            // The defaults are applied from PrestaShop 9.2.0, the fields are required before
            $validationErrorsResponse = $this->createItem('/cms-pages', $data, ['cms_page_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
            $this->assertValidationErrors(array_map(
                fn (string $field) => ['propertyPath' => $field, 'message' => 'This value should not be null.'],
                ['metaTitles', 'metaDescriptions', 'contents', 'indexedForSearch', 'displayed']
            ), $validationErrorsResponse);

            return;
        }

        $cmsPage = $this->createItem('/cms-pages', $data, ['cms_page_write']);
        $empty = ['en-US' => '', 'fr-FR' => ''];
        $this->assertEquals([
            'cmsPageId' => $cmsPage['cmsPageId'],
            'cmsPageCategoryId' => 1,
            // The missing languages of the required fields take the value of the default language
            'titles' => ['en-US' => 'Default page EN', 'fr-FR' => 'Default page EN'],
            'linkRewrites' => ['en-US' => 'default-page-en', 'fr-FR' => 'default-page-en'],
            'metaTitles' => $empty,
            'metaDescriptions' => $empty,
            'contents' => $empty,
            'indexedForSearch' => true,
            'displayed' => false,
            'shopIds' => [1],
        ], $cmsPage);
    }

    public function testPartialUpdateOneLanguageReplacesTheOthers(): void
    {
        [$cmsPageId] = $this->createSeveral(['One language page']);

        // The edit handler replaces every language of a localized field: the missing one becomes empty, or takes the
        // value of the default language for a required field
        $cmsPage = $this->partialUpdateItem('/cms-pages/' . $cmsPageId, [
            'contents' => ['en-US' => '<p>Patched EN</p>'],
            'titles' => ['en-US' => 'Patched EN'],
        ], ['cms_page_write']);
        $this->assertSame(['en-US' => '<p>Patched EN</p>', 'fr-FR' => ''], $cmsPage['contents']);
        $this->assertSame(['en-US' => 'Patched EN', 'fr-FR' => 'Patched EN'], $cmsPage['titles']);
        // The fields not sent keep their languages
        $this->assertSame($this->getCreateData()['metaTitles'], $cmsPage['metaTitles']);
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

    public function testListCmsPagesWithFiltersAndOrder(): void
    {
        [$cmsPageId] = $this->createSeveral(['Filtered page']);

        // The list fields are filtered and ordered through their API names (cmsPageCategoryId filters but cannot be
        // used in orderBy: the core grid sorts on its filter name)
        $filtered = $this->listItems('/cms-pages?orderBy=title&sortOrder=asc', ['cms_page_read'], [
            'cmsPageCategoryId' => 1,
            'title' => 'Filtered page',
        ]);
        $this->assertEquals('title', $filtered['orderBy']);
        $this->assertSame(1, $filtered['totalItems']);
        $this->assertSame([$cmsPageId], array_column($filtered['items'], 'cmsPageId'));

        $this->assertEquals(0, $this->countItems('/cms-pages', ['cms_page_read'], ['cmsPageCategoryId' => 999999]));
    }

    public function testAddCmsPageInUnknownCategory(): void
    {
        $data = $this->getCreateData();
        $data['cmsPageCategoryId'] = 999999;

        // The page handler checks the category and throws the not found exception of the category domain
        $this->createItem('/cms-pages', $data, ['cms_page_write'], Response::HTTP_NOT_FOUND);
    }

    public function testAddCmsPageInContextShops(): void
    {
        $this->markTestSkippedByMinVersion('9.2.0');

        $data = $this->getCreateData();
        unset($data['shopIds']);
        $data['linkRewrites'] = ['en-US' => 'context-shops-en', 'fr-FR' => 'context-shops-fr'];

        $cmsPage = $this->createItem('/cms-pages', $data, ['cms_page_write']);
        $this->assertSame([1], $cmsPage['shopIds']);
    }

    public function testInvalidCmsPageCategoryAndShops(): void
    {
        $data = $this->getCreateData();
        unset($data['cmsPageCategoryId']);
        $data['shopIds'] = ['3abc'];

        $validationErrorsResponse = $this->createItem('/cms-pages', $data, ['cms_page_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertValidationErrors([
            [
                'propertyPath' => 'cmsPageCategoryId',
                'message' => 'This value should not be null.',
            ],
            [
                'propertyPath' => 'shopIds[0]',
                'message' => 'This value should be of type integer.',
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
