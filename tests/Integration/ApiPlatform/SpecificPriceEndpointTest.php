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
use PrestaShop\PrestaShop\Core\Domain\ValueObject\Reduction;
use Symfony\Component\HttpFoundation\Response;
use Tests\Resources\Resetter\ProductResetter;

class SpecificPriceEndpointTest extends ApiTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        ProductResetter::resetProducts();
        // Pre-create the API Client with the needed scopes
        self::createApiClient(['product_write', 'product_read']);
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        ProductResetter::resetProducts();
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'get endpoint' => [
            'GET',
            '/products/specific-prices/{specificPriceId}',
        ];
        yield 'list endpoint' => [
            'GET',
            '/products/1/specific-prices',
        ];
        yield 'create endpoint' => [
            'POST',
            '/products/specific-prices',
        ];
        yield 'update endpoint' => [
            'PATCH',
            '/products/specific-prices/{specificPriceId}',
        ];
        yield 'delete endpoint' => [
            'DELETE',
            '/products/specific-prices/{specificPriceId}',
        ];
        yield 'update priority endpoint' => [
            'PATCH',
            '/products/{productId}/specific-price-priorities',
        ];
        yield 'delete priority endpoint' => [
            'DELETE',
            '/products/{productId}/specific-price-priorities',
        ];
    }

    /**
     * @return int
     */
    private function createTestProduct(): int
    {
        $product = $this->createItem('/products', [
            'type' => ProductType::TYPE_STANDARD,
            'names' => [
                'en-US' => 'Test Product for Specific Price',
            ],
            'priceTaxExcluded' => 100.0,
        ], ['product_write']);

        return $product['productId'];
    }

    public function testAddSpecificPrice(): int
    {
        $productId = $this->createTestProduct();

        $postData = [
            'productId' => $productId,
            'reductionType' => Reduction::TYPE_PERCENTAGE,
            'reductionValue' => '10.5',
            'includesTax' => true,
            'fixedPrice' => '-1', // Initial price
            'fromQuantity' => 1,
            'dateTimeFrom' => '2024-01-01T00:00:00+00:00',
            'dateTimeTo' => '2024-12-31T23:59:59+00:00',
        ];

        $specificPrice = $this->createItem(
            '/products/specific-prices',
            $postData,
            ['product_write']
        );

        $this->assertArrayHasKey('specificPriceId', $specificPrice);
        $specificPriceId = $specificPrice['specificPriceId'];
        $this->assertIsInt($specificPriceId);
        $this->assertGreaterThan(0, $specificPriceId);

        // Verify the returned data matches what we sent
        $this->assertEquals($productId, $specificPrice['productId']);
        $this->assertEquals(Reduction::TYPE_PERCENTAGE, $specificPrice['reductionType']);
        $this->assertEquals('10.5', (string) $specificPrice['reductionValue']);
        $this->assertTrue($specificPrice['includesTax']);
        $this->assertEquals(1, $specificPrice['fromQuantity']);
        $this->assertNotNull($specificPrice['dateTimeFrom']);
        $this->assertNotNull($specificPrice['dateTimeTo']);

        return $specificPriceId;
    }

    public function testAddSpecificPriceWithAmountReduction(): int
    {
        $productId = $this->createTestProduct();

        $postData = [
            'productId' => $productId,
            'reductionType' => Reduction::TYPE_AMOUNT,
            'reductionValue' => '5.50',
            'includesTax' => false,
            'fixedPrice' => '-1',
            'fromQuantity' => 2,
            'dateTimeFrom' => '2024-01-01T00:00:00+00:00',
            'dateTimeTo' => '2024-12-31T23:59:59+00:00',
        ];

        $specificPrice = $this->createItem(
            '/products/specific-prices',
            $postData,
            ['product_write']
        );

        $this->assertArrayHasKey('specificPriceId', $specificPrice);
        $this->assertEquals(Reduction::TYPE_AMOUNT, $specificPrice['reductionType']);
        $this->assertEquals('5.5', (string) $specificPrice['reductionValue']);
        $this->assertFalse($specificPrice['includesTax']);
        $this->assertEquals(2, $specificPrice['fromQuantity']);

        return $specificPrice['specificPriceId'];
    }

    public function testAddSpecificPriceWithOptionalFields(): int
    {
        $productId = $this->createTestProduct();

        $postData = [
            'productId' => $productId,
            'reductionType' => Reduction::TYPE_PERCENTAGE,
            'reductionValue' => '15',
            'includesTax' => true,
            'fixedPrice' => '80.00',
            'fromQuantity' => 3,
            'dateTimeFrom' => '2024-01-01T00:00:00+00:00',
            'dateTimeTo' => '2024-12-31T23:59:59+00:00',
            'shopId' => 1,
            'currencyId' => 1,
            'countryId' => 8,
            'groupId' => 3,
        ];

        $specificPrice = $this->createItem(
            '/products/specific-prices',
            $postData,
            ['product_write']
        );

        $this->assertArrayHasKey('specificPriceId', $specificPrice);
        $this->assertEquals(1, $specificPrice['shopId']);
        $this->assertEquals(1, $specificPrice['currencyId']);
        $this->assertEquals(8, $specificPrice['countryId']);
        $this->assertEquals(3, $specificPrice['groupId']);
        $this->assertEquals('80', (string) $specificPrice['fixedPrice']);

        return $specificPrice['specificPriceId'];
    }

    /**
     * Test case 1: Percentage reduction with a wide valid date range.
     */
    public function testAddSpecificPriceWithUnlimitedDates(): void
    {
        $productId = $this->createTestProduct();

        $postData = [
            'productId' => $productId,
            'reductionType' => Reduction::TYPE_PERCENTAGE,
            'reductionValue' => '4.0',
            'includesTax' => true,
            'fixedPrice' => '-1',
            'fromQuantity' => 1,
            'dateTimeFrom' => '1970-01-01',
            'dateTimeTo' => '2050-01-01',
        ];

        $specificPrice = $this->createItem(
            '/products/specific-prices',
            $postData,
            ['product_write']
        );

        $this->assertArrayHasKey('specificPriceId', $specificPrice);
        $this->assertEquals($productId, $specificPrice['productId']);
        $this->assertEquals(Reduction::TYPE_PERCENTAGE, $specificPrice['reductionType']);
        $this->assertEquals('4', (string) $specificPrice['reductionValue']);
        $this->assertTrue($specificPrice['includesTax']);
        $this->assertEquals('-1', (string) $specificPrice['fixedPrice']);
        $this->assertEquals(1, $specificPrice['fromQuantity']);
        $this->assertStringContainsString('1970-01-01', $specificPrice['dateTimeFrom']);
        $this->assertStringContainsString('2050-01-01', $specificPrice['dateTimeTo']);
    }

    /**
     * Test case 2: Amount reduction with a wide valid date range.
     */
    public function testAddSpecificPriceWithStartDateOnly(): void
    {
        $productId = $this->createTestProduct();

        $postData = [
            'productId' => $productId,
            'reductionType' => Reduction::TYPE_AMOUNT,
            'reductionValue' => '4.0',
            'includesTax' => true,
            'fixedPrice' => '-1',
            'fromQuantity' => 1,
            'dateTimeFrom' => '1970-01-01',
            'dateTimeTo' => '2050-01-01',
        ];

        $specificPrice = $this->createItem(
            '/products/specific-prices',
            $postData,
            ['product_write']
        );

        $this->assertArrayHasKey('specificPriceId', $specificPrice);
        $this->assertEquals($productId, $specificPrice['productId']);
        $this->assertEquals(Reduction::TYPE_AMOUNT, $specificPrice['reductionType']);
        $this->assertEquals('4', (string) $specificPrice['reductionValue']);
        $this->assertTrue($specificPrice['includesTax']);
        $this->assertEquals('-1', (string) $specificPrice['fixedPrice']);
        $this->assertEquals(1, $specificPrice['fromQuantity']);
        $this->assertStringContainsString('1970-01-01', $specificPrice['dateTimeFrom']);
        $this->assertStringContainsString('2050-01-01', $specificPrice['dateTimeTo']);
    }

    /**
     * Test case 3: Fixed price with specific date range
     * Similar to id 6 in the database: Fixed price 12€ from 2025-11-27 to 2025-12-28
     */
    public function testAddSpecificPriceWithFixedPriceAndDateRange(): void
    {
        $productId = $this->createTestProduct();

        $postData = [
            'productId' => $productId,
            'reductionType' => Reduction::TYPE_AMOUNT,
            'reductionValue' => '0.0',
            'includesTax' => false,
            'fixedPrice' => '12.0',
            'fromQuantity' => 1,
            'dateTimeFrom' => '2025-11-27T16:28:32+00:00',
            'dateTimeTo' => '2025-12-28T16:28:29+00:00',
        ];

        $specificPrice = $this->createItem(
            '/products/specific-prices',
            $postData,
            ['product_write']
        );

        $this->assertArrayHasKey('specificPriceId', $specificPrice);
        $this->assertEquals($productId, $specificPrice['productId']);
        $this->assertEquals(Reduction::TYPE_AMOUNT, $specificPrice['reductionType']);
        $this->assertEquals('0', (string) $specificPrice['reductionValue']);
        $this->assertFalse($specificPrice['includesTax']);
        $this->assertEquals('12', (string) $specificPrice['fixedPrice']);
        $this->assertEquals(1, $specificPrice['fromQuantity']);
        // Both dates should be set
        $this->assertArrayHasKey('dateTimeFrom', $specificPrice);
        $this->assertArrayHasKey('dateTimeTo', $specificPrice);
        $this->assertStringContainsString('2025-11-27', $specificPrice['dateTimeFrom']);
        $this->assertStringContainsString('2025-12-28', $specificPrice['dateTimeTo']);
    }

    /**
     * Test case 4: Amount reduction with constraints (country, group, currency) and tax excluded
     * Similar to id 7 in the database: 3€ reduction (tax excluded) limited to:
     * - Country: France (id 8)
     * - Customer group: Client (id 3)
     * - Currency: Euro (id 1)
     * - Date range: 2025-11-29 to 2025-12-31
     */
    public function testAddSpecificPriceWithConstraintsAndTaxExcluded(): void
    {
        $productId = $this->createTestProduct();

        $postData = [
            'productId' => $productId,
            'reductionType' => Reduction::TYPE_AMOUNT,
            'reductionValue' => '3.0',
            'includesTax' => false, // Tax excluded (hors taxe)
            'fixedPrice' => '-1',
            'fromQuantity' => 1,
            'dateTimeFrom' => '2025-11-29T16:31:10+00:00',
            'dateTimeTo' => '2025-12-31T16:30:59+00:00',
            'countryId' => 8, // France
            'groupId' => 3, // Client group
            'currencyId' => 1, // Euro
        ];

        $specificPrice = $this->createItem(
            '/products/specific-prices',
            $postData,
            ['product_write']
        );

        $this->assertArrayHasKey('specificPriceId', $specificPrice);
        $this->assertEquals($productId, $specificPrice['productId']);
        $this->assertEquals(Reduction::TYPE_AMOUNT, $specificPrice['reductionType']);
        $this->assertEquals('3', (string) $specificPrice['reductionValue']);
        $this->assertFalse($specificPrice['includesTax']); // Tax excluded
        $this->assertEquals('-1', (string) $specificPrice['fixedPrice']);
        $this->assertEquals(1, $specificPrice['fromQuantity']);
        // Constraints
        $this->assertEquals(8, $specificPrice['countryId']);
        $this->assertEquals(3, $specificPrice['groupId']);
        $this->assertEquals(1, $specificPrice['currencyId']);
        // Dates
        $this->assertStringContainsString('2025-11-29', $specificPrice['dateTimeFrom']);
        $this->assertStringContainsString('2025-12-31', $specificPrice['dateTimeTo']);
    }

    /**
     * @depends testAddSpecificPrice
     */
    public function testUpdateSpecificPrice(int $specificPriceId): int
    {
        $patchData = [
            'reductionType' => Reduction::TYPE_AMOUNT,
            'reductionValue' => '15.50',
            'includesTax' => false,
            'fromQuantity' => 2,
        ];

        $updatedSpecificPrice = $this->partialUpdateItem(
            '/products/specific-prices/' . $specificPriceId,
            $patchData,
            ['product_write'],
            Response::HTTP_OK
        );

        // Verify the updated data
        $this->assertEquals($specificPriceId, $updatedSpecificPrice['specificPriceId']);
        $this->assertEquals(Reduction::TYPE_AMOUNT, $updatedSpecificPrice['reductionType']);
        $this->assertEquals('15.5', (string) $updatedSpecificPrice['reductionValue']);
        $this->assertFalse($updatedSpecificPrice['includesTax']);
        $this->assertEquals(2, $updatedSpecificPrice['fromQuantity']);

        return $specificPriceId;
    }

    public function testUpdateSpecificPriceWithPartialFields(): void
    {
        $productId = $this->createTestProduct();

        // Create a specific price with constraints
        $postData = [
            'productId' => $productId,
            'reductionType' => Reduction::TYPE_PERCENTAGE,
            'reductionValue' => '10.0',
            'includesTax' => true,
            'fixedPrice' => '-1',
            'fromQuantity' => 1,
            'dateTimeFrom' => '2024-01-01T00:00:00+00:00',
            'dateTimeTo' => '2024-12-31T23:59:59+00:00',
            'countryId' => 8,
            'groupId' => 3,
        ];

        $specificPrice = $this->createItem(
            '/products/specific-prices',
            $postData,
            ['product_write']
        );

        $specificPriceId = $specificPrice['specificPriceId'];

        // Update only some fields (PATCH - partial update)
        // Note: reductionType is required when updating reductionValue
        $patchData = [
            'reductionType' => Reduction::TYPE_PERCENTAGE, // Keep the same type
            'reductionValue' => '20.0',
            'countryId' => 8, // Keep the same
            'currencyId' => 1, // Add new constraint
        ];

        $updatedSpecificPrice = $this->partialUpdateItem(
            '/products/specific-prices/' . $specificPriceId,
            $patchData,
            ['product_write'],
            Response::HTTP_OK
        );

        // Verify updated and unchanged fields
        $this->assertEquals('20', (string) $updatedSpecificPrice['reductionValue']);
        $this->assertEquals(8, $updatedSpecificPrice['countryId']);
        $this->assertEquals(1, $updatedSpecificPrice['currencyId']);
        $this->assertEquals(3, $updatedSpecificPrice['groupId']); // Should remain unchanged
    }

    /**
     * @depends testUpdateSpecificPrice
     */
    public function testDeleteSpecificPrice(int $specificPriceId): void
    {
        $this->deleteItem(
            '/products/specific-prices/' . $specificPriceId,
            ['product_write'],
            Response::HTTP_NO_CONTENT
        );
    }

    public function testInvalidSpecificPrice(): void
    {
        $validationErrorsResponse = $this->createItem('/products/specific-prices', [
            'reductionType' => '',
        ], ['product_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertIsArray($validationErrorsResponse);

        $this->assertValidationErrors([
            ['propertyPath' => 'productId'],
            ['propertyPath' => 'reductionType'],
            ['propertyPath' => 'reductionValue'],
            ['propertyPath' => 'includesTax'],
            ['propertyPath' => 'fixedPrice'],
            ['propertyPath' => 'fromQuantity'],
            ['propertyPath' => 'dateTimeFrom'],
            ['propertyPath' => 'dateTimeTo'],
        ], $validationErrorsResponse);
    }

    // ============================================
    // Tests for Specific Price Priority endpoints
    // ============================================

    public function testSetSpecificPricePriority(): int
    {
        $productId = $this->createTestProduct();

        $patchData = [
            'priorities' => [
                'id_shop',
                'id_country',
                'id_currency',
                'id_group',
            ],
        ];

        $this->partialUpdateItem(
            '/products/' . $productId . '/specific-price-priorities',
            $patchData,
            ['product_write'],
            Response::HTTP_NO_CONTENT
        );

        return $productId;
    }

    public function testSetSpecificPricePriorityWithDifferentOrder(): void
    {
        $productId = $this->createTestProduct();

        $patchData = [
            'priorities' => [
                'id_shop',
                'id_country',
                'id_group',
                'id_currency',
            ],
        ];

        $this->partialUpdateItem(
            '/products/' . $productId . '/specific-price-priorities',
            $patchData,
            ['product_write'],
            Response::HTTP_NO_CONTENT
        );
    }

    public function testSetSpecificPricePriorityWithPartialList(): void
    {
        $productId = $this->createTestProduct();

        $patchData = [
            'priorities' => [
                'id_currency',
                'id_country',
            ],
        ];

        $this->partialUpdateItem(
            '/products/' . $productId . '/specific-price-priorities',
            $patchData,
            ['product_write'],
            Response::HTTP_NO_CONTENT
        );
    }

    public function testInvalidSpecificPricePriority(): void
    {
        $productId = $this->createTestProduct();

        $validationErrorsResponse = $this->partialUpdateItem(
            '/products/' . $productId . '/specific-price-priorities',
            [
                'priorities' => [
                    'id_shop',
                    'invalid_priority',
                    'id_shop',
                ],
            ],
            ['product_write'],
            Response::HTTP_UNPROCESSABLE_ENTITY
        );
        $this->assertIsArray($validationErrorsResponse);

        $this->assertValidationErrors([
            ['propertyPath' => 'priorities[1]'],
            ['propertyPath' => 'priorities'],
        ], $validationErrorsResponse);
    }

    /**
     * @depends testSetSpecificPricePriority
     */
    public function testRemoveSpecificPricePriority(int $productId): void
    {
        $this->deleteItem(
            '/products/' . $productId . '/specific-price-priorities',
            ['product_write'],
            Response::HTTP_NO_CONTENT
        );
    }

    public function testGetSpecificPrice(): void
    {
        $productId = $this->createTestProduct();

        // First, create a specific price
        $postData = [
            'productId' => $productId,
            'reductionType' => Reduction::TYPE_PERCENTAGE,
            'reductionValue' => '10.0',
            'includesTax' => true,
            'fixedPrice' => '-1',
            'fromQuantity' => 1,
            'dateTimeFrom' => '2024-01-01T00:00:00+00:00',
            'dateTimeTo' => '2024-12-31T23:59:59+00:00',
        ];

        $specificPrice = $this->createItem(
            '/products/specific-prices',
            $postData,
            ['product_write']
        );

        $specificPriceId = $specificPrice['specificPriceId'];
        $this->assertIsInt($specificPriceId);
        $this->assertGreaterThan(0, $specificPriceId);

        // Then, retrieve it via GET
        $retrievedSpecificPrice = $this->getItem(
            '/products/specific-prices/' . $specificPriceId,
            ['product_read'],
            Response::HTTP_OK
        );

        // Verify the retrieved data matches
        $this->assertEquals($specificPriceId, $retrievedSpecificPrice['specificPriceId']);
        $this->assertEquals($productId, $retrievedSpecificPrice['productId']);
        $this->assertEquals(Reduction::TYPE_PERCENTAGE, $retrievedSpecificPrice['reductionType']);
        $this->assertEquals('10', (string) $retrievedSpecificPrice['reductionValue']);
        $this->assertTrue($retrievedSpecificPrice['includesTax']);
        $this->assertEquals(1, $retrievedSpecificPrice['fromQuantity']);
    }

    public function testGetSpecificPriceList(): void
    {
        $productId = $this->createTestProduct();

        // Create multiple specific prices
        $postData1 = [
            'productId' => $productId,
            'reductionType' => Reduction::TYPE_PERCENTAGE,
            'reductionValue' => '10.0',
            'includesTax' => true,
            'fixedPrice' => '-1',
            'fromQuantity' => 1,
            'dateTimeFrom' => '2024-01-01T00:00:00+00:00',
            'dateTimeTo' => '2024-12-31T23:59:59+00:00',
        ];

        $postData2 = [
            'productId' => $productId,
            'reductionType' => Reduction::TYPE_AMOUNT,
            'reductionValue' => '5.50',
            'includesTax' => false,
            'fixedPrice' => '-1',
            'fromQuantity' => 5,
            'dateTimeFrom' => '2024-06-01T00:00:00+00:00',
            'dateTimeTo' => '2024-12-31T23:59:59+00:00',
        ];

        $specificPrice1 = $this->createItem(
            '/products/specific-prices',
            $postData1,
            ['product_write']
        );

        $specificPrice2 = $this->createItem(
            '/products/specific-prices',
            $postData2,
            ['product_write']
        );

        // Retrieve the list
        $collection = $this->listItems(
            '/products/' . $productId . '/specific-prices',
            ['product_read'],
        );

        // Verify paginated response format and we have at least the 2 created specific prices
        $this->assertIsArray($collection);
        $this->assertArrayHasKey('totalItems', $collection);
        $this->assertArrayHasKey('items', $collection);
        $this->assertGreaterThanOrEqual(2, $collection['totalItems']);
        $this->assertIsArray($collection['items']);
        $this->assertGreaterThanOrEqual(2, count($collection['items']));

        // Find our specific prices in the collection
        $found1 = false;
        $found2 = false;
        foreach ($collection['items'] as $item) {
            if ($item['specificPriceId'] === $specificPrice1['specificPriceId']) {
                $found1 = true;
                $this->assertEquals($productId, $item['productId']);
                $this->assertEquals(Reduction::TYPE_PERCENTAGE, $item['reductionType']);
                $this->assertEquals('10', (string) $item['reductionValue']);
            }
            if ($item['specificPriceId'] === $specificPrice2['specificPriceId']) {
                $found2 = true;
                $this->assertEquals($productId, $item['productId']);
                $this->assertEquals(Reduction::TYPE_AMOUNT, $item['reductionType']);
                $this->assertEquals('5.5', (string) $item['reductionValue']);
            }
        }

        $this->assertTrue($found1, 'First specific price should be in the collection');
        $this->assertTrue($found2, 'Second specific price should be in the collection');
    }
}
