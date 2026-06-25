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
        self::createApiClient(['image_settings_write']);
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        DatabaseDump::restoreTables(['configuration']);
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'edit image settings endpoint' => ['PUT', '/image-settings'];
    }

    public function testEditImageSettings(): void
    {
        $this->updateItem(
            '/image-settings',
            [
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
            ],
            ['image_settings_write'],
            Response::HTTP_NO_CONTENT
        );

        $this->assertSame(82, (int) \Configuration::get('PS_JPEG_QUALITY'));
        $this->assertSame(80, (int) \Configuration::get('PS_WEBP_QUALITY'));
    }
}
