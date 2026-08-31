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

use PrestaShop\PrestaShop\Adapter\Attribute\Repository\AttributeRepository;
use PrestaShop\PrestaShop\Adapter\AttributeGroup\Repository\AttributeGroupRepository;
use PrestaShop\PrestaShop\Core\Domain\AttributeGroup\ValueObject\AttributeGroupId;
use PrestaShop\PrestaShop\Core\Domain\Product\Combination\Command\UpdateCombinationStockAvailableCommand;
use PrestaShop\PrestaShop\Core\Domain\Product\Supplier\Command\SetSuppliersCommand;
use PrestaShop\PrestaShop\Core\Domain\Product\ValueObject\ProductType;
use PrestaShop\PrestaShop\Core\Domain\Shop\ValueObject\ShopConstraint;
use Symfony\Component\HttpFoundation\Response;
use Tests\Resources\DatabaseDump;
use Tests\Resources\Resetter\ProductResetter;

class ProductCombinationEndpointTest extends ApiTestCase
{
    /**
     * @var string[]
     */
    private const MUTATED_TABLES = [
        'product',
        'product_shop',
        'product_lang',
        'category_product',
        'product_attribute',
        'product_attribute_shop',
        'product_attribute_lang',
        'product_attribute_combination',
        'product_attribute_image',
        'image',
        'image_shop',
        'image_lang',
        'stock_available',
        'stock_mvt',
        'product_supplier',
    ];

    /**
     * @var array<string, int>
     */
    private static array $attributeGroupData = [];
    /**
     * @var array<string, int>
     */
    private static array $attributeData = [];

    public static function setUpBeforeClass(): void
    {
        // Ensure DB is restored before parent config/init
        DatabaseDump::restoreTables(self::MUTATED_TABLES);
        parent::setUpBeforeClass();
        ProductResetter::resetProducts();
        // Pre-create the API Client with the needed scopes, this way we reduce the number of created API Clients
        self::createApiClient(['product_write', 'product_read']);

        // Fetch data for attributes to use them more easily in the following tests
        /** @var AttributeGroupRepository $attributeGroupRepository */
        $attributeGroupRepository = self::getContainer()->get(AttributeGroupRepository::class);
        $attributeGroups = $attributeGroupRepository->getAttributeGroups(ShopConstraint::allShops());
        foreach ($attributeGroups as $attributeGroup) {
            // Store english name as the key
            self::$attributeGroupData[$attributeGroup->name[1]] = (int) $attributeGroup->id;
        }

        /** @var AttributeRepository $attributeRepository */
        $attributeRepository = self::getContainer()->get(AttributeRepository::class);
        $attributeGroupIds = array_map(static function (int $attributeGroupId) {
            return new AttributeGroupId($attributeGroupId);
        }, array_values(self::$attributeGroupData));
        $groupedAttributes = $attributeRepository->getGroupedAttributes(ShopConstraint::allShops(), $attributeGroupIds);
        foreach ($groupedAttributes as $attributeGroupId => $groupAttributes) {
            foreach ($groupAttributes as $attribute) {
                self::$attributeData[$attribute->name[1]] = (int) $attribute->id;
            }
        }
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        ProductResetter::resetProducts();
        DatabaseDump::restoreTables(self::MUTATED_TABLES);
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'generate combinations endpoint' => [
            'POST',
            '/products/1/generate-combinations',
        ];

        yield 'list combination IDs' => [
            'GET',
            '/products/1/combination-ids',
        ];

        yield 'get endpoint' => [
            'GET',
            '/products/combinations/1',
        ];

        yield 'update combination endpoint' => [
            'PATCH',
            '/products/combinations/1',
        ];

        yield 'delete combination endpoint' => [
            'DELETE',
            '/products/combinations/1',
        ];

        yield 'bulk delete combinations endpoint' => [
            'DELETE',
            '/products/1/combinations/bulk-delete',
        ];

        yield 'combination stock movements list endpoint' => [
            'GET',
            '/products/combinations/1/stock-movements',
        ];

        yield 'combination suppliers list endpoint' => [
            'GET',
            '/products/combinations/1/suppliers',
        ];

        yield 'combination suppliers update endpoint' => [
            'PATCH',
            '/products/combinations/1/suppliers',
        ];

        yield 'combination images set endpoint' => [
            'PATCH',
            '/products/combinations/1/images',
        ];

        yield 'combination images clear endpoint' => [
            'DELETE',
            '/products/combinations/1/images',
        ];
    }

