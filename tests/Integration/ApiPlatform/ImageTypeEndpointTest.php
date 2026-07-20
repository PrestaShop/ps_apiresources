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

class ImageTypeEndpointTest extends ApiTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        DatabaseDump::restoreTables(['image_type']);
        self::createApiClient(['image_type_write', 'image_type_read']);
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        DatabaseDump::restoreTables(['image_type']);
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'create endpoint' => ['POST', '/image-types'];
        yield 'get endpoint' => ['GET', '/image-types/1'];
        yield 'update endpoint' => ['PATCH', '/image-types/1'];
        yield 'delete endpoint' => ['DELETE', '/image-types/1'];
        yield 'bulk delete endpoint' => ['DELETE', '/image-types/bulk-delete'];
    }

    private function createPayload(string $name): array
    {
        return [
            'name' => $name,
            'width' => 120,
            'height' => 90,
            'products' => true,
            'categories' => false,
            'manufacturers' => false,
            'suppliers' => false,
            'stores' => false,
        ];
    }

    public function testAddImageType(): int
    {
        $imageType = $this->createItem('/image-types', $this->createPayload('my_custom_type'), ['image_type_write']);

        $this->assertArrayHasKey('imageTypeId', $imageType);
        $imageTypeId = $imageType['imageTypeId'];
        $this->assertEquals(['imageTypeId' => $imageTypeId], $imageType);

        return $imageTypeId;
    }

    /**
     * @depends testAddImageType
     */
    public function testGetImageType(int $imageTypeId): int
    {
        $imageType = $this->getItem('/image-types/' . $imageTypeId, ['image_type_read']);
        $this->assertEquals(
            [
                'imageTypeId' => $imageTypeId,
                'name' => 'my_custom_type',
                'width' => 120,
                'height' => 90,
                'products' => true,
                'categories' => false,
                'manufacturers' => false,
                'suppliers' => false,
                'stores' => false,
            ],
            $imageType
        );

        return $imageTypeId;
    }

    /**
     * @depends testGetImageType
     */
    public function testEditImageType(int $imageTypeId): int
    {
        $updated = $this->partialUpdateItem('/image-types/' . $imageTypeId, [
            'width' => 200,
            'categories' => true,
        ], ['image_type_write']);

        $this->assertSame(200, $updated['width']);
        $this->assertTrue($updated['categories']);
        $this->assertSame(90, $updated['height']);

        return $imageTypeId;
    }

    /**
     * @depends testEditImageType
     */
    public function testDeleteImageType(int $imageTypeId): void
    {
        $return = $this->deleteItem('/image-types/' . $imageTypeId, ['image_type_write']);
        $this->assertNull($return);

        $this->getItem('/image-types/' . $imageTypeId, ['image_type_read'], Response::HTTP_NOT_FOUND);
    }

    public function testBulkDeleteImageTypes(): void
    {
        $firstId = $this->createItem('/image-types', $this->createPayload('bulk_type_1'), ['image_type_write'])['imageTypeId'];
        $secondId = $this->createItem('/image-types', $this->createPayload('bulk_type_2'), ['image_type_write'])['imageTypeId'];

        $this->bulkDeleteItems('/image-types/bulk-delete', [
            'imageTypeIds' => [$firstId, $secondId],
        ], ['image_type_write']);

        $this->getItem('/image-types/' . $firstId, ['image_type_read'], Response::HTTP_NOT_FOUND);
        $this->getItem('/image-types/' . $secondId, ['image_type_read'], Response::HTTP_NOT_FOUND);
    }
}
