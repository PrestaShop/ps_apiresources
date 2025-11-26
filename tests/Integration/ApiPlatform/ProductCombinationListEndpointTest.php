<?php

declare(strict_types=1);

namespace PsApiResourcesTest\Integration\ApiPlatform;

use PrestaShop\PrestaShop\Core\Domain\Product\ValueObject\ProductType;
use Tests\Resources\Resetter\ProductResetter;
use Tests\Resources\ResourceResetter;
use Symfony\Component\HttpFoundation\Response;

class ProductCombinationListEndpointTest extends ApiTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        (new ResourceResetter())->backupTestModules();
        ProductResetter::resetProducts();
        self::createApiClient(['product_read']);
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        ProductResetter::resetProducts();
        (new ResourceResetter())->resetTestModules();
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'get endpoint' => [
            'GET',
            '/product/1/combinations',
        ];
    }

    public function testGetProductCombinations(): void
    {
        // 1. Create Product
        $product = new \Product();
        $product->name = ['en-US' => 'Test Product', 'fr-FR' => 'Produit Test'];
        $product->link_rewrite = ['en-US' => 'test-product', 'fr-FR' => 'produit-test'];
        $product->price = 10.0;
        $product->active = true;
        $product->id_category_default = 2; // Home
        $product->add();
        $productId = (int) $product->id;

        // 2. Create Attribute Group
        $attributeGroup = new \AttributeGroup();
        $attributeGroup->name = [\Configuration::get('PS_LANG_DEFAULT') => 'Size'];
        $attributeGroup->public_name = [\Configuration::get('PS_LANG_DEFAULT') => 'Size'];
        $attributeGroup->group_type = 'select';
        $attributeGroup->add();
        $attributeGroupId = (int) $attributeGroup->id;

        // 3. Create Attribute
        $attribute = new \Attribute();
        $attribute->id_attribute_group = $attributeGroupId;
        $attribute->name = [\Configuration::get('PS_LANG_DEFAULT') => 'Small'];
        $attribute->add();
        $attributeId = (int) $attribute->id;

        // 4. Create Combination
        $combination = new \Combination();
        $combination->id_product = $productId;
        $combination->reference = 'TEST-COMB-1';
        $combination->price = 5.0;
        $combination->quantity = 10;
        $combination->minimal_quantity = 1;
        $combination->add();
        $combinationId = (int) $combination->id;

        // Link attribute to combination
        $combination->setAttributes([$attributeId]);

        // 5. Call API
        $response = $this->getItem('/product/' . $productId . '/combinations', ['product_read']);

        // 6. Assert
        $this->assertCount(1, $response['items']);
        $this->assertEquals($combinationId, $response['items'][0]['combinationId']);
        $this->assertEquals($productId, $response['items'][0]['productId']);

        // Clean up
        $product->delete();
        $attributeGroup->delete();
        // Attribute and Combination should be deleted by cascade or we rely on resetter
    }
}