    public function testAddProductWithCombinations(): int
    {
        $addedProduct = $this->createItem('/products', [
            'type' => ProductType::TYPE_COMBINATIONS,
            'names' => [
                'en-US' => 'product with combinations',
                'fr-FR' => 'produit avec combinaisons',
            ],
        ], ['product_write']);

        return $addedProduct['productId'];
    }

    /**
     * @depends testAddProductWithCombinations
     */
    public function testCreateProductCombinations(int $productId): array
    {
        $postData = [
            'groupedAttributes' => [
                [
                    'attributeGroupId' => self::$attributeGroupData['Color'],
                    'attributeIds' => [
                        self::$attributeData['Red'],
                        self::$attributeData['Black'],
                        self::$attributeData['Yellow'],
                    ],
                ],
                [
                    'attributeGroupId' => self::$attributeGroupData['Size'],
                    'attributeIds' => [
                        self::$attributeData['S'],
                        self::$attributeData['M'],
                        self::$attributeData['L'],
                    ],
                ],
            ],
        ];
        $createdCombinations = $this->createItem(sprintf('/products/%d/generate-combinations', $productId), $postData, ['product_write']);
        // 9 combinations should have been created (three sizes for each three colors)
        $this->assertCount(9, $createdCombinations['newCombinationIds']);
        $newCombinationIds = $createdCombinations['newCombinationIds'];
        foreach ($newCombinationIds as $combinationId) {
            $this->assertIsInt($combinationId);
        }
        $expectedResult = [
            'productId' => $productId,
            // We fill this value dynamically because we can't guess their values
            'newCombinationIds' => $newCombinationIds,
        ];
        $this->assertEquals($expectedResult, $createdCombinations);

        // Now we call the same endpoint with the same attributes
        $createdCombinations = $this->createItem(sprintf('/products/%d/generate-combinations', $productId), $postData, ['product_write']);
        $this->assertEquals([
            'productId' => $productId,
            // Since the combinations already exist no new combination is created
            'newCombinationIds' => [],
        ], $createdCombinations);

        // Now we call with extra attributes, only the missing combinations are created
        $postData['groupedAttributes'][0]['attributeIds'][] = self::$attributeData['White'];
        $postData['groupedAttributes'][1]['attributeIds'][] = self::$attributeData['XL'];

        // In total 16 combinations should be created, 9 have already been so 7 new combinations should be returned
        $createdCombinations = $this->createItem(sprintf('/products/%d/generate-combinations', $productId), $postData, ['product_write']);
        $this->assertCount(7, $createdCombinations['newCombinationIds']);
        $newCombinationIds = array_merge($newCombinationIds, $createdCombinations['newCombinationIds']);
        $this->assertCount(16, $newCombinationIds);

        // Now create new combinations from other attributes (we don't merge with previous postData)
        $postData = [
            'groupedAttributes' => [
                [
                    'attributeGroupId' => self::$attributeGroupData['Color'],
                    'attributeIds' => [
                        self::$attributeData['Blue'],
                    ],
                ],
                [
                    'attributeGroupId' => self::$attributeGroupData['Size'],
                    'attributeIds' => [
                        self::$attributeData['M'],
                        self::$attributeData['L'],
                    ],
                ],
            ],
        ];
        $createdCombinations = $this->createItem(sprintf('/products/%d/generate-combinations', $productId), $postData, ['product_write']);
        // Only two new combinations should have been created
        $this->assertCount(2, $createdCombinations['newCombinationIds']);
        $newCombinationIds = array_merge($newCombinationIds, $createdCombinations['newCombinationIds']);
        $this->assertCount(18, $newCombinationIds);

        return $newCombinationIds;
    }

