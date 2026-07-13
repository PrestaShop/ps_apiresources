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

class CmsRedirectionQueriesEndpointTest extends ApiTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::createApiClient(['cms_page_category_read', 'cms_page_read']);
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'cms page category parent-for-redirection' => ['GET', '/cms-page-categories/1/parent-for-redirection'];
        yield 'cms page category-for-redirection' => ['GET', '/cms-pages/1/category-for-redirection'];
    }

    public function testGetCmsPageCategoryParent(): void
    {
        // ROOT (1) has no parent → handler catches and returns ROOT (1)
        $result = $this->getItem('/cms-page-categories/1/parent-for-redirection', ['cms_page_category_read']);

        $this->assertArrayHasKey('cmsPageCategoryId', $result);
        $this->assertSame(1, $result['cmsPageCategoryId']);
        $this->assertArrayHasKey('parentId', $result);
        $this->assertIsInt($result['parentId']);
    }

    public function testGetCmsPageCategoryOfPage(): void
    {
        // Unknown page id → handler catches CmsPageException, returns ROOT (1)
        $result = $this->getItem('/cms-pages/999999/category-for-redirection', ['cms_page_read']);

        $this->assertArrayHasKey('cmsPageId', $result);
        $this->assertSame(999999, $result['cmsPageId']);
        $this->assertArrayHasKey('cmsPageCategoryId', $result);
        $this->assertIsInt($result['cmsPageCategoryId']);
    }
}
