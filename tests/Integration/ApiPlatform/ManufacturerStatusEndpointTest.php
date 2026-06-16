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

class ManufacturerStatusEndpointTest extends ApiTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::resetTables();
        self::createApiClient(['manufacturer_write', 'manufacturer_read']);
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        self::resetTables();
    }

    protected static function resetTables(): void
    {
        DatabaseDump::restoreTables([
            'manufacturer',
            'manufacturer_lang',
            'manufacturer_shop',
        ]);
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'set-status endpoint' => ['PUT', '/manufacturers/1/set-status'];
        yield 'delete logo endpoint' => ['DELETE', '/manufacturers/1/logo'];
    }

    private function createManufacturer(): int
    {
        $manufacturer = $this->createItem('/manufacturers', [
            'name' => 'Manufacturer status test',
            'shortDescriptions' => ['en-US' => 'short en', 'fr-FR' => 'short fr'],
            'descriptions' => ['en-US' => 'description en', 'fr-FR' => 'description fr'],
            'metaTitles' => ['en-US' => 'meta title en', 'fr-FR' => 'meta title fr'],
            'metaDescriptions' => ['en-US' => 'meta description en', 'fr-FR' => 'meta description fr'],
            'shopIds' => [1],
            'enabled' => true,
        ], ['manufacturer_write']);

        return $manufacturer['manufacturerId'];
    }

    public function testSetManufacturerStatus(): void
    {
        $manufacturerId = $this->createManufacturer();

        // Disable the manufacturer
        $this->updateItem(
            '/manufacturers/' . $manufacturerId . '/set-status',
            ['enabled' => false],
            ['manufacturer_write'],
            Response::HTTP_NO_CONTENT
        );
        $manufacturer = $this->getItem('/manufacturers/' . $manufacturerId, ['manufacturer_read']);
        $this->assertFalse($manufacturer['enabled']);

        // Enable it again
        $this->updateItem(
            '/manufacturers/' . $manufacturerId . '/set-status',
            ['enabled' => true],
            ['manufacturer_write'],
            Response::HTTP_NO_CONTENT
        );
        $manufacturer = $this->getItem('/manufacturers/' . $manufacturerId, ['manufacturer_read']);
        $this->assertTrue($manufacturer['enabled']);
    }

    public function testDeleteManufacturerLogo(): void
    {
        $manufacturerId = $this->createManufacturer();

        $return = $this->deleteItem('/manufacturers/' . $manufacturerId . '/logo', ['manufacturer_write']);
        $this->assertNull($return);
    }
}
