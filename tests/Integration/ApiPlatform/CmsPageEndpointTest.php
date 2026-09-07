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

class CmsPageEndpointTest extends ApiTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        LanguageResetter::resetLanguages();
        self::addLanguageByLocale('fr-FR');
        self::resetTables();
        self::createApiClient(['cms_page_read', 'cms_page_write']);
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
            'cms',
            'cms_lang',
            'cms_shop',
        ]);
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'get endpoint' => ['GET', '/cms-pages/1'];
        yield 'create endpoint' => ['POST', '/cms-pages'];
        yield 'patch endpoint' => ['PATCH', '/cms-pages/1'];
        yield 'toggle status endpoint' => ['PUT', '/cms-pages/1/toggle-status'];
        yield 'delete endpoint' => ['DELETE', '/cms-pages/1'];
        yield 'bulk delete endpoint' => ['DELETE', '/cms-pages/bulk-delete'];
        yield 'bulk enable endpoint' => ['PUT', '/cms-pages/bulk-enable'];
        yield 'bulk disable endpoint' => ['PUT', '/cms-pages/bulk-disable'];
    }

    public function testAddCmsPage(): int
    {
        $postData = [
            'cmsPageCategoryId' => 1,
            'titles' => [
                'en-US' => 'Test CMS Page',
                'fr-FR' => 'Page CMS de test',
            ],
            'metaTitles' => [
                'en-US' => 'Meta Title EN',
                'fr-FR' => 'Meta Title FR',
            ],
            'metaDescriptions' => [
                'en-US' => 'Meta description EN',
                'fr-FR' => 'Meta description FR',
            ],
            'friendlyUrls' => [
                'en-US' => 'test-cms-page',
                'fr-FR' => 'page-cms-de-test',
            ],
            'contents' => [
                'en-US' => '<p>Content EN</p>',
                'fr-FR' => '<p>Contenu FR</p>',
            ],
            'indexedForSearch' => true,
            'enabled' => true,
            'shopIds' => [1],
        ];

        $response = $this->createItem('/cms-pages', $postData, ['cms_page_write']);
        $this->assertArrayHasKey('cmsPageId', $response);
        $cmsPageId = $response['cmsPageId'];

        $this->assertEquals(
            ['cmsPageId' => $cmsPageId] + $postData,
            $response
        );

        return $cmsPageId;
    }

    /** @depends testAddCmsPage */
    public function testGetCmsPage(int $cmsPageId): int
    {
        $response = $this->getItem('/cms-pages/' . $cmsPageId, ['cms_page_read']);
        $this->assertEquals([
            'cmsPageId' => $cmsPageId,
            'cmsPageCategoryId' => 1,
            'titles' => [
                'en-US' => 'Test CMS Page',
                'fr-FR' => 'Page CMS de test',
            ],
            'metaTitles' => [
                'en-US' => 'Meta Title EN',
                'fr-FR' => 'Meta Title FR',
            ],
            'metaDescriptions' => [
                'en-US' => 'Meta description EN',
                'fr-FR' => 'Meta description FR',
            ],
            'friendlyUrls' => [
                'en-US' => 'test-cms-page',
                'fr-FR' => 'page-cms-de-test',
            ],
            'contents' => [
                'en-US' => '<p>Content EN</p>',
                'fr-FR' => '<p>Contenu FR</p>',
            ],
            'indexedForSearch' => true,
            'enabled' => true,
            'shopIds' => [1],
        ], $response);

        return $cmsPageId;
    }

    /** @depends testGetCmsPage */
    public function testPartialUpdateCmsPage(int $cmsPageId): int
    {
        $patchData = [
            'titles' => [
                'en-US' => 'Updated CMS Page',
                'fr-FR' => 'Page CMS mise à jour',
            ],
            'enabled' => false,
        ];

        $updated = $this->partialUpdateItem('/cms-pages/' . $cmsPageId, $patchData, ['cms_page_write']);
        $this->assertEquals([
            'cmsPageId' => $cmsPageId,
            'cmsPageCategoryId' => 1,
            'titles' => [
                'en-US' => 'Updated CMS Page',
                'fr-FR' => 'Page CMS mise à jour',
            ],
            'metaTitles' => [
                'en-US' => 'Meta Title EN',
                'fr-FR' => 'Meta Title FR',
            ],
            'metaDescriptions' => [
                'en-US' => 'Meta description EN',
                'fr-FR' => 'Meta description FR',
            ],
            'friendlyUrls' => [
                'en-US' => 'test-cms-page',
                'fr-FR' => 'page-cms-de-test',
            ],
            'contents' => [
                'en-US' => '<p>Content EN</p>',
                'fr-FR' => '<p>Contenu FR</p>',
            ],
            'indexedForSearch' => true,
            'enabled' => false,
            'shopIds' => [1],
        ], $updated);

        // Verify GET reflects the changes
        $fetched = $this->getItem('/cms-pages/' . $cmsPageId, ['cms_page_read']);
        $this->assertEquals($updated, $fetched);

        return $cmsPageId;
    }

    /** @depends testPartialUpdateCmsPage */
    public function testToggleCmsPageStatus(int $cmsPageId): int
    {
        // Page is currently disabled, toggle should enable it
        $this->updateItem('/cms-pages/' . $cmsPageId . '/toggle-status', [], ['cms_page_write'], Response::HTTP_NO_CONTENT);
        $response = $this->getItem('/cms-pages/' . $cmsPageId, ['cms_page_read']);
        $this->assertEquals(true, $response['enabled']);

        // Toggle again should disable it
        $this->updateItem('/cms-pages/' . $cmsPageId . '/toggle-status', [], ['cms_page_write'], Response::HTTP_NO_CONTENT);
        $response = $this->getItem('/cms-pages/' . $cmsPageId, ['cms_page_read']);
        $this->assertEquals(false, $response['enabled']);

        return $cmsPageId;
    }

    /** @depends testToggleCmsPageStatus */
    public function testBulkUpdateStatusCmsPages(int $cmsPageId): int
    {
        // Bulk enable
        $this->updateItem('/cms-pages/bulk-enable', ['cmsPageIds' => [$cmsPageId]], ['cms_page_write'], Response::HTTP_NO_CONTENT);
        $response = $this->getItem('/cms-pages/' . $cmsPageId, ['cms_page_read']);
        $this->assertEquals(true, $response['enabled']);

        // Bulk disable
        $this->updateItem('/cms-pages/bulk-disable', ['cmsPageIds' => [$cmsPageId]], ['cms_page_write'], Response::HTTP_NO_CONTENT);
        $response = $this->getItem('/cms-pages/' . $cmsPageId, ['cms_page_read']);
        $this->assertEquals(false, $response['enabled']);

        return $cmsPageId;
    }

    /** @depends testBulkUpdateStatusCmsPages */
    public function testDeleteCmsPage(int $cmsPageId): void
    {
        $this->deleteItem('/cms-pages/' . $cmsPageId, ['cms_page_write']);
        $this->getItem('/cms-pages/' . $cmsPageId, ['cms_page_read'], Response::HTTP_NOT_FOUND);
    }

    public function testBulkDeleteCmsPages(): void
    {
        // Create two pages to bulk delete
        $page1 = $this->createItem('/cms-pages', [
            'cmsPageCategoryId' => 1,
            'titles' => ['en-US' => 'Bulk Page 1'],
            'friendlyUrls' => ['en-US' => 'bulk-page-1'],
            'metaTitles' => [],
            'metaDescriptions' => [],
            'contents' => [],
            'indexedForSearch' => false,
            'enabled' => false,
            'shopIds' => [1],
        ], ['cms_page_write']);

        $page2 = $this->createItem('/cms-pages', [
            'cmsPageCategoryId' => 1,
            'titles' => ['en-US' => 'Bulk Page 2'],
            'friendlyUrls' => ['en-US' => 'bulk-page-2'],
            'metaTitles' => [],
            'metaDescriptions' => [],
            'contents' => [],
            'indexedForSearch' => false,
            'enabled' => false,
            'shopIds' => [1],
        ], ['cms_page_write']);

        $this->deleteItem('/cms-pages/bulk-delete', ['cms_page_write'], Response::HTTP_NO_CONTENT, [
            'json' => ['cmsPageIds' => [$page1['cmsPageId'], $page2['cmsPageId']]],
        ]);

        $this->getItem('/cms-pages/' . $page1['cmsPageId'], ['cms_page_read'], Response::HTTP_NOT_FOUND);
        $this->getItem('/cms-pages/' . $page2['cmsPageId'], ['cms_page_read'], Response::HTTP_NOT_FOUND);
    }

    public function testInvalidCmsPage(): void
    {
        $invalidData = [
            'cmsPageCategoryId' => 1,
            'titles' => [
                // en-US (default language) is missing
                'fr-FR' => 'Page CMS<',
            ],
            'friendlyUrls' => [
                // en-US (default language) is missing
                'fr-FR' => 'valid-friendly-url',
            ],
            'metaTitles' => [],
            'metaDescriptions' => [],
            'contents' => [],
            'indexedForSearch' => true,
            'enabled' => true,
            'shopIds' => [1],
        ];

        $response = $this->createItem(
            '/cms-pages',
            $invalidData,
            ['cms_page_write'],
            Response::HTTP_UNPROCESSABLE_ENTITY
        );

        $this->assertValidationErrors([
            [
                'propertyPath' => 'titles',
                'message' => 'The field titles is required at least in your default language.',
            ],
            [
                'propertyPath' => 'titles[fr-FR]',
                'message' => '"Page CMS<" is invalid',
            ],
            [
                'propertyPath' => 'friendlyUrls',
                'message' => 'The field friendlyUrls is required at least in your default language.',
            ],
        ], $response);
    }
}
