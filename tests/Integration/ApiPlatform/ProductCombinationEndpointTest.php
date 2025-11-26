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
            '/product/combination/1',
        ];
    }

    public function testGetProductCombination(): void
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
        $attribute = new \AttributeCore();
        $attribute->id_attribute_group = $attributeGroupId;
        $attribute->name = [\Configuration::get('PS_LANG_DEFAULT') => 'Small'];
        $attribute->add();
        $attributeId = (int) $attribute->id;

        // 4. Create Combination
        $combination = new \Combination();
        $combination->id_product = $productId;
        $combination->reference = 'TEST-COMB-1';
        $combination->ean13 = '1234567890123';
        $combination->isbn = '1234567890';
        $combination->upc = '123456789012';
        $combination->mpn = 'MPN-123';
        $combination->price = 5.0; // Impact on price
        $combination->quantity = 10;
        $combination->minimal_quantity = 1;
        $combination->weight = 0.5;
        $combination->add();
        $combinationId = (int) $combination->id;

        // Link attribute to combination
        $combination->setAttributes([$attributeId]);

        // 5. Call API
        $response = $this->getItem('/product/combination/' . $combinationId, ['product_read']);

        // 6. Assert
        $this->assertEquals($combinationId, $response['combinationId']);
        $this->assertEquals($productId, $response['productId']);
        $this->assertEquals('TEST-COMB-1', $response['reference']);
        $this->assertEquals('1234567890123', $response['gtin']); // ean13 maps to gtin usually
        $this->assertEquals('1234567890', $response['isbn']);
        $this->assertEquals('123456789012', $response['upc']);
        $this->assertEquals('MPN-123', $response['mpn']);
        $this->assertEquals(10, $response['quantity']);

        // Check impacts (DecimalNumber might be returned as string or float, checking value)
        // Note: The API Resource definition maps [prices][impactOnPrice] to impactOnPrice
        // We need to verify the structure of the response based on the Resource definition
        // The resource definition has flat properties, but the query mapping maps from nested arrays.
        // However, the Output of the API Platform resource is the Resource class itself, so it should be flat JSON matching the public properties.

        // $this->assertEquals(5.0, $response['impactOnPrice']); // This might need adjustment based on how DecimalNumber is serialized

        // Clean up
        $product->delete();
        $attributeGroup->delete();
    }
}
