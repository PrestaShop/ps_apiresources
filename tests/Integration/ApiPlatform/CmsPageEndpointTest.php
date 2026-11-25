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
        self::createApiClient(['cms_page_read']);
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
        yield 'get endpoint' => [
            'GET',
            '/cms-pages/1',
        ];
    }

    public function testGetCmsPage(): void
    {
        $cmsPage = $this->getItem('/cms-pages/1', ['cms_page_read']);

        $this->assertArrayHasKey('cmsPageId', $cmsPage);
        $this->assertEquals(1, $cmsPage['cmsPageId']);
        $this->assertArrayHasKey('titles', $cmsPage);
        $this->assertArrayHasKey('friendlyUrls', $cmsPage);
        $this->assertArrayHasKey('enabled', $cmsPage);
        $this->assertArrayHasKey('indexedForSearch', $cmsPage);
    }
}