    /**
     * @depends testAddProductWithCombinations
     * @depends testCreateProductCombinations
     */
    public function testListCombinationsIds(int $productId, array $newCombinationIds): array
    {
        $combinations = $this->getItem(sprintf('/products/%d/combination-ids', $productId), ['product_read']);
        $this->assertEquals([
            'productId' => $productId,
            'combinationIds' => $newCombinationIds,
        ], $combinations);

        // Now test pagination
        $resultsPerPage = 5;
        $pagesNumber = ceil(count($newCombinationIds) / $resultsPerPage);
        for ($page = 1; $page <= $pagesNumber; ++$page) {
            $offset = ($page - 1) * $resultsPerPage;
            $paginatedCombinations = $this->getItem(sprintf(
                '/products/%d/combination-ids?offset=%d&limit=%d',
                $productId,
                $offset,
                $resultsPerPage),
                ['product_read']
            );
            $expectedCombinationIds = array_slice($newCombinationIds, $offset, $resultsPerPage);
            $this->assertEquals([
                'productId' => $productId,
                'combinationIds' => $expectedCombinationIds,
            ], $paginatedCombinations);
        }

        return $newCombinationIds;
    }

    /**
     * @depends testAddProductWithCombinations
     * @depends testCreateProductCombinations
     */
    public function testCombinationList(int $productId, array $newCombinationIds): array
    {
        $paginatedCombinations = $this->listItems(sprintf('/products/%d/combinations', $productId), ['product_read']);
        $this->assertEquals(count($newCombinationIds), $paginatedCombinations['totalItems']);

        // Now check the expected format at least for the first two
        $this->assertEquals([
            'productId' => $productId,
            'combinationId' => $newCombinationIds[0],
            'name' => 'Size - S, Color - Red',
            'default' => true,
            'reference' => '',
            'impactOnPriceTaxExcluded' => 0.0,
            'ecoTax' => 0.0,
            'quantity' => 0,
            'imageUrl' => 'http://myshop.com/img/p/en-default-small_default.jpg',
            'attributes' => [
                [
                    'attributeGroupId' => self::$attributeGroupData['Size'],
                    'attributeGroupName' => 'Size',
                    'attributeId' => self::$attributeData['S'],
                    'attributeName' => 'S',
                ],
                [
                    'attributeGroupId' => self::$attributeGroupData['Color'],
                    'attributeGroupName' => 'Color',
                    'attributeId' => self::$attributeData['Red'],
                    'attributeName' => 'Red',
                ],
            ],
        ], $paginatedCombinations['items'][0]);
        $this->assertEquals([
            'productId' => $productId,
            'combinationId' => $newCombinationIds[1],
            'name' => 'Size - M, Color - Red',
            'default' => false,
            'reference' => '',
            'impactOnPriceTaxExcluded' => 0.0,
            'ecoTax' => 0.0,
            'quantity' => 0,
            'imageUrl' => 'http://myshop.com/img/p/en-default-small_default.jpg',
            'attributes' => [
                [
                    'attributeGroupId' => self::$attributeGroupData['Size'],
                    'attributeGroupName' => 'Size',
                    'attributeId' => self::$attributeData['M'],
                    'attributeName' => 'M',
                ],
                [
                    'attributeGroupId' => self::$attributeGroupData['Color'],
                    'attributeGroupName' => 'Color',
                    'attributeId' => self::$attributeData['Red'],
                    'attributeName' => 'Red',
                ],
            ],
        ], $paginatedCombinations['items'][1]);

        // Now test pagination
        $resultsPerPage = 5;
        $pagesNumber = ceil(count($newCombinationIds) / $resultsPerPage);
        for ($page = 1; $page <= $pagesNumber; ++$page) {
            $offset = ($page - 1) * $resultsPerPage;
            $paginatedCombinations = $this->getItem(sprintf(
                '/products/%d/combinations?offset=%d&limit=%d',
                $productId,
                $offset,
                $resultsPerPage),
                ['product_read']
            );
            $expectedCombinationIds = array_slice($newCombinationIds, $offset, $resultsPerPage);
            $paginatedCombinationIds = array_map(static function (array $combination): int {
                return $combination['combinationId'];
            }, $paginatedCombinations['items']);
            $this->assertEquals($expectedCombinationIds, $paginatedCombinationIds);
        }

        return $newCombinationIds;
    }

