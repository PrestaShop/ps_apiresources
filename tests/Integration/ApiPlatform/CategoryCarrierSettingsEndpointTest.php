<?php
declare(strict_types=1);

namespace PsApiResourcesTest\Integration\ApiPlatform;

class CategoryCarrierSettingsEndpointTest extends ApiTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::createApiClient(['category_read']);
    }

    public static function getProtectedEndpoints(): iterable
    {
        // Carrier settings endpoints return scope errors (CQRS not available)
        yield 'get category tree endpoint' => ['GET', '/categories/trees'];
    }
}
