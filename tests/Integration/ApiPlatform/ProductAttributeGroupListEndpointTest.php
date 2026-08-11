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

use PrestaShop\PrestaShop\Core\Domain\Product\ValueObject\ProductType;
use Tests\Resources\DatabaseDump;
use Tests\Resources\Resetter\ProductResetter;

class ProductAttributeGroupListEndpointTest extends ApiTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        ProductResetter::resetProducts();
        self::createApiClient(['product_write', 'product_read', 'attribute_group_write', 'attribute_write']);
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        ProductResetter::resetProducts();
        DatabaseDump::restoreTables([
            'attribute_group',
            'attribute_group_lang',
            'attribute_group_shop',
            'attribute',
            'attribute_lang',
            'attribute_shop',
        ]);
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'get product attribute groups endpoint' => [
            'GET',
            '/products/1/attribute-groups',
        ];
    }

    public function testGetProductAttributeGroups(): void
    {
        // Create a dedicated attribute group with two attributes, purely via the API
        $attributeGroup = $this->createItem('/attributes/groups', [
            'names' => [
                'en-US' => 'Fabric',
                'fr-FR' => 'Tissu',
            ],
            'publicNames' => [
                'en-US' => 'Fabric public',
                'fr-FR' => 'Tissu public',
            ],
            'type' => 'select',
            'shopIds' => [1],
        ], ['attribute_group_write']);
        $this->assertArrayHasKey('attributeGroupId', $attributeGroup);
        $attributeGroupId = $attributeGroup['attributeGroupId'];

        $attributeIds = [];
        foreach (['Cotton', 'Silk'] as $attributeName) {
            $attribute = $this->createItem('/attributes/attributes', [
                'names' => [
                    'en-US' => $attributeName,
                    'fr-FR' => $attributeName . ' FR',
                ],
                'attributeGroupId' => $attributeGroupId,
                'color' => '',
                'shopIds' => [1],
            ], ['attribute_write']);
            $this->assertArrayHasKey('attributeId', $attribute);
            $attributeIds[$attributeName] = $attribute['attributeId'];
        }

        // Create a product and generate its combinations from the new attributes
        $product = $this->createItem('/products', [
            'type' => ProductType::TYPE_COMBINATIONS,
            'names' => [
                'en-US' => 'product with attributes',
                'fr-FR' => 'produit avec attributs',
            ],
        ], ['product_write']);
        $this->assertArrayHasKey('productId', $product);
        $productId = $product['productId'];

        $this->createItem(sprintf('/products/%d/generate-combinations', $productId), [
            'groupedAttributes' => [
                [
                    'attributeGroupId' => $attributeGroupId,
                    'attributeIds' => array_values($attributeIds),
                ],
            ],
        ], ['product_write']);

        $this->assertEquals(
            [
                [
                    'attributeGroupId' => $attributeGroupId,
                    'names' => [
                        'en-US' => 'Fabric',
                        'fr-FR' => 'Tissu',
                    ],
                    'publicNames' => [
                        'en-US' => 'Fabric public',
                        'fr-FR' => 'Tissu public',
                    ],
                    'type' => 'select',
                    'colorGroup' => false,
                    // The default catalog ships 4 attribute groups, the new one is appended after them
                    'position' => 4,
                    'attributes' => [
                        [
                            'attributeId' => $attributeIds['Cotton'],
                            'position' => 0,
                            'color' => '',
                            'names' => [
                                'en-US' => 'Cotton',
                                'fr-FR' => 'Cotton FR',
                            ],
                            'textureFilePath' => null,
                        ],
                        [
                            'attributeId' => $attributeIds['Silk'],
                            'position' => 1,
                            'color' => '',
                            'names' => [
                                'en-US' => 'Silk',
                                'fr-FR' => 'Silk FR',
                            ],
                            'textureFilePath' => null,
                        ],
                    ],
                ],
            ],
            $this->getItem(sprintf('/products/%d/attribute-groups', $productId), ['product_read'])
        );
    }

    public function testGetAttributeGroupsForProductWithoutCombinations(): void
    {
        $product = $this->createItem('/products', [
            'type' => ProductType::TYPE_STANDARD,
            'names' => [
                'en-US' => 'standard product',
                'fr-FR' => 'produit standard',
            ],
        ], ['product_write']);

        // A product without combinations has no attribute groups, the endpoint returns an empty list
        $this->assertEquals(
            [],
            $this->getItem(sprintf('/products/%d/attribute-groups', $product['productId']), ['product_read'])
        );
    }

    public function testGetAttributeGroupsForUnknownProduct(): void
    {
        // The core query does not check the product existence: an unknown product simply has no
        // combinations, so the endpoint returns an empty list (same contract as above)
        $this->assertEquals(
            [],
            $this->getItem('/products/99999999/attribute-groups', ['product_read'])
        );
    }
}
