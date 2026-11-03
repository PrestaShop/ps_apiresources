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
    // Install defaults (install-dev/data/xml/configuration.xml), with PS_IMAGE_QUALITY set to png by the theme
    // (config/theme.yml); PS_IMAGE_GENERATION_METHOD is not set, so it reads as 0
    private const DEFAULT_SETTINGS = [
        'formats' => ['jpg'],
        'baseFormat' => 'png',
        'avifQuality' => 90,
        'jpegQuality' => 90,
        'pngQuality' => 7,
        'webpQuality' => 80,
        'generationMethod' => 0,
        'pictureMaxSize' => 8388608,
        'pictureMaxWidth' => 64,
        'pictureMaxHeight' => 64,
    ];

    private const UPDATED_SETTINGS = [
        'formats' => ['jpg', 'webp'],
        'baseFormat' => 'png_all',
        'avifQuality' => 50,
        'jpegQuality' => 82,
        'pngQuality' => 3,
        'webpQuality' => 60,
        'generationMethod' => 2,
        'pictureMaxSize' => 2000000,
        'pictureMaxWidth' => 1200,
        'pictureMaxHeight' => 1100,
    ];

    public static function setUpBeforeClass(): void
    {
        // Restored before the parent setup, which changes the configuration the API needs
        DatabaseDump::restoreTables(['configuration']);
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

    public function testGetImageSettings(): void
    {
        $this->assertEquals(self::DEFAULT_SETTINGS, $this->getItem('/image-settings', ['image_settings_read']));
    }

    /**
     * @depends testGetImageSettings
     */
    public function testUpdateImageSettings(): void
    {
        // Every field differs from its default, so a field the update drops cannot go unnoticed
        foreach (self::UPDATED_SETTINGS as $field => $value) {
            $this->assertNotEquals(self::DEFAULT_SETTINGS[$field], $value, $field);
        }

        // The update returns the updated settings, built by replaying the GET query
        $response = $this->updateItem('/image-settings', self::UPDATED_SETTINGS, ['image_settings_write'], Response::HTTP_OK);
        $this->assertEquals(self::UPDATED_SETTINGS, $response);

        $this->assertEquals(self::UPDATED_SETTINGS, $this->getItem('/image-settings', ['image_settings_read']));
    }

    /**
     * @depends testUpdateImageSettings
     */
    public function testJpgFormatIsAlwaysAdded(): void
    {
        $settings = ['formats' => ['webp']] + self::UPDATED_SETTINGS;
        $response = $this->updateItem('/image-settings', $settings, ['image_settings_write'], Response::HTTP_OK);
        $this->assertEquals(['formats' => ['webp', 'jpg']] + self::UPDATED_SETTINGS, $response);

        // Back to the state the following tests expect
        $this->updateItem('/image-settings', self::UPDATED_SETTINGS, ['image_settings_write'], Response::HTTP_OK);
    }

    /**
     * @depends testJpgFormatIsAlwaysAdded
     */
    public function testUpdateInvalidImageSettings(): void
    {
        $validationErrors = $this->updateItem(
            '/image-settings',
            [
                'formats' => ['jpg', 'gif'],
                'baseFormat' => 'tiff',
                'avifQuality' => 101,
                'jpegQuality' => -1,
                'pngQuality' => 10,
                'webpQuality' => 101,
                'generationMethod' => 3,
                'pictureMaxSize' => -1,
                'pictureMaxWidth' => -1,
                'pictureMaxHeight' => -1,
            ],
            ['image_settings_write'],
            Response::HTTP_UNPROCESSABLE_ENTITY
        );
        $this->assertValidationErrors([
            ['propertyPath' => 'formats[1]', 'message' => 'The value you selected is not a valid choice.'],
            ['propertyPath' => 'baseFormat', 'message' => 'The value you selected is not a valid choice.'],
            ['propertyPath' => 'avifQuality', 'message' => 'This value should be between 0 and 100.'],
            ['propertyPath' => 'jpegQuality', 'message' => 'This value should be between 0 and 100.'],
            ['propertyPath' => 'pngQuality', 'message' => 'This value should be between 0 and 9.'],
            ['propertyPath' => 'webpQuality', 'message' => 'This value should be between 0 and 100.'],
            ['propertyPath' => 'generationMethod', 'message' => 'The value you selected is not a valid choice.'],
            ['propertyPath' => 'pictureMaxSize', 'message' => 'This value should be either positive or zero.'],
            ['propertyPath' => 'pictureMaxWidth', 'message' => 'This value should be either positive or zero.'],
            ['propertyPath' => 'pictureMaxHeight', 'message' => 'This value should be either positive or zero.'],
        ], $validationErrors);

        // Every field is required: a missing key and an explicit null get the same 422
        $notNullErrors = array_map(
            fn (string $field) => ['propertyPath' => $field, 'message' => 'This value should not be null.'],
            array_keys(self::UPDATED_SETTINGS)
        );
        $validationErrors = $this->updateItem(
            '/image-settings',
            array_fill_keys(array_keys(self::UPDATED_SETTINGS), null),
            ['image_settings_write'],
            Response::HTTP_UNPROCESSABLE_ENTITY
        );
        $this->assertValidationErrors($notNullErrors, $validationErrors);
        // Only formats is sent (an empty array would be sent as an empty body by the test client)
        $validationErrors = $this->updateItem('/image-settings', ['formats' => ['jpg']], ['image_settings_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertValidationErrors(array_slice($notNullErrors, 1), $validationErrors);

        // Nothing was persisted by the rejected calls
        $this->assertEquals(self::UPDATED_SETTINGS, $this->getItem('/image-settings', ['image_settings_read']));
    }
}
