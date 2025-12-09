<?php

declare(strict_types=1);

namespace PsApiResourcesTest\Integration\ApiPlatform;

use Tests\Resources\Resetter\ProductResetter;
use Tests\Resources\ResourceResetter;

class ProductCombinationEndpointTest extends ApiTestCase
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
            '/products/combinations/1',
        ];
    }

    /**
     * @return int
     */
    public function testGetProductCombination(): int
    {
        $combinationId = 1;
        $productCombination = $this->getItem('/products/combinations/' . $combinationId, ['product_read']);
        $combinationIdExpected = 1;

        // TODO: Add create, updat before doing this asset
        // $this->assertEquals([
        //     'attributeGroupId' => $attributeGroupId,
        //     'names' => [
        //         'en-US' => 'name en',
        //         'fr-FR' => 'name fr',
        //     ],
        //     'publicNames' => [
        //         'en-US' => 'public name en',
        //         'fr-FR' => 'public name fr',
        //     ],
        //     'type' => 'select',
        //     'shopIds' => [1],
        // ], $productCombination);

        return $combinationId;
    }
}
