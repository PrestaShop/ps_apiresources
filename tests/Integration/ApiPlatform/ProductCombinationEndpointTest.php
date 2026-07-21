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

use PrestaShop\PrestaShop\Core\Domain\Product\Supplier\Command\SetSuppliersCommand;
use Symfony\Component\HttpFoundation\Response;
use Tests\Resources\DatabaseDump;

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

    public static function setUpBeforeClass(): void
    {
        // Ensure DB is restored before parent config/init
        DatabaseDump::restoreTables(self::MUTATED_TABLES);
        parent::setUpBeforeClass();
        self::createApiClient(['product_write', 'product_read']);
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        DatabaseDump::restoreTables(self::MUTATED_TABLES);
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'generate combinations endpoint' => [
            'POST',
            '/products/1/combinations',
        ];

        yield 'bulk delete combinations endpoint' => [
            'DELETE',
            '/products/1/combinations/bulk-delete',
        ];

        yield 'delete combination endpoint' => [
            'DELETE',
            '/products/combinations/1',
        ];

        yield 'update combination endpoint' => [
            'PATCH',
            '/products/combinations/1',
        ];
        yield 'get combination endpoint' => [
            'GET',
            '/products/combinations/1',
        ];

        yield 'list combinations endpoint' => [
            'GET',
            '/products/1/combinations',
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

        yield 'update combination stock endpoint' => [
            'PATCH',
            '/products/combinations/1/stocks',
        ];

        yield 'get combination ids endpoint' => [
            'GET',
            '/products/1/combinations/ids',
        ];

        yield 'search product combinations endpoint' => [
            'GET',
            '/products/1/combinations/search',
        ];

        yield 'search combinations for association endpoint' => [
            'GET',
            '/products/combinations/associations/search',
        ];
    }

    /**
     * Creates a product with 4 generated combinations and associates suppliers to it.
     *
     * @return array{0: int, 1: array} [$productId, $items] where $items contains the 4 generated combinations
     */
    public function testGenerateProductCombinations(): array
    {
        // Create a product
        $product = $this->createItem('/products', [
            'type' => 'combinations',
            'names' => [
                'en-US' => 'Combinations product',
            ],
        ], ['product_write']);
        $this->assertArrayHasKey('productId', $product);
        $productId = $product['productId'];

        // Associate suppliers at product level BEFORE generating combinations so per-combination rows are created
        $container = static::createClient()->getContainer();
        $commandBus = $container->get('prestashop.core.command_bus');
        $commandBus->handle(new SetSuppliersCommand(
            $productId,
            [1, 2]
        ));

        $generated = $this->createItem('/products/' . $productId . '/combinations', [
            'groupedAttributeIds' => [
                1 => [2, 3],
                2 => [10, 14],
            ],
        ], ['product_write']);
        $this->assertIsArray($generated);
        $this->assertArrayHasKey('items', $generated);
        $this->assertArrayHasKey('totalItems', $generated);
        $this->assertCount(4, $generated['items']);
        $this->assertSame(4, $generated['totalItems']);

        // Ensure per-combination ProductSupplier rows exist (associate again now that combinations are created)
        $commandBus->handle(new SetSuppliersCommand(
            $productId,
            [1, 2]
        ));

        return [$productId, $generated['items']];
    }

    public function testGenerateProductCombinationsInvalidPayload(): void
    {
        // Missing required fields should return validation errors
        $product = $this->createItem('/products', [
            'type' => 'combinations',
            'names' => [
                'en-US' => 'Combinations product invalid',
            ],
        ], ['product_write']);
        $productId = $product['productId'];
        $errors = $this->createItem('/products/' . $productId . '/combinations', null, ['product_write'], Response::HTTP_UNPROCESSABLE_ENTITY, [
            'json' => (object) [],
        ]);
        $this->assertIsArray($errors);
        $this->assertValidationErrors([
            [
                'propertyPath' => 'groupedAttributeIds',
                'message' => '',
            ],
        ], $errors);
    }

    /**
     * @depends testGenerateProductCombinations
     */
    public function testGetEditableCombinationsList(array $generated): void
    {
        [$productId] = $generated;

        // GET list
        $list = $this->getItem('/products/' . $productId . '/combinations', ['product_read']);
        $this->assertIsArray($list);
        $this->assertGreaterThan(0, $list['totalItems']);
        $this->assertIsArray($list['items']);

        // Limit=1
        $limited = $this->getItem('/products/' . $productId . '/combinations?limit=1', ['product_read']);
        $this->assertIsArray($limited);
        $this->assertCount(1, $limited['items']);
    }

    /**
     * @depends testGenerateProductCombinations
     */
    public function testGetCombinationIds(array $generated): void
    {
        [$productId] = $generated;

        // Fetch IDs via API endpoint
        $ids = $this->getItem('/products/' . $productId . '/combinations/ids', ['product_read']);
        $this->assertIsArray($ids);
        $this->assertNotEmpty($ids);
        $this->assertIsArray($ids[0]);
        $this->assertArrayHasKey('combinationId', $ids[0]);
        $this->assertIsInt($ids[0]['combinationId']);

        // Limit to 1 via query parameter
        $idsLimited = $this->getItem('/products/' . $productId . '/combinations/ids?limit=1', ['product_read']);
        $this->assertIsArray($idsLimited);
        $this->assertCount(1, $idsLimited);
    }

    /**
     * @depends testGenerateProductCombinations
     */
    public function testSearchProductCombinations(array $generated): void
    {
        [$productId] = $generated;

        // Search with a generic small phrase and limited results
        $results = $this->getItem('/products/' . $productId . '/combinations/search?phrase=1&limit=5', ['product_read']);
        $this->assertIsArray($results);
        $this->assertArrayHasKey('productId', $results);
        $this->assertSame($productId, $results['productId']);
        $list = $results['combinations'];
        if (!empty($list)) {
            $first = array_values($list)[0];
            $this->assertArrayHasKey('combinationId', $first);
            $this->assertArrayHasKey('combinationName', $first);
        }
    }

    /**
     * @depends testGenerateProductCombinations
     */
    public function testGetCombinationForEditing(array $generated): void
    {
        [, $items] = $generated;
        $targetId = $items[0]['combinationId'];

        // Call GET endpoint and assert structure
        $combination = $this->getItem('/products/combinations/' . $targetId, ['product_read']);
        $this->assertIsArray($combination);
        $this->assertSame($targetId, $combination['combinationId']);
        $this->assertArrayHasKey('reference', $combination);
        $this->assertArrayHasKey('default', $combination);
        $this->assertArrayHasKey('impactOnPrice', $combination);
        $this->assertArrayHasKey('ecoTax', $combination);
        $this->assertArrayHasKey('availableNowLabels', $combination);
        $this->assertArrayHasKey('availableLaterLabels', $combination);
    }

    /**
     * @depends testGenerateProductCombinations
     */
    public function testGetCombinationSuppliers(array $generated): void
    {
        [, $items] = $generated;
        $targetId = $items[0]['combinationId'];

        // Fetch suppliers for the combination
        $suppliers = $this->getItem('/products/combinations/' . $targetId . '/suppliers', ['product_read']);
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
     * @depends testGenerateProductCombinations
     */
    public function testUpdateCombinationSuppliers(array $generated): void
    {
        [, $items] = $generated;
        $targetId = $items[0]['combinationId'];

        // Update suppliers (use default suppliers 1 and 2, currency 1)
        $updated = $this->partialUpdateItem('/products/combinations/' . $targetId . '/suppliers', [
            'combinationSuppliers' => [
                [
                    'supplier_id' => 1,
                    'currency_id' => 1,
                    'reference' => 'SUP-REF-001',
                    'price_tax_excluded' => '10.50',
                ],
                [
                    'supplier_id' => 2,
                    'currency_id' => 1,
                    'reference' => 'SUP-REF-002',
                    'price_tax_excluded' => '20.00',
                ],
            ],
        ], ['product_write'], Response::HTTP_NO_CONTENT);

        $this->assertNull($updated);

        // The write endpoint stays declarative; read the collection endpoint to assert persisted supplier data.
        $suppliers = $this->getItem('/products/combinations/' . $targetId . '/suppliers', ['product_read']);
        $this->assertIsArray($suppliers);
        $this->assertNotEmpty($suppliers);
        $this->assertArrayHasKey('supplierId', $suppliers[0]);
        $this->assertArrayHasKey('reference', $suppliers[0]);
    }

    /**
     * @depends testGenerateProductCombinations
     */
    public function testUpdateCombinationSuppliersInvalidPayload(array $generated): void
    {
        [, $items] = $generated;
        $targetId = $items[1]['combinationId'];

        $errors = $this->partialUpdateItem('/products/combinations/' . $targetId . '/suppliers', [
            'combinationSuppliers' => [],
        ], ['product_write'], Response::HTTP_UNPROCESSABLE_ENTITY);

        $this->assertIsArray($errors);
    }

    /**
     * @depends testGenerateProductCombinations
     */
    public function testSetAndClearCombinationImages(array $generated): void
    {
        [$productId, $items] = $generated;
        $targetId = $items[0]['combinationId'];

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
        $updated = $this->partialUpdateItem('/products/combinations/' . $targetId . '/images', [
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
        $this->deleteItem('/products/combinations/' . $targetId . '/images', ['product_write']);
    }

    /**
     * @depends testGenerateProductCombinations
     */
    public function testSetCombinationImagesInvalidPayload(array $generated): void
    {
        [, $items] = $generated;
        $targetId = $items[1]['combinationId'];

        // Missing/empty imageIds -> 422
        $this->partialUpdateItem('/products/combinations/' . $targetId . '/images', ['imageIds' => []], ['product_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /**
     * @depends testGenerateProductCombinations
     */
    public function testGetCombinationStockMovements(array $generated): void
    {
        [, $items] = $generated;
        $targetId = $items[0]['combinationId'];

        // Add a stock movement so there is always at least one to assert against
        \Context::getContext()->employee = new \Employee(1);
        $this->partialUpdateItem('/products/combinations/' . $targetId . '/stocks', [
            'deltaQuantity' => 1,
        ], ['product_write'], Response::HTTP_NO_CONTENT);

        // Fetch stock movements
        $movements = $this->getItem('/products/combinations/' . $targetId . '/stock-movements?limit=3', ['product_read']);
        $this->assertIsArray($movements);
        $this->assertNotEmpty($movements);
        $first = $movements[0];
        $this->assertArrayHasKey('type', $first);
        $this->assertArrayHasKey('dates', $first);
        $this->assertArrayHasKey('deltaQuantity', $first);
        $this->assertArrayHasKey('stockMovementIds', $first);
    }

    /**
     * @depends testGenerateProductCombinations
     */
    public function testUpdateCombinationStockDeltaAndFixedAreExclusive(array $generated): void
    {
        [, $items] = $generated;
        $targetId = $items[0]['combinationId'];

        // Send both delta and fixed to trigger domain validation -> 422
        \Context::getContext()->employee = new \Employee(1);
        $errors = $this->partialUpdateItem('/products/combinations/' . $targetId . '/stocks', [
            'deltaQuantity' => 5,
            'fixedQuantity' => 10,
        ], ['product_write'], Response::HTTP_UNPROCESSABLE_ENTITY);

        $this->assertIsArray($errors);
    }

    /**
     * @depends testGenerateProductCombinations
     */
    public function testUpdateCombinationStockDelta(array $generated): void
    {
        [, $items] = $generated;
        $targetId = $items[0]['combinationId'];

        // Update with delta only - command returns no body, stock can be verified through stock movements.
        \Context::getContext()->employee = new \Employee(1);
        $stockUpdated = $this->partialUpdateItem('/products/combinations/' . $targetId . '/stocks', [
            'deltaQuantity' => 3,
            'location' => 'Rack A',
        ], ['product_write'], Response::HTTP_NO_CONTENT);
        $this->assertNull($stockUpdated);
    }

    /**
     * @depends testGenerateProductCombinations
     */
    public function testPartialUpdateCombination(array $generated): void
    {
        [, $items] = $generated;
        $toPatch = $items[0]['combinationId'];

        // Patch combination and expect updated details
        $updated = $this->partialUpdateItem('/products/combinations/' . $toPatch, [
            'reference' => 'REF-UPDATED',
            'default' => false,
            'availableNowLabels' => [
                'en-US' => 'now',
                'fr-FR' => 'maintenant',
            ],
        ], ['product_write']);
        $this->assertIsArray($updated);
        $this->assertSame($toPatch, $updated['combinationId']);
        $this->assertSame('REF-UPDATED', $updated['reference']);
        $this->assertSame('now', $updated['availableNowLabels']['en-US']);
        $this->assertSame('maintenant', $updated['availableNowLabels']['fr-FR']);
    }

    /**
     * Deletes the same combination already exercised by the previous tests in this chain (get, suppliers, images,
     * stock, patch) — declared after them so it stays the last consumer of combination #0 in this file.
     *
     * @depends testGenerateProductCombinations
     */
    public function testDeleteSingleCombination(array $generated): void
    {
        [$productId, $items] = $generated;
        $toDelete = $items[0]['combinationId'];

        // Delete single combination
        $this->deleteItem('/products/combinations/' . $toDelete, ['product_write']);

        // Ensure it is gone
        $remainingList = $this->getItem('/products/' . $productId . '/combinations', ['product_read']);
        $this->assertIsArray($remainingList);
        $ids = array_map(static fn ($row) => $row['combinationId'], $remainingList['items']);
        $this->assertNotContains($toDelete, $ids);
    }

    /**
     * Bulk-deletes whatever combinations remain on the shared product — declared last among the consumers of
     * testGenerateProductCombinations in this file, since it empties the combination list.
     *
     * @depends testGenerateProductCombinations
     */
    public function testBulkDeleteCombinations(array $generated): void
    {
        [$productId] = $generated;

        // Retrieve remaining combination IDs from API JSON
        $list = $this->getItem('/products/' . $productId . '/combinations', ['product_read']);
        $this->assertIsArray($list);
        $combinationIds = array_map(static fn ($row) => $row['combinationId'], $list['items']);
        $this->assertGreaterThan(0, count($combinationIds));

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

    public function testSearchCombinationsForAssociationTooShortPhrase(): void
    {
        // phrase must be at least 3 chars, expect 422
        $this->getItem('/products/combinations/associations/search?phrase=ab&limit=5', ['product_read'], Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testSearchCombinationsForAssociation(): void
    {
        // Create product and generate combinations
        $product = $this->createItem('/products', [
            'type' => 'combinations',
            'names' => [
                'en-US' => 'Association search product',
            ],
        ], ['product_write']);
        $productId = $product['productId'];

        $this->createItem('/products/' . $productId . '/combinations', [
            'groupedAttributeIds' => [
                1 => [2, 3],
                2 => [10, 14],
            ],
        ], ['product_write']);

        // Search across catalog for combinations with generic 3-char phrase
        $results = $this->getItem('/products/combinations/associations/search?phrase=pro&limit=5', ['product_read']);
        $this->assertIsArray($results);
        if (!empty($results)) {
            $first = $results[0];
            $this->assertArrayHasKey('productId', $first);
            $this->assertArrayHasKey('combinationId', $first);
            $this->assertArrayHasKey('name', $first);
            $this->assertArrayHasKey('reference', $first);
            $this->assertArrayHasKey('imageUrl', $first);
        }
    }
}
