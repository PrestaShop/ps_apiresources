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
    public static function setUpBeforeClass(): void
    {
        // Ensure DB is restored before parent config/init
        DatabaseDump::restoreAllTables();
        parent::setUpBeforeClass();
        self::createApiClient(['product_write']);
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        DatabaseDump::restoreAllTables();
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
            'PATCH',
            '/products/combinations/1/images/clears',
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

    public function testGenerateProductCombinations(): void
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

        $this->createItem('/products/' . $productId . '/combinations', [
            'groupedAttributeIds' => [
                1 => [2, 3],
                2 => [10, 14],
            ],
        ], ['product_write']);

        // Ensure per-combination ProductSupplier rows exist (associate again now that combinations are created)
        $commandBus->handle(new SetSuppliersCommand(
            $productId,
            [1, 2]
        ));
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

    public function testBulkDeleteCombinations(): void
    {
        // Create product with combinations type
        $product = $this->createItem('/products', [
            'type' => 'combinations',
            'names' => [
                'en-US' => 'Combinations product',
            ],
        ], ['product_write']);
        $productId = $product['productId'];

        // Associate suppliers at product level BEFORE generating combinations so per-combination rows are created
        $container = static::createClient()->getContainer();
        $commandBus = $container->get('prestashop.core.command_bus');
        $commandBus->handle(new SetSuppliersCommand(
            $productId,
            [1, 2]
        ));

        // Then generate combinations
        $this->createItem('/products/' . $productId . '/combinations', [
            'groupedAttributeIds' => [
                1 => [2, 3],
                2 => [10, 14],
            ],
        ], ['product_write']);

        // Ensure per-combination ProductSupplier rows exist after creation
        $commandBus->handle(new SetSuppliersCommand(
            $productId,
            [1, 2]
        ));

        // Retrieve combination IDs from API JSON
        $list = $this->getItem('/products/' . $productId . '/combinations', ['product_read']);
        $this->assertIsArray($list);
        $this->assertArrayHasKey('combinations', $list);
        $combinationIds = array_map(static fn ($row) => $row['combinationId'], $list['combinations']);
        $this->assertGreaterThan(0, count($combinationIds));

        // Bulk delete combinations (PUT with body, productId in URL)
        $this->bulkDeleteItems('/products/' . $productId . '/combinations/bulk-delete', [
            'combinationIds' => $combinationIds,
        ], ['product_write'], Response::HTTP_NO_CONTENT);

        // Ensure there is no combinations left
        $list = $this->getItem('/products/' . $productId . '/combinations', ['product_read']);
        $this->assertIsArray($list);
        $this->assertArrayHasKey('totalCombinationsCount', $list);
        $this->assertSame(0, $list['totalCombinationsCount']);
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

    public function testDeleteSingleCombination(): void
    {
        // Create product with combinations type
        $product = $this->createItem('/products', [
            'type' => 'combinations',
            'names' => [
                'en-US' => 'Combinations product to delete one',
            ],
        ], ['product_write']);
        $productId = $product['productId'];

        // Generate combinations
        $this->createItem('/products/' . $productId . '/combinations', [
            'groupedAttributeIds' => [
                1 => [2, 3],
                2 => [10, 14],
            ],
        ], ['product_write']);

        // Fetch generated combination IDs from API JSON
        $list = $this->getItem('/products/' . $productId . '/combinations', ['product_read']);
        $this->assertIsArray($list);
        $this->assertArrayHasKey('combinations', $list);
        $this->assertGreaterThanOrEqual(2, count($list['combinations']));
        $toDelete = $list['combinations'][0]['combinationId'];

        // Delete single combination
        $this->deleteItem('/products/combinations/' . $toDelete, ['product_write']);

        // Ensure it is gone
        $remainingList = $this->getItem('/products/' . $productId . '/combinations', ['product_read']);
        $this->assertIsArray($remainingList);
        $ids = array_map(static fn ($row) => $row['combinationId'], $remainingList['combinations']);
        $this->assertNotContains($toDelete, $ids);
    }

    public function testPartialUpdateCombination(): void
    {
        // Create product and generate combinations
        $product = $this->createItem('/products', [
            'type' => 'combinations',
            'names' => [
                'en-US' => 'Combinations product to patch',
            ],
        ], ['product_write']);
        $productId = $product['productId'];

        $this->createItem('/products/' . $productId . '/combinations', [
            'groupedAttributeIds' => [
                1 => [2, 3],
                2 => [10, 14],
            ],
        ], ['product_write']);

        // Get one combination id from the API JSON
        $list = $this->getItem('/products/' . $productId . '/combinations', ['product_read']);
        $this->assertIsArray($list);
        $this->assertArrayHasKey('combinations', $list);
        $this->assertGreaterThanOrEqual(1, count($list['combinations']));
        $toPatch = $list['combinations'][0]['combinationId'];

        // Patch combination and expect updated details
        $updated = $this->partialUpdateItem('/products/combinations/' . $toPatch, [
            'reference' => 'REF-UPDATED',
            'isDefault' => false,
            'availableNowLabels' => [
                'en-US' => 'now',
            ],
        ], ['product_write']);
        $this->assertIsArray($updated);
        $this->assertArrayHasKey('combinationId', $updated);
    }

    public function testUpdateCombinationStockDeltaAndFixedAreExclusive(): void
    {
        // Create product and generate combinations
        $product = $this->createItem('/products', [
            'type' => 'combinations',
            'names' => [
                'en-US' => 'Combinations product stock test',
            ],
        ], ['product_write']);
        $productId = $product['productId'];

        $this->createItem('/products/' . $productId . '/combinations', [
            'groupedAttributeIds' => [
                1 => [2, 3],
                2 => [10, 14],
            ],
        ], ['product_write']);

        $list = $this->getItem('/products/' . $productId . '/combinations', ['product_read']);
        $this->assertIsArray($list);
        $this->assertArrayHasKey('combinations', $list);
        $this->assertGreaterThanOrEqual(1, count($list['combinations']));
        $toPatch = $list['combinations'][0]['combinationId'];

        // Send both delta and fixed to trigger domain validation -> 422
        \Context::getContext()->employee = new \Employee(1);
        $errors = $this->partialUpdateItem('/products/combinations/' . $toPatch . '/stocks', [
            'deltaQuantity' => 5,
            'fixedQuantity' => 10,
        ], ['product_write'], Response::HTTP_UNPROCESSABLE_ENTITY);

        $this->assertIsArray($errors);
    }

    public function testUpdateCombinationStockDelta(): void
    {
        // Create product and generate combinations
        $product = $this->createItem('/products', [
            'type' => 'combinations',
            'names' => [
                'en-US' => 'Combinations product stock delta',
            ],
        ], ['product_write']);
        $productId = $product['productId'];

        $this->createItem('/products/' . $productId . '/combinations', [
            'groupedAttributeIds' => [
                1 => [2, 3],
                2 => [10, 14],
            ],
        ], ['product_write']);

        $list = $this->getItem('/products/' . $productId . '/combinations', ['product_read']);
        $this->assertIsArray($list);
        $this->assertArrayHasKey('combinations', $list);
        $this->assertGreaterThanOrEqual(1, count($list['combinations']));
        $toPatch = $list['combinations'][0]['combinationId'];

        // Update with delta only - expect updated details
        \Context::getContext()->employee = new \Employee(1);
        $stockUpdated = $this->partialUpdateItem('/products/combinations/' . $toPatch . '/stocks', [
            'deltaQuantity' => 3,
            'location' => 'Rack A',
        ], ['product_write']);
        $this->assertIsArray($stockUpdated);
        $this->assertArrayHasKey('combinationId', $stockUpdated);
        $this->assertArrayHasKey('quantity', $stockUpdated);
        $this->assertArrayHasKey('location', $stockUpdated);
    }

    public function testGetCombinationForEditing(): void
    {
        // Create product and generate combinations
        $product = $this->createItem('/products', [
            'type' => 'combinations',
            'names' => [
                'en-US' => 'Combinations product get details',
            ],
        ], ['product_write']);
        $productId = $product['productId'];

        $this->createItem('/products/' . $productId . '/combinations', [
            'groupedAttributeIds' => [
                1 => [2, 3],
                2 => [10, 14],
            ],
        ], ['product_write']);

        // Get one combination id
        $ids = $this->getItem('/products/' . $productId . '/combinations/ids', ['product_read']);
        $combinationIds = array_map(static fn ($row) => $row['combinationId'], $ids);
        $this->assertGreaterThanOrEqual(1, count($combinationIds));
        $targetId = $combinationIds[0];

        // Call GET endpoint and assert structure
        $combination = $this->getItem('/products/combinations/' . $targetId, ['product_read']);
        $this->assertIsArray($combination);
        $this->assertArrayHasKey('combinationId', $combination);
        $this->assertEquals($targetId, $combination['combinationId']);
        // Some typical nullable/optional fields
        // Optional fields may be null or omitted depending on data; presence is not enforced here
    }

    public function testGetEditableCombinationsList(): void
    {
        // Create product and generate combinations
        $product = $this->createItem('/products', [
            'type' => 'combinations',
            'names' => [
                'en-US' => 'Combinations product list',
            ],
        ], ['product_write']);
        $productId = $product['productId'];

        $this->createItem('/products/' . $productId . '/combinations', [
            'groupedAttributeIds' => [
                1 => [2, 3],
                2 => [10, 14],
            ],
        ], ['product_write']);

        // GET list
        $list = $this->getItem('/products/' . $productId . '/combinations', ['product_read']);
        $this->assertIsArray($list);
        $this->assertArrayHasKey('totalCombinationsCount', $list);
        $this->assertArrayHasKey('combinations', $list);
        $this->assertArrayHasKey('productId', $list);
        $this->assertSame($productId, $list['productId']);
        $this->assertGreaterThan(0, $list['totalCombinationsCount']);
        $this->assertIsArray($list['combinations']);

        // Limit=1
        $limited = $this->getItem('/products/' . $productId . '/combinations?limit=1', ['product_read']);
        $this->assertIsArray($limited);
        $this->assertArrayHasKey('combinations', $limited);
        $this->assertIsArray($limited['combinations']);
        $this->assertCount(1, $limited['combinations']);
    }

    public function testGetCombinationStockMovements(): void
    {
        // Create product and generate combinations
        $product = $this->createItem('/products', [
            'type' => 'combinations',
            'names' => [
                'en-US' => 'Combinations product stock movements',
            ],
        ], ['product_write']);
        $productId = $product['productId'];

        $this->createItem('/products/' . $productId . '/combinations', [
            'groupedAttributeIds' => [
                1 => [2, 3],
                2 => [10, 14],
            ],
        ], ['product_write']);

        $list = $this->getItem('/products/' . $productId . '/combinations', ['product_read']);
        $this->assertIsArray($list);
        $this->assertArrayHasKey('combinations', $list);
        $this->assertGreaterThanOrEqual(1, count($list['combinations']));
        $targetId = $list['combinations'][0]['combinationId'];

        // Fetch stock movements
        $movements = $this->getItem('/products/combinations/' . $targetId . '/stock-movements?limit=3', ['product_read']);
        $this->assertIsArray($movements);
        if (!empty($movements)) {
            $first = $movements[0];
            $this->assertArrayHasKey('type', $first);
            $this->assertArrayHasKey('dates', $first);
            $this->assertArrayHasKey('deltaQuantity', $first);
            $this->assertArrayHasKey('stockMovementIds', $first);
        }
    }

    public function testGetCombinationSuppliers(): void
    {
        // Create product and generate combinations
        $product = $this->createItem('/products', [
            'type' => 'combinations',
            'names' => [
                'en-US' => 'Combinations product suppliers',
            ],
        ], ['product_write']);
        $productId = $product['productId'];

        $this->createItem('/products/' . $productId . '/combinations', [
            'groupedAttributeIds' => [
                1 => [2, 3],
                2 => [10, 14],
            ],
        ], ['product_write']);

        $list = $this->getItem('/products/' . $productId . '/combinations', ['product_read']);
        $this->assertIsArray($list);
        $this->assertArrayHasKey('combinations', $list);
        $this->assertGreaterThanOrEqual(1, count($list['combinations']));
        $targetId = $list['combinations'][0]['combinationId'];

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

    public function testUpdateCombinationSuppliers(): void
    {
        // Create product and generate combinations
        $product = $this->createItem('/products', [
            'type' => 'combinations',
            'names' => [
                'en-US' => 'Combinations product suppliers update',
            ],
        ], ['product_write']);
        $productId = $product['productId'];

        // Ensure product-level suppliers exist before generating combinations
        $container = static::createClient()->getContainer();
        $commandBus = $container->get('prestashop.core.command_bus');
        $commandBus->handle(new SetSuppliersCommand(
            $productId,
            [1, 2]
        ));

        $this->createItem('/products/' . $productId . '/combinations', [
            'groupedAttributeIds' => [
                1 => [2, 3],
                2 => [10, 14],
            ],
        ], ['product_write']);

        // Ensure per-combination ProductSupplier rows exist
        $commandBus->handle(new SetSuppliersCommand(
            $productId,
            [1, 2]
        ));

        $list = $this->getItem('/products/' . $productId . '/combinations', ['product_read']);
        $this->assertIsArray($list);
        $this->assertArrayHasKey('combinations', $list);
        $this->assertGreaterThanOrEqual(1, count($list['combinations']));
        $targetId = $list['combinations'][0]['combinationId'];

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
        ], ['product_write']);

        // Endpoint returns the suppliers list after update
        $this->assertIsArray($updated);
        $this->assertNotEmpty($updated);
        $this->assertArrayHasKey('supplierId', $updated[0]);
        $this->assertArrayHasKey('reference', $updated[0]);
    }

    public function testSetAndClearCombinationImages(): void
    {
        // Create product and generate combinations
        $product = $this->createItem('/products', [
            'type' => 'combinations',
            'names' => [
                'en-US' => 'Combinations product images',
            ],
        ], ['product_write']);
        $productId = $product['productId'];

        $this->createItem('/products/' . $productId . '/combinations', [
            'groupedAttributeIds' => [
                1 => [2, 3],
                2 => [10, 14],
            ],
        ], ['product_write']);

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

        // Get a combination id via API
        $ids = $this->getItem('/products/' . $productId . '/combinations/ids', ['product_read']);
        $combinationIds = array_map(static fn ($row) => $row['combinationId'], $ids);
        $this->assertGreaterThanOrEqual(1, count($combinationIds));
        $targetId = $combinationIds[0];

        // Set images on the combination
        $updated = $this->partialUpdateItem('/products/combinations/' . $targetId . '/images', [
            'imageIds' => [
                $image1['imageId'],
                $image2['imageId'],
            ],
        ], ['product_write']);
        $this->assertIsArray($updated);
        $this->assertArrayHasKey('combinationId', $updated);

        // Clear images on the combination
        $cleared = $this->partialUpdateItem('/products/combinations/' . $targetId . '/images/clears', null, ['product_write']);
        $this->assertIsArray($cleared);
        $this->assertArrayHasKey('combinationId', $cleared);
    }

    public function testSetCombinationImagesInvalidPayload(): void
    {
        // Create product and generate combinations
        $product = $this->createItem('/products', [
            'type' => 'combinations',
            'names' => [
                'en-US' => 'Combinations product images invalid',
            ],
        ], ['product_write']);
        $productId = $product['productId'];

        $this->createItem('/products/' . $productId . '/combinations', [
            'groupedAttributeIds' => [
                1 => [2, 3],
                2 => [10, 14],
            ],
        ], ['product_write']);

        $ids = $this->getItem('/products/' . $productId . '/combinations/ids', ['product_read']);
        $combinationIds = array_map(static fn ($row) => $row['combinationId'], $ids);
        $targetId = $combinationIds[0];

        // Missing/empty imageIds -> 422
        $this->partialUpdateItem('/products/combinations/' . $targetId . '/images', ['imageIds' => []], ['product_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testGetCombinationIds(): void
    {
        // Create a product with combinations type
        $product = $this->createItem('/products', [
            'type' => 'combinations',
            'names' => [
                'en-US' => 'Combinations product for ids',
            ],
        ], ['product_write']);
        $this->assertArrayHasKey('productId', $product);
        $productId = $product['productId'];

        $this->createItem('/products/' . $productId . '/combinations', [
            'groupedAttributeIds' => [
                1 => [2, 3],
                2 => [10, 14],
            ],
        ], ['product_write']);

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

    public function testSearchProductCombinations(): void
    {
        // Create product and generate combinations
        $product = $this->createItem('/products', [
            'type' => 'combinations',
            'names' => [
                'en-US' => 'Combinations product for search',
            ],
        ], ['product_write']);
        $productId = $product['productId'];

        $this->createItem('/products/' . $productId . '/combinations', [
            'groupedAttributeIds' => [
                1 => [2, 3],
                2 => [10, 14],
            ],
        ], ['product_write']);

        // Search with a generic small phrase and limited results
        $results = $this->getItem('/products/' . $productId . '/combinations/search?phrase=1&limit=5', ['product_read']);
        $this->assertIsArray($results);
        $this->assertArrayHasKey('productId', $results);
        $this->assertSame($productId, $results['productId']);
        $list = isset($results['combinations']) && is_array($results['combinations']) ? $results['combinations'] : $results;
        if (!empty($list)) {
            $first = array_values($list)[0];
            $this->assertArrayHasKey('combinationId', $first);
            $this->assertArrayHasKey('combinationName', $first);
        }
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
