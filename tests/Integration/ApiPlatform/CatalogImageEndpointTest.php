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

class CatalogImageEndpointTest extends ApiTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::createApiClient([
            'catalog_price_rule_read', 'catalog_price_rule_write',
            'image_type_read', 'image_type_write',
        ]);
        DatabaseDump::restoreTables(['specific_price_rule', 'specific_price_rule_condition', 'specific_price_rule_condition_group', 'image_type']);
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        DatabaseDump::restoreTables(['specific_price_rule', 'specific_price_rule_condition', 'specific_price_rule_condition_group', 'image_type']);
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'get catalog price rule endpoint' => ['GET', '/catalog-price-rules/1'];
        yield 'create catalog price rule endpoint' => ['POST', '/catalog-price-rules'];
        yield 'update catalog price rule endpoint' => ['PATCH', '/catalog-price-rules/1'];
        yield 'delete catalog price rule endpoint' => ['DELETE', '/catalog-price-rules/1'];
        yield 'bulk delete catalog price rules endpoint' => ['DELETE', '/catalog-price-rules/bulk-delete'];
        yield 'get image type endpoint' => ['GET', '/image-types/1'];
        yield 'create image type endpoint' => ['POST', '/image-types'];
        yield 'update image type endpoint' => ['PATCH', '/image-types/1'];
        yield 'delete image type endpoint' => ['DELETE', '/image-types/1'];
        yield 'bulk delete image types endpoint' => ['DELETE', '/image-types/bulk-delete'];
    }

    public function testCreateImageType(): int
    {
        $imageType = $this->createItem('/image-types', [
            'name' => 'test_image_type',
            'width' => 100,
            'height' => 100,
            'products' => true,
            'categories' => false,
            'manufacturers' => false,
            'suppliers' => false,
            'stores' => false,
        ], ['image_type_write']);

        $this->assertArrayHasKey('imageTypeId', $imageType);
        $this->assertEquals('test_image_type', $imageType['name']);
        $this->assertEquals(100, $imageType['width']);
        $this->assertEquals(100, $imageType['height']);

        return $imageType['imageTypeId'];
    }

    /**
     * @depends testCreateImageType
     */
    public function testGetImageType(int $imageTypeId): int
    {
        $imageType = $this->getItem('/image-types/' . $imageTypeId, ['image_type_read']);

        $this->assertEquals($imageTypeId, $imageType['imageTypeId']);
        $this->assertEquals('test_image_type', $imageType['name']);

        $expectedImageType = $imageType;
        $this->assertEquals($expectedImageType, $this->getItem('/image-types/' . $imageTypeId, ['image_type_read']));

        return $imageTypeId;
    }

    public function testDeleteImageType(): void
    {
        $imageType = $this->createItem('/image-types', [
            'name' => 'to_delete_type',
            'width' => 50,
            'height' => 50,
            'products' => false,
            'categories' => false,
            'manufacturers' => false,
            'suppliers' => false,
            'stores' => false,
        ], ['image_type_write']);

        $this->deleteItem('/image-types/' . $imageType['imageTypeId'], ['image_type_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testGetNonExistentImageType(): void
    {
        $this->getItem('/image-types/999999', ['image_type_read'], Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testGetNonExistentCatalogPriceRule(): void
    {
        $this->getItem('/catalog-price-rules/999999', ['catalog_price_rule_read'], Response::HTTP_NOT_FOUND);
    }
}
