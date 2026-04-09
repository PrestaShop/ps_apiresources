<?php
declare(strict_types=1);

namespace PsApiResourcesTest\Integration\ApiPlatform;

class SettingsEndpointTest extends ApiTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::createApiClient(['currency_read', 'image_settings_read', 'image_settings_write', 'shop_read', 'country_read']);
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'get image settings endpoint' => ['GET', '/image-settings'];
        yield 'update image settings endpoint' => ['PUT', '/image-settings'];
        yield 'get shop logos endpoint' => ['GET', '/shops/logos'];
    }
}
