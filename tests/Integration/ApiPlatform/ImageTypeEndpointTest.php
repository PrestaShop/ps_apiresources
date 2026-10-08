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
        yield 'delete images from type endpoint' => ['DELETE', '/image-types/1/images'];
        yield 'regenerate thumbnails endpoint' => ['PUT', '/image-types/regenerate-thumbnails'];
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
        $this->assertEquals(
            ['imageTypeId' => $imageTypeId] + $this->createPayload('my_custom_type'),
            $imageType
        );

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

        $this->assertEquals(
            [
                'imageTypeId' => $imageTypeId,
                'name' => 'my_custom_type',
                'width' => 200,
                'height' => 90,
                'products' => true,
                'categories' => true,
                'manufacturers' => false,
                'suppliers' => false,
                'stores' => false,
            ],
            $updated
        );

        return $imageTypeId;
    }

    /**
     * @depends testEditImageType
     */
    public function testPartialUpdateInvalidImageType(int $imageTypeId): int
    {
        $before = $this->getItem('/image-types/' . $imageTypeId, ['image_type_read']);

        $validationErrors = $this->partialUpdateItem('/image-types/' . $imageTypeId, ['name' => ''], ['image_type_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertValidationErrors([
            ['propertyPath' => 'name', 'message' => 'This value should not be blank.'],
        ], $validationErrors);

        $validationErrors = $this->partialUpdateItem('/image-types/' . $imageTypeId, ['width' => 0, 'height' => -5], ['image_type_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertValidationErrors([
            ['propertyPath' => 'width', 'message' => 'This value should be positive.'],
            ['propertyPath' => 'height', 'message' => 'This value should be positive.'],
        ], $validationErrors);

        // Nothing was persisted by the rejected calls
        $this->assertEquals($before, $this->getItem('/image-types/' . $imageTypeId, ['image_type_read']));

        return $imageTypeId;
    }

    public function testInvalidImageType(): void
    {
        $validationErrors = $this->createItem('/image-types', ['width' => -5] + $this->createPayload(''), ['image_type_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertValidationErrors([
            ['propertyPath' => 'name', 'message' => 'This value should not be blank.'],
            ['propertyPath' => 'width', 'message' => 'This value should be positive.'],
        ], $validationErrors);
    }

    /**
     * @depends testPartialUpdateInvalidImageType
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

    public function testDeleteImagesFromType(): void
    {
        // The image type is created through POST /image-types instead of picking the first id
        // with a raw SELECT, so the test owns the type whose images it wipes.
        $imageTypeId = $this->createItem(
            '/image-types',
            $this->createPayload('delete_images_type'),
            ['image_type_write']
        )['imageTypeId'];

        // Removes the generated thumbnail files of that image type (none for a type that was
        // just created), so a 204 confirms the command ran successfully.
        $return = $this->deleteItem('/image-types/' . $imageTypeId . '/images', ['image_type_write']);
        $this->assertNull($return);

        // Wiping the images does not remove the type itself
        $this->assertEquals(
            $imageTypeId,
            $this->getItem('/image-types/' . $imageTypeId, ['image_type_read'])['imageTypeId']
        );
    }

    public function testRegenerateThumbnails(): void
    {
        // Regenerate the supplier thumbnails (a small image domain) for every image type,
        // without erasing the existing images.
        $this->updateItem(
            '/image-types/regenerate-thumbnails',
            [
                'image' => 'suppliers',
                'imageTypeId' => 0,
                'erasePreviousImages' => false,
            ],
            ['image_type_write'],
            Response::HTTP_NO_CONTENT
        );
    }

    public function testRegenerateThumbnailsWithUnknownDomain(): void
    {
        $validationErrors = $this->updateItem(
            '/image-types/regenerate-thumbnails',
            [
                'image' => 'not_a_domain',
                'imageTypeId' => 0,
                'erasePreviousImages' => false,
            ],
            ['image_type_write'],
            Response::HTTP_UNPROCESSABLE_ENTITY
        );
        $this->assertValidationErrors([
            ['propertyPath' => 'image', 'message' => 'The value you selected is not a valid choice.'],
        ], $validationErrors);
    }
}
