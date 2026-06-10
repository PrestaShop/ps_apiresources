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

class TagEndpointTest extends ApiTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        DatabaseDump::restoreTables(['tag', 'product_tag']);
        self::createApiClient(['tag_write', 'tag_read']);
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        DatabaseDump::restoreTables(['tag', 'product_tag']);
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'create endpoint' => [
            'POST',
            '/tags',
        ];

        yield 'get endpoint' => [
            'GET',
            '/tags/1',
        ];

        yield 'update endpoint' => [
            'PATCH',
            '/tags/1',
        ];

        yield 'delete endpoint' => [
            'DELETE',
            '/tags/1',
        ];

        yield 'bulk delete endpoint' => [
            'DELETE',
            '/tags/bulk-delete',
        ];
    }

    public function testAddTag(): int
    {
        $tag = $this->createItem('/tags', [
            'name' => 'My Tag',
            'languageId' => 1,
        ], ['tag_write']);

        $this->assertArrayHasKey('tagId', $tag);
        $tagId = $tag['tagId'];
        $this->assertEquals(['tagId' => $tagId], $tag);

        return $tagId;
    }

    /**
     * @depends testAddTag
     */
    public function testGetTag(int $tagId): int
    {
        $tag = $this->getItem('/tags/' . $tagId, ['tag_read']);
        $this->assertEquals(
            [
                'tagId' => $tagId,
                'name' => 'My Tag',
                'languageId' => 1,
                'products' => [],
            ],
            $tag
        );

        return $tagId;
    }

    /**
     * @depends testGetTag
     */
    public function testEditTag(int $tagId): int
    {
        $updatedTag = $this->partialUpdateItem('/tags/' . $tagId, [
            'name' => 'My Tag Updated',
        ], ['tag_write']);
        $this->assertEquals(
            [
                'tagId' => $tagId,
                'name' => 'My Tag Updated',
                'languageId' => 1,
                'products' => [],
            ],
            $updatedTag
        );

        return $tagId;
    }

    /**
     * @depends testEditTag
     */
    public function testDeleteTag(int $tagId): void
    {
        $return = $this->deleteItem('/tags/' . $tagId, ['tag_write']);
        // This endpoint returns an empty response and a 204 HTTP code
        $this->assertNull($return);

        // Getting the item should result in a 404 now
        $this->getItem('/tags/' . $tagId, ['tag_read'], Response::HTTP_NOT_FOUND);
    }

    public function testBulkDeleteTags(): void
    {
        $firstTagId = $this->createItem('/tags', [
            'name' => 'Bulk Tag 1',
            'languageId' => 1,
        ], ['tag_write'])['tagId'];
        $secondTagId = $this->createItem('/tags', [
            'name' => 'Bulk Tag 2',
            'languageId' => 1,
        ], ['tag_write'])['tagId'];

        $this->bulkDeleteItems('/tags/bulk-delete', [
            'tagIds' => [$firstTagId, $secondTagId],
        ], ['tag_write']);

        $this->getItem('/tags/' . $firstTagId, ['tag_read'], Response::HTTP_NOT_FOUND);
        $this->getItem('/tags/' . $secondTagId, ['tag_read'], Response::HTTP_NOT_FOUND);
    }
}
