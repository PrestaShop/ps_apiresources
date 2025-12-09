<?php

declare(strict_types=1);

namespace PsApiResourcesTest\Integration\ApiPlatform;

use Tests\Resources\Resetter\LanguageResetter;
use Tests\Resources\Resetter\ProductResetter;

class ProductCombinationListEndpointTest extends ApiTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        // Add the fr-FR language to test multi lang values accurately
        LanguageResetter::resetLanguages();
        ProductResetter::resetProducts();
        self::addLanguageByLocale('fr-FR');
        // Pre-create the API Client with the needed scopes, this way we reduce the number of created API Clients
        self::createApiClient(['product_read']);
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        ProductResetter::resetProducts();
        LanguageResetter::resetLanguages();
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'get endpoint' => [
            'GET',
            '/products/1/combinations',
        ];
    }

    /**
     * @return int
     */
    public function testGetProductCombinationList(): int
    {
        $productId = 1;
        $productCombinationList = $this->getItem('/products/' . $productId . '/combinations', ['product_read']);
        $combinationIdExpected = 1;
        $this->assertIsArray($productCombinationList);
        $this->assertContains($combinationIdExpected, $productCombinationList);
        return $productId;
    }

}
