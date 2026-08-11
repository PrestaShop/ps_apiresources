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
use Symfony\Component\HttpFoundation\Response;
use Tests\Resources\DatabaseDump;
use Tests\Resources\Resetter\ProductResetter;

class ProductSuppliersEndpointTest extends ApiTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        ProductResetter::resetProducts();
        self::createApiClient(['product_write', 'product_read', 'supplier_write']);
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        ProductResetter::resetProducts();
        DatabaseDump::restoreTables(['address', 'supplier', 'supplier_lang', 'supplier_shop', 'product_supplier']);
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'get product suppliers endpoint' => [
            'GET',
            '/products/1/suppliers',
        ];

        yield 'associate product suppliers endpoint' => [
            'PUT',
            '/products/1/suppliers',
        ];

        yield 'update product suppliers details endpoint' => [
            'PATCH',
            '/products/1/suppliers',
        ];

        yield 'remove all product suppliers endpoint' => [
            'DELETE',
            '/products/1/suppliers',
        ];

        yield 'set product default supplier endpoint' => [
            'PUT',
            '/products/1/default-supplier',
        ];
    }

    public function testGetSuppliersForProductWithoutSuppliers(): array
    {
        $product = $this->createItem('/products', [
            'type' => ProductType::TYPE_STANDARD,
            'names' => [
                'en-US' => 'product with suppliers',
                'fr-FR' => 'produit avec fournisseurs',
            ],
        ], ['product_write']);
        $this->assertArrayHasKey('productId', $product);
        $productId = $product['productId'];

        $this->assertEquals(
            [
                'productId' => $productId,
                'defaultSupplierId' => 0,
                'supplierIds' => [],
                'productSuppliers' => [],
            ],
            $this->getItem(sprintf('/products/%d/suppliers', $productId), ['product_read'])
        );

        $supplierIds = [];
        foreach (['first supplier', 'second supplier'] as $supplierName) {
            $supplier = $this->createItem('/suppliers', [
                'name' => $supplierName,
                'address' => 'My address',
                'postCode' => '12345',
                'city' => 'MyCity',
                'countryId' => (int) \Db::getInstance()->getValue('SELECT id_country FROM `' . _DB_PREFIX_ . 'country` WHERE iso_code="FR"'),
                'enabled' => true,
                'descriptions' => [
                    'en-US' => '',
                    'fr-FR' => '',
                ],
                'metaTitles' => [
                    'en-US' => '',
                    'fr-FR' => '',
                ],
                'metaDescriptions' => [
                    'en-US' => '',
                    'fr-FR' => '',
                ],
                'shopIds' => [1],
            ], ['supplier_write']);
            $this->assertArrayHasKey('supplierId', $supplier);
            $supplierIds[$supplierName] = $supplier['supplierId'];
        }

        return [
            'productId' => $productId,
            'supplierIds' => array_values($supplierIds),
        ];
    }

    /**
     * @depends testGetSuppliersForProductWithoutSuppliers
     */
    public function testAssociateSuppliers(array $fixtures): array
    {
        $productId = $fixtures['productId'];
        [$firstSupplierId, $secondSupplierId] = $fixtures['supplierIds'];

        $updatedSuppliers = $this->updateItem(sprintf('/products/%d/suppliers', $productId), [
            'supplierIds' => [$firstSupplierId, $secondSupplierId],
        ], ['product_write']);

        $this->assertArrayHasKey('productSuppliers', $updatedSuppliers);
        $this->assertCount(2, $updatedSuppliers['productSuppliers']);
        $productSupplierIds = array_column($updatedSuppliers['productSuppliers'], 'productSupplierId', 'supplierId');

        $expectedSuppliers = [
            'productId' => $productId,
            'defaultSupplierId' => $firstSupplierId,
            'supplierIds' => [$firstSupplierId, $secondSupplierId],
            'productSuppliers' => [
                [
                    'productSupplierId' => $productSupplierIds[$firstSupplierId],
                    'productId' => $productId,
                    'supplierId' => $firstSupplierId,
                    'supplierName' => 'first supplier',
                    'reference' => '',
                    'priceTaxExcluded' => '0.000000',
                    'currencyId' => 1,
                    'combinationId' => 0,
                ],
                [
                    'productSupplierId' => $productSupplierIds[$secondSupplierId],
                    'productId' => $productId,
                    'supplierId' => $secondSupplierId,
                    'supplierName' => 'second supplier',
                    'reference' => '',
                    'priceTaxExcluded' => '0.000000',
                    'currencyId' => 1,
                    'combinationId' => 0,
                ],
            ],
        ];
        $this->assertEquals($expectedSuppliers, $updatedSuppliers);
        // The GET endpoint returns the exact same content
        $this->assertEquals($expectedSuppliers, $this->getItem(sprintf('/products/%d/suppliers', $productId), ['product_read']));

        return $fixtures + ['productSupplierIds' => $productSupplierIds];
    }

    /**
     * @depends testAssociateSuppliers
     */
    public function testUpdateSupplierDetails(array $fixtures): array
    {
        $productId = $fixtures['productId'];
        [$firstSupplierId, $secondSupplierId] = $fixtures['supplierIds'];
        $productSupplierIds = $fixtures['productSupplierIds'];

        $updatedSuppliers = $this->partialUpdateItem(sprintf('/products/%d/suppliers', $productId), [
            'productSuppliers' => [
                [
                    'productSupplierId' => $productSupplierIds[$firstSupplierId],
                    'supplierId' => $firstSupplierId,
                    'currencyId' => 1,
                    'reference' => 'FIRST-SUPPLIER-REF',
                    'priceTaxExcluded' => '12.349000',
                ],
                [
                    'productSupplierId' => $productSupplierIds[$secondSupplierId],
                    'supplierId' => $secondSupplierId,
                    'currencyId' => 1,
                    'reference' => 'SECOND-SUPPLIER-REF',
                    'priceTaxExcluded' => '14.990000',
                ],
            ],
        ], ['product_write']);

        $this->assertEquals(
            [
                'productId' => $productId,
                'defaultSupplierId' => $firstSupplierId,
                'supplierIds' => [$firstSupplierId, $secondSupplierId],
                'productSuppliers' => [
                    [
                        'productSupplierId' => $productSupplierIds[$firstSupplierId],
                        'productId' => $productId,
                        'supplierId' => $firstSupplierId,
                        'supplierName' => 'first supplier',
                        'reference' => 'FIRST-SUPPLIER-REF',
                        'priceTaxExcluded' => '12.349000',
                        'currencyId' => 1,
                        'combinationId' => 0,
                    ],
                    [
                        'productSupplierId' => $productSupplierIds[$secondSupplierId],
                        'productId' => $productId,
                        'supplierId' => $secondSupplierId,
                        'supplierName' => 'second supplier',
                        'reference' => 'SECOND-SUPPLIER-REF',
                        'priceTaxExcluded' => '14.990000',
                        'currencyId' => 1,
                        'combinationId' => 0,
                    ],
                ],
            ],
            $updatedSuppliers
        );

        return $fixtures;
    }

    /**
     * @depends testUpdateSupplierDetails
     */
    public function testSetDefaultSupplier(array $fixtures): array
    {
        $productId = $fixtures['productId'];
        [, $secondSupplierId] = $fixtures['supplierIds'];

        $updatedSuppliers = $this->updateItem(sprintf('/products/%d/default-supplier', $productId), [
            'defaultSupplierId' => $secondSupplierId,
        ], ['product_write']);

        $this->assertEquals($secondSupplierId, $updatedSuppliers['defaultSupplierId']);

        return $fixtures;
    }

    /**
     * @depends testSetDefaultSupplier
     */
    public function testRemoveAllSuppliers(array $fixtures): void
    {
        $productId = $fixtures['productId'];

        $this->deleteItem(sprintf('/products/%d/suppliers', $productId), ['product_write']);

        $this->assertEquals(
            [
                'productId' => $productId,
                'defaultSupplierId' => 0,
                'supplierIds' => [],
                'productSuppliers' => [],
            ],
            $this->getItem(sprintf('/products/%d/suppliers', $productId), ['product_read'])
        );
    }

    public function testGetSuppliersForUnknownProduct(): void
    {
        $this->getItem('/products/99999999/suppliers', ['product_read'], Response::HTTP_NOT_FOUND);
    }
}
