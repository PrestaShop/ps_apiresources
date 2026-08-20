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

class ImageSettingsEndpointTest extends ApiTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::createApiClient(['image_settings_read', 'image_settings_write']);
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        DatabaseDump::restoreTables(['configuration']);
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'get image settings endpoint' => ['GET', '/image-settings'];
        yield 'edit image settings endpoint' => ['PUT', '/image-settings'];
    }

    /**
     * The settings are a singleton resource, so the initial GET is the only fixture this test
     * needs: everything else is built from the API itself.
     */
    public function testGetImageSettings(): array
    {
        $settings = $this->getItem('/image-settings', ['image_settings_read']);

        $this->assertEquals(
            [
                'formats',
                'baseFormat',
                'avifQuality',
                'jpegQuality',
                'pngQuality',
                'webpQuality',
                'generationMethod',
                'pictureMaxSize',
                'pictureMaxWidth',
                'pictureMaxHeight',
            ],
            array_keys($settings)
        );

        return $settings;
    }

    /**
     * @depends testGetImageSettings
     */
    public function testUpdateImageSettings(array $initialSettings): array
    {
        $updatedSettings = [
            'formats' => ['jpg', 'webp'],
            'baseFormat' => 'jpg',
            'avifQuality' => 90,
            'jpegQuality' => 82,
            'pngQuality' => 7,
            'webpQuality' => 80,
            'generationMethod' => 0,
            'pictureMaxSize' => 2000000,
            'pictureMaxWidth' => 1200,
            'pictureMaxHeight' => 1200,
        ];
        $this->assertNotEquals($initialSettings, $updatedSettings);

        // The update returns the updated settings, built by replaying the GET query
        $response = $this->updateItem(
            '/image-settings',
            $updatedSettings,
            ['image_settings_write'],
            Response::HTTP_OK
        );

        $this->assertEquals($updatedSettings, $response);

        return $updatedSettings;
    }

    /**
     * @depends testUpdateImageSettings
     */
    public function testGetUpdatedImageSettings(array $updatedSettings): void
    {
        $settings = $this->getItem('/image-settings', ['image_settings_read']);

        $this->assertEquals($updatedSettings, $settings);
    }
}