    /**
     * @depends testAddProductWithCombinations
     * @depends testListCombinationsIds
     *
     * @return int
     */
    public function testGetProductCombination(int $productId, array $newCombinationIds): int
    {
        $combinationId = $newCombinationIds[0];
        $combination = $this->getItem('/products/combinations/' . $combinationId, ['product_read']);

        // minimalQuantity, lowStockThreshold, availableNowLabels and availableLaterLabels were added on top of
        // the already-released GET response to support the new PATCH operation (see testPartialUpdateCombination).
        // availableDate is intentionally absent: it is null on a freshly generated combination, and null
        // properties are omitted from the response rather than serialized as null.
        $this->assertEquals([
            'productId' => $productId,
            'combinationId' => $combinationId,
            'name' => 'Size - S, Color - Red',
            'default' => true,
            'gtin' => '',
            'isbn' => '',
            'mpn' => '',
            'reference' => '',
            'upc' => '',
            'coverThumbnailUrl' => 'http://myshop.com/img/p/en-default-cart_default.jpg',
            'imageIds' => [],
            'impactOnPriceTaxExcluded' => 0.0,
            'impactOnPriceTaxIncluded' => 0.0,
            'impactOnUnitPriceTaxIncluded' => 0.0,
            'ecotaxTaxExcluded' => 0.0,
            'ecotaxTaxIncluded' => 0.0,
            'impactOnWeight' => 0.0,
            'wholesalePrice' => 0.0,
            'productTaxRate' => 6.0,
            'productPriceTaxExcluded' => 0.0,
            'productEcotaxTaxExcluded' => 0.0,
            'quantity' => 0,
            'minimalQuantity' => 1,
            'lowStockThreshold' => 0,
            'availableNowLabels' => [
                'en-US' => '',
                'fr-FR' => '',
            ],
            'availableLaterLabels' => [
                'en-US' => '',
                'fr-FR' => '',
            ],
        ], $combination);

        return $combinationId;
    }

    /**
     * @depends testAddProductWithCombinations
     * @depends testGetProductCombination
     */
    public function testGetCombinationSuppliers(int $productId, int $combinationId): void
    {
        // Combination-level supplier rows only exist once suppliers are associated at product level
        $commandBus = static::createClient()->getContainer()->get('prestashop.core.command_bus');
        $commandBus->handle(new SetSuppliersCommand($productId, [1, 2]));

        $suppliers = $this->getItem('/products/combinations/' . $combinationId . '/suppliers', ['product_read']);
        $this->assertIsArray($suppliers);
        if (!empty($suppliers)) {
            $first = $suppliers[0];
            $this->assertArrayHasKey('productSupplierId', $first);
            $this->assertArrayHasKey('productId', $first);
            $this->assertArrayHasKey('supplierId', $first);
            $this->assertArrayHasKey('supplierName', $first);
            $this->assertArrayHasKey('reference', $first);
            $this->assertArrayHasKey('priceTaxExcluded', $first);
            $this->assertArrayHasKey('currencyId', $first);
            $this->assertArrayHasKey('combinationId', $first);
        }
    }

    /**
     * @depends testAddProductWithCombinations
     * @depends testGetProductCombination
     */
    public function testUpdateCombinationSuppliers(int $productId, int $combinationId): void
    {
        $commandBus = static::createClient()->getContainer()->get('prestashop.core.command_bus');
        $commandBus->handle(new SetSuppliersCommand($productId, [1, 2]));

        // Update suppliers (use default suppliers 1 and 2, currency 1)
        $updated = $this->partialUpdateItem('/products/combinations/' . $combinationId . '/suppliers', [
            'combinationSuppliers' => [
                [
                    'supplierId' => 1,
                    'currencyId' => 1,
                    'reference' => 'SUP-REF-001',
                    'priceTaxExcluded' => '10.50',
                ],
                [
                    'supplierId' => 2,
                    'currencyId' => 1,
                    'reference' => 'SUP-REF-002',
                    'priceTaxExcluded' => '20.00',
                ],
            ],
        ], ['product_write'], Response::HTTP_NO_CONTENT);

        $this->assertNull($updated);

        // The write endpoint stays declarative; read the collection endpoint to assert persisted supplier data.
        $suppliers = $this->getItem('/products/combinations/' . $combinationId . '/suppliers', ['product_read']);
        $this->assertIsArray($suppliers);
        $this->assertNotEmpty($suppliers);
        $this->assertArrayHasKey('supplierId', $suppliers[0]);
        $this->assertArrayHasKey('reference', $suppliers[0]);
    }

