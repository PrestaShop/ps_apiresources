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

class MetaEndpointTest extends ApiTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        DatabaseDump::restoreTables(['meta', 'meta_lang']);
        self::createApiClient(['meta_write', 'meta_read']);
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        DatabaseDump::restoreTables(['meta', 'meta_lang']);
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'get endpoint' => [
            'GET',
            '/metas/1',
        ];

        yield 'update endpoint' => [
            'PATCH',
            '/metas/1',
        ];

        yield 'create endpoint' => [
            'POST',
            '/metas',
        ];

        yield 'get pages endpoint' => [
            'GET',
            '/metas/pages',
        ];
    }

    public function testGetPagesForLayout(): void
    {
        $pages = $this->getItem('/metas/pages', ['meta_read']);

        $this->assertIsArray($pages);
        $this->assertNotEmpty($pages);
        $this->assertArrayHasKey('page', $pages[0]);
        $this->assertArrayHasKey('title', $pages[0]);
        $this->assertArrayHasKey('description', $pages[0]);
    }

    public function testAddMeta(): int
    {
        // Pick a valid page name from the same source AddMeta validates against
        $pages = $this->getItem('/metas/pages', ['meta_read']);
        $this->assertNotEmpty($pages);
        $pageName = $pages[0]['page'];

        $meta = $this->createItem('/metas', [
            'pageName' => $pageName,
            'urlRewrites' => [
                'en-US' => 'my-custom-meta-page',
                'fr-FR' => 'ma-page-meta-perso',
            ],
        ], ['meta_write']);

        $this->assertArrayHasKey('metaId', $meta);
        $this->assertEquals(['metaId' => $meta['metaId']], $meta);

        return $meta['metaId'];
    }

    public function testGetMeta(): void
    {
        // Meta id 1 exists in the default fixtures
        $meta = $this->getItem('/metas/1', ['meta_read']);

        $this->assertSame(1, $meta['metaId']);
        $this->assertIsString($meta['pageName']);
        $this->assertIsArray($meta['pageTitles']);
        $this->assertIsArray($meta['metaDescriptions']);
        $this->assertIsArray($meta['urlRewrites']);
    }

    public function testEditMeta(): void
    {
        $updated = $this->partialUpdateItem('/metas/1', [
            'pageTitles' => [
                'en-US' => 'My Updated Page Title',
                'fr-FR' => 'Mon titre de page',
            ],
        ], ['meta_write']);

        $this->assertSame(
            [
                'en-US' => 'My Updated Page Title',
                'fr-FR' => 'Mon titre de page',
            ],
            $updated['pageTitles']
        );
        $this->assertSame(1, $updated['metaId']);
    }
}
