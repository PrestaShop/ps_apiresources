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

class ManufacturerEndpointTest extends ApiTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::createApiClient(['manufacturer_read', 'manufacturer_write']);
        DatabaseDump::restoreTables(['manufacturer', 'manufacturer_lang', 'manufacturer_shop']);
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        DatabaseDump::restoreTables(['manufacturer', 'manufacturer_lang', 'manufacturer_shop']);
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'get manufacturer endpoint' => ['GET', '/manufacturers/1'];
        yield 'create manufacturer endpoint' => ['POST', '/manufacturers'];
        yield 'update manufacturer endpoint' => ['PATCH', '/manufacturers/1'];
        yield 'delete manufacturer endpoint' => ['DELETE', '/manufacturers/1'];
        yield 'toggle manufacturer status endpoint' => ['PUT', '/manufacturers/1/toggle-status'];
        yield 'bulk delete manufacturers endpoint' => ['DELETE', '/manufacturers/bulk-delete'];
        yield 'bulk update manufacturers status endpoint' => ['PUT', '/manufacturers/bulk-update-status'];
    }

    public function testCreateManufacturer(): int
    {
        $manufacturer = $this->createItem('/manufacturers', [
            'name' => 'Test Manufacturer',
            'shortDescriptions' => [
                'en-US' => 'Short description EN',
            ],
            'descriptions' => [
                'en-US' => 'Full description EN',
            ],
            'metaTitles' => [
                'en-US' => 'Meta title EN',
            ],
            'metaDescriptions' => [
                'en-US' => 'Meta description EN',
            ],
            'enabled' => true,
            'shopIds' => [1],
        ], ['manufacturer_write']);

        $this->assertArrayHasKey('manufacturerId', $manufacturer);
        $this->assertEquals('Test Manufacturer', $manufacturer['name']);
        $this->assertTrue($manufacturer['enabled']);

        // Dump response for debugging
        file_put_contents('/tmp/manufacturer_create_response.json', json_encode($manufacturer, JSON_PRETTY_PRINT));

        return $manufacturer['manufacturerId'];
    }

    /**
     * @depends testCreateManufacturer
     */
    public function testGetManufacturer(int $manufacturerId): int
    {
        $manufacturer = $this->getItem('/manufacturers/' . $manufacturerId, ['manufacturer_read']);

        $this->assertEquals($manufacturerId, $manufacturer['manufacturerId']);
        $this->assertEquals('Test Manufacturer', $manufacturer['name']);
        $this->assertTrue($manufacturer['enabled']);
        $this->assertArrayHasKey('shortDescriptions', $manufacturer);
        $this->assertArrayHasKey('descriptions', $manufacturer);
        $this->assertArrayHasKey('metaTitles', $manufacturer);
        $this->assertArrayHasKey('metaDescriptions', $manufacturer);
        $this->assertArrayHasKey('shopIds', $manufacturer);

        $expectedManufacturer = $manufacturer;
        $this->assertEquals($expectedManufacturer, $this->getItem('/manufacturers/' . $manufacturerId, ['manufacturer_read']));

        return $manufacturerId;
    }

    /**
     * @depends testGetManufacturer
     */
    public function testUpdateManufacturer(int $manufacturerId): int
    {
        $updated = $this->partialUpdateItem('/manufacturers/' . $manufacturerId, [
            'name' => 'Updated Manufacturer',
            'enabled' => false,
        ], ['manufacturer_write']);

        $this->assertEquals('Updated Manufacturer', $updated['name']);
        $this->assertFalse($updated['enabled']);

        $manufacturer = $this->getItem('/manufacturers/' . $manufacturerId, ['manufacturer_read']);
        $this->assertEquals('Updated Manufacturer', $manufacturer['name']);
        $this->assertFalse($manufacturer['enabled']);

        return $manufacturerId;
    }

    /**
     * @depends testUpdateManufacturer
     */
    public function testDeleteManufacturer(): void
    {
        $manufacturer = $this->createItem('/manufacturers', [
            'name' => 'To Delete',
            'shortDescriptions' => ['en-US' => ''],
            'descriptions' => ['en-US' => ''],
            'metaTitles' => ['en-US' => ''],
            'metaDescriptions' => ['en-US' => ''],
            'enabled' => true,
            'shopIds' => [1],
        ], ['manufacturer_write']);

        $manufacturerId = $manufacturer['manufacturerId'];

        $this->deleteItem('/manufacturers/' . $manufacturerId, ['manufacturer_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testGetNonExistentManufacturer(): void
    {
        $this->getItem('/manufacturers/999999', ['manufacturer_read'], Response::HTTP_NOT_FOUND);
    }
}