    /**
     * @depends testAddProductWithCombinations
     * @depends testGetProductCombination
     */
    public function testUpdateCombinationSuppliersInvalidPayload(int $productId, int $combinationId): void
    {
        $commandBus = static::createClient()->getContainer()->get('prestashop.core.command_bus');
        $commandBus->handle(new SetSuppliersCommand($productId, [1, 2]));

        $errors = $this->partialUpdateItem('/products/combinations/' . $combinationId . '/suppliers', [
            'combinationSuppliers' => [],
        ], ['product_write'], Response::HTTP_UNPROCESSABLE_ENTITY);

        $this->assertIsArray($errors);
    }

    /**
     * @depends testAddProductWithCombinations
     * @depends testGetProductCombination
     */
    public function testUpdateCombinationSuppliersInvalidItemPayload(int $productId, int $combinationId): void
    {
        $commandBus = static::createClient()->getContainer()->get('prestashop.core.command_bus');
        $commandBus->handle(new SetSuppliersCommand($productId, [1, 2]));

        $errors = $this->partialUpdateItem('/products/combinations/' . $combinationId . '/suppliers', [
            'combinationSuppliers' => [
                [
                    'reference' => 'SUP-REF-INCOMPLETE',
                ],
            ],
        ], ['product_write'], Response::HTTP_UNPROCESSABLE_ENTITY);

        $this->assertIsArray($errors);
    }

    /**
     * @depends testAddProductWithCombinations
     * @depends testGetProductCombination
     */
    public function testSetAndClearCombinationImages(int $productId, int $combinationId): void
    {
        // Upload two images to the product
        $assetPath = __DIR__ . '/../../Resources/assets/image/Hummingbird_cushion.jpg';
        if (!file_exists($assetPath)) {
            // Fallback to an existing language flag image from assets if product image not present
            $assetPath = __DIR__ . '/../../Resources/assets/lang/en.jpg';
        }
        $upload = $this->prepareUploadedFile($assetPath);
        $image1 = $this->createItem('/products/' . $productId . '/images', null, ['product_write'], null, [
            'headers' => [
                'content-type' => 'multipart/form-data',
            ],
            'extra' => [
                'files' => [
                    'image' => $upload,
                ],
            ],
        ]);
        $this->assertArrayHasKey('imageId', $image1);
        $upload2 = $this->prepareUploadedFile($assetPath);
        $image2 = $this->createItem('/products/' . $productId . '/images', null, ['product_write'], null, [
            'headers' => [
                'content-type' => 'multipart/form-data',
            ],
            'extra' => [
                'files' => [
                    'image' => $upload2,
                ],
            ],
        ]);
        $this->assertArrayHasKey('imageId', $image2);

        // Set images on the combination
        $updated = $this->partialUpdateItem('/products/combinations/' . $combinationId . '/images', [
            'imageIds' => [
                $image1['imageId'],
                $image2['imageId'],
            ],
        ], ['product_write']);
        $this->assertIsArray($updated);
        $this->assertArrayHasKey('combinationId', $updated);
        $this->assertArrayHasKey('imageIds', $updated);
        $this->assertEqualsCanonicalizing([$image1['imageId'], $image2['imageId']], $updated['imageIds']);

        // Clear images on the combination
        $this->deleteItem('/products/combinations/' . $combinationId . '/images', ['product_write']);
    }

    /**
     * @depends testGetProductCombination
     */
    public function testSetCombinationImagesInvalidPayload(int $combinationId): void
    {
        // Missing/empty imageIds -> 422
        $this->partialUpdateItem('/products/combinations/' . $combinationId . '/images', ['imageIds' => []], ['product_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /**
     * @depends testGetProductCombination
     */
    public function testGetCombinationStockMovements(int $combinationId): void
    {
        // Add a stock movement so there is always at least one to assert against
        \Context::getContext()->employee = new \Employee(1);
        $commandBus = static::createClient()->getContainer()->get('prestashop.core.command_bus');
        $commandBus->handle(
            (new UpdateCombinationStockAvailableCommand($combinationId, ShopConstraint::allShops()))
                ->setDeltaQuantity(1)
        );

        // Fetch stock movements
        $movements = $this->getItem('/products/combinations/' . $combinationId . '/stock-movements?limit=3', ['product_read']);
        $this->assertIsArray($movements);
        $this->assertNotEmpty($movements);
        $first = $movements[0];
        $this->assertArrayHasKey('type', $first);
        $this->assertArrayHasKey('dates', $first);
        $this->assertArrayHasKey('deltaQuantity', $first);
        $this->assertArrayHasKey('stockMovementIds', $first);
    }

    /**
     * @depends testGetProductCombination
     */
    public function testPartialUpdateCombination(int $combinationId): void
    {
        // Patch combination and expect updated details
        $updated = $this->partialUpdateItem('/products/combinations/' . $combinationId, [
            'reference' => 'REF-UPDATED',
            'default' => false,
            'availableNowLabels' => [
                'en-US' => 'now',
                'fr-FR' => 'maintenant',
            ],
        ], ['product_write']);
        $this->assertIsArray($updated);
        $this->assertSame($combinationId, $updated['combinationId']);
        $this->assertSame('REF-UPDATED', $updated['reference']);
        $this->assertSame('now', $updated['availableNowLabels']['en-US']);
        $this->assertSame('maintenant', $updated['availableNowLabels']['fr-FR']);
    }

    /**
     * Deletes the same combination already exercised by the previous tests in this chain (suppliers, images,
     * stock movements, patch) — declared after them so it stays the last consumer of that combination in this file.
     *
     * @depends testAddProductWithCombinations
     * @depends testGetProductCombination
     */
    public function testDeleteSingleCombination(int $productId, int $combinationId): void
    {
        // Delete single combination
        $this->deleteItem('/products/combinations/' . $combinationId, ['product_write']);

        // Ensure it is gone
        $remainingList = $this->getItem('/products/' . $productId . '/combinations', ['product_read']);
        $this->assertIsArray($remainingList);
        $ids = array_map(static fn ($row) => $row['combinationId'], $remainingList['items']);
        $this->assertNotContains($combinationId, $ids);
    }

    /**
     * Bulk-deletes whatever combinations remain on the shared product — declared last among the consumers of
     * testAddProductWithCombinations in this file, since it empties the combination list.
     *
     * @depends testAddProductWithCombinations
     */
    public function testBulkDeleteCombinations(int $productId): void
    {
        // Retrieve remaining combination IDs from API JSON (explicit limit: the list endpoint paginates by
        // default, and this product carries 18 combinations from testCreateProductCombinations)
        $list = $this->getItem('/products/' . $productId . '/combinations?limit=100', ['product_read']);
        $this->assertIsArray($list);
        $combinationIds = array_map(static fn ($row) => $row['combinationId'], $list['items']);
        $this->assertGreaterThan(0, count($combinationIds));
        $this->assertSame($list['totalItems'], count($combinationIds));

        // Bulk delete combinations (DELETE with body, productId in URL)
        $this->bulkDeleteItems('/products/' . $productId . '/combinations/bulk-delete', [
            'combinationIds' => $combinationIds,
        ], ['product_write'], Response::HTTP_NO_CONTENT);

        // Ensure there is no combinations left
        $list = $this->getItem('/products/' . $productId . '/combinations', ['product_read']);
        $this->assertIsArray($list);
        $this->assertSame(0, $list['totalItems']);
    }

    public function testBulkDeleteCombinationsInvalidPayload(): void
    {
        // Missing required fields should return validation errors
        $product = $this->createItem('/products', [
            'type' => 'combinations',
            'names' => [
                'en-US' => 'Combinations product invalid bulk delete',
            ],
        ], ['product_write']);
        $productId = $product['productId'];
        $errors = $this->bulkDeleteItems('/products/' . $productId . '/combinations/bulk-delete', ['combinationIds' => []], ['product_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertIsArray($errors);
        $this->assertValidationErrors([
            [
                'propertyPath' => 'combinationIds',
                'message' => '',
            ],
        ], $errors);
    }
}
