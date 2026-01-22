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

use PrestaShop\PrestaShop\Core\Domain\Discount\Command\AddDiscountCommand;
use PrestaShop\PrestaShop\Core\Domain\Discount\ProductRuleGroupType;
use PrestaShop\PrestaShop\Core\Domain\Discount\ProductRuleType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Tests\Resources\DatabaseDump;
use Tests\Resources\Resetter\LanguageResetter;

class DiscountEndpointTest extends ApiTestCase
{
    public const CART_LEVEL = 'cart_level';
    public const PRODUCT_LEVEL = 'product_level';
    public const FREE_GIFT = 'free_gift';
    public const FREE_SHIPPING = 'free_shipping';
    public const ORDER_LEVEL = 'order_level';

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        // Check if the command exists, if it doesn't the tests won't run so nothing to init
        if (class_exists(AddDiscountCommand::class)) {
            LanguageResetter::resetLanguages();
            DatabaseDump::restoreTables([
                'cart_cart_rule',
                'cart_rule',
                'cart_rule_carrier',
                'cart_rule_combination',
                'cart_rule_compatible_types',
                'cart_rule_country',
                'cart_rule_group',
                'cart_rule_lang',
                'cart_rule_product_rule',
                'cart_rule_product_rule_group',
                'cart_rule_product_rule_value',
                'cart_rule_shop',
            ]);

            self::addLanguageByLocale('fr-FR');
            self::createApiClient(['discount_write', 'discount_read']);
        }
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();

        if (class_exists(AddDiscountCommand::class)) {
            LanguageResetter::resetLanguages();
        }
    }

    protected function setUp(): void
    {
        parent::setUp();
        // If the discount domain does not exist we can skip all the tests here
        if (!class_exists(AddDiscountCommand::class)) {
            $this->markTestSkipped('AddDiscountCommand class does not exist');
        }
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'get endpoint' => [
            'GET',
            '/discounts/1',
        ];

        yield 'create endpoint' => [
            'POST',
            '/discounts',
        ];

        yield 'update endpoint' => [
            'PATCH',
            '/discounts/1',
        ];

        yield 'list endpoint' => [
            'GET',
            '/discounts',
        ];

        yield 'delete endpoint' => [
            'DELETE',
            '/discounts/1',
        ];

        yield 'bulk toggle status endpoint' => [
            'PATCH',
            '/discounts/bulk-update-status',
        ];

        yield 'bulk delete endpoint' => [
            'DELETE',
            '/discounts/bulk-delete',
        ];
    }

    /**
     * @dataProvider discountTypesDataProvider
     *
     * @param string $type
     * @param array $names
     *
     * @return int
     */
    public function testAddDiscountAndGet(string $type, array $names, ?array $data): int
    {
        $creationData = [
            'type' => $type,
            'names' => $names,
        ];
        if ($data !== null) {
            $creationData = array_merge($creationData, $data);
        }

        $discount = $this->createItem('/discounts', $creationData, ['discount_write']);
        $this->assertArrayHasKey('discountId', $discount);
        $discountId = $discount['discountId'];

        $expectedDiscount = [
            'discountId' => $discountId,
            'type' => $type,
            'names' => $names,
            'description' => '',
            'code' => '',
            'enabled' => false,
            'totalQuantity' => null,
            'quantityPerUser' => null,
            'reductionPercent' => null,
            'reductionAmount' => null,
            'giftProductId' => null,
            'giftCombinationId' => null,
            'cheapestProduct' => false,
            'productConditions' => [],
            'minimumProductQuantity' => 0,
            'minimumAmount' => null,
            'customerId' => null,
            'customerGroupIds' => [],
            'carrierIds' => [],
            'countryIds' => [],
            'compatibleDiscountTypeIds' => [],
            'highlightInCart' => false,
            'allowPartialUse' => true,
            'priority' => 1,
            // These two values are dynamic, we can't hard-code the expected value
            'validFrom' => $discount['validFrom'],
            'validTo' => $discount['validTo'],
        ];
        if ($data !== null) {
            $expectedDiscount = array_merge($expectedDiscount, $data);
        }

        $this->assertEquals($expectedDiscount, $discount);
        // Now test that the GET request returns the same expected result
        $this->assertEquals($expectedDiscount, $this->getItem('/discounts/' . $discountId, ['discount_read']));

        return $discountId;
    }

    public static function discountTypesDataProvider(): iterable
    {
        yield 'cart level discount' => [
            self::CART_LEVEL,
            [
                'en-US' => 'new cart level discount',
                'fr-FR' => 'nouveau discount panier',
            ],
            null,
        ];

        yield 'product level discount' => [
            self::PRODUCT_LEVEL,
            [
                'en-US' => 'new product level discount',
                'fr-FR' => 'nouveau discount produit',
            ],
            [
                'cheapestProduct' => true,
                'reductionPercent' => 10.0,
            ],
        ];

        yield 'free gift discount' => [
            self::FREE_GIFT,
            [
                'en-US' => 'new free gift discount',
                'fr-FR' => 'nouveau discount produit offert',
            ],
            [
                'giftProductId' => 1,
            ],
        ];

        yield 'free shipping discount' => [
            self::FREE_SHIPPING,
            [
                'en-US' => 'new free shipping discount',
                'fr-FR' => 'nouveau discount frais de port offert',
            ],
            null,
        ];

        yield 'order level discount' => [
            self::ORDER_LEVEL,
            [
                'en-US' => 'new order level discount',
                'fr-FR' => 'nouveau discount commande',
            ],
            null,
        ];
    }

    /**
     * @depends testAddDiscountAndGet
     */
    public function testListDiscount(): void
    {
        $paginatedDiscounts = $this->listItems('/discounts', ['discount_read']);
        $createdDiscountData = [];
        $index = 0;
        foreach ($this->discountTypesDataProvider() as $discountData) {
            $createdDiscountData[$index] = $discountData;
            ++$index;
        }
        $this->assertEquals(count($createdDiscountData), $paginatedDiscounts['totalItems']);

        foreach ($paginatedDiscounts['items'] as $key => $discount) {
            $creationData = $createdDiscountData[$key];
            $expectedDiscount = [
                'discountId' => $discount['discountId'],
                'type' => $creationData[0],
                'name' => $creationData[1]['en-US'],
                'enabled' => false,
                'code' => '',
            ];
            $this->assertEquals($expectedDiscount, $discount);
        }
    }

    /**
     * @depends testListDiscount
     */
    public function testDeleteDiscount(): void
    {
        $this->deleteItem('/discounts/1', ['discount_write']);
        // Check that the discount no longer exists
        $this->getItem('/discounts/1', ['discount_read'], Response::HTTP_NOT_FOUND);
    }

    /**
     * Create a discount specifically for update tests
     *
     * @return array
     */
    public function testCreateDiscountForUpdateTests(): array
    {
        $discount = $this->createItem('/discounts', [
            'type' => self::CART_LEVEL,
            'names' => [
                'en-US' => 'Discount for update tests',
                'fr-FR' => 'Discount pour tests de mise à jour',
            ],
        ], ['discount_write']);
        $this->assertArrayHasKey('discountId', $discount);

        return $discount;
    }

    /**
     * @depends testCreateDiscountForUpdateTests
     *
     * @param array $discount
     *
     * @return array
     */
    public function testPartialUpdateDiscount(array $discount): array
    {
        $discountId = $discount['discountId'];
        $expectedDiscount = [
            'discountId' => $discountId,
            'type' => self::CART_LEVEL,
            'names' => [
                'en-US' => 'Discount for update tests',
                'fr-FR' => 'Discount pour tests de mise à jour',
            ],
            'description' => '',
            'code' => '',
            'enabled' => false,
            'totalQuantity' => null,
            'quantityPerUser' => null,
            'reductionPercent' => null,
            'reductionAmount' => null,
            'giftProductId' => null,
            'giftCombinationId' => null,
            'cheapestProduct' => false,
            'productConditions' => [],
            'minimumProductQuantity' => 0,
            'minimumAmount' => null,
            'customerId' => null,
            'customerGroupIds' => [],
            'carrierIds' => [],
            'countryIds' => [],
            'compatibleDiscountTypeIds' => [],
            'highlightInCart' => false,
            'allowPartialUse' => true,
            'priority' => 1,
            // These two values are dynamic, we can't hard-code the expected value
            'validFrom' => $discount['validFrom'],
            'validTo' => $discount['validTo'],
        ];

        $updatedFields = [
            'names' => [
                'en-US' => 'Updated EN name',
                'fr-FR' => 'Updated FR name',
            ],
            'description' => 'Updated description',
            'code' => 'NEWCODE123',
            'enabled' => true,
            'totalQuantity' => 100,
            'quantityPerUser' => 5,
            'highlightInCart' => true,
            'allowPartialUse' => false,
            'priority' => 10,
        ];

        // Update fields one by one and check that the content is updated accordingly
        foreach ($updatedFields as $key => $value) {
            $expectedDiscount[$key] = $value;
            $updatedDiscount = $this->partialUpdateItem('/discounts/' . $discountId, [
                $key => $value,
            ], ['discount_write']);
            $this->assertEquals($expectedDiscount, $updatedDiscount);
            // Also check that the discount is updated when we read it
            $this->assertEquals($expectedDiscount, $this->getItem('/discounts/' . $discountId, ['discount_read']));
        }

        return $expectedDiscount;
    }

    /**
     * @depends testPartialUpdateDiscount
     *
     * @param array $expectedDiscount
     *
     * @return array
     */
    public function testGetUpdatedDiscount(array $expectedDiscount): array
    {
        $discountId = $expectedDiscount['discountId'];
        $discount = $this->getItem('/discounts/' . $discountId, ['discount_read']);
        $this->assertEquals($expectedDiscount, $discount);

        return $expectedDiscount;
    }

    /**
     * @depends testGetUpdatedDiscount
     *
     * @param array $expectedDiscount
     *
     * @return int
     */
    public function testUpdateDiscountConditions(array $expectedDiscount): array
    {
        $discountId = $expectedDiscount['discountId'];
        $conditionFields = [
            'minimumProductQuantity' => 5,
            'carrierIds' => [1, 2],
            'countryIds' => [1, 2, 3],
            'customerId' => 42,
            'customerGroupIds' => [1, 2],
            // Hummingbird t-shirt
            'giftProductId' => 1,
            'giftCombinationId' => 1,
            'compatibleDiscountTypeIds' => [1, 2],
            'reductionPercent' => 0.5,
            'minimumAmount' => [
                'amount' => '50.00',
                'currencyId' => 1,
                'taxIncluded' => true,
                'shippingIncluded' => true,
            ],
        ];

        // Update fields one by one and check that the content is updated accordingly
        foreach ($conditionFields as $key => $value) {
            $expectedDiscount[$key] = $value;
            $updatedDiscount = $this->partialUpdateItem('/discounts/' . $discountId, [
                $key => $value,
            ], ['discount_write']);
            $this->assertEquals($expectedDiscount, $updatedDiscount, sprintf('Unexpected updated data for %s', $key));
            // Also check that the discount is updated when we read it
            $this->assertEquals($expectedDiscount, $this->getItem('/discounts/' . $discountId, ['discount_read']), sprintf('Unexpected get data for %s', $key));
        }

        // Update reduction amount automatically removes reduction percent
        $reductionAmount = [
            'amount' => '42.99',
            'currencyId' => 2,
            'taxIncluded' => false,
        ];
        $updatedDiscount = $this->partialUpdateItem('/discounts/' . $discountId, [
            'reductionAmount' => $reductionAmount,
        ], ['discount_write']);
        $expectedDiscount['reductionAmount'] = $reductionAmount;
        $expectedDiscount['reductionPercent'] = null;
        $this->assertEquals($expectedDiscount, $updatedDiscount);
        $this->assertEquals($expectedDiscount, $this->getItem('/discounts/' . $discountId, ['discount_read']));

        // And when percent is used the amount is null
        $updatedDiscount = $this->partialUpdateItem('/discounts/' . $discountId, [
            'reductionPercent' => 0.43,
        ], ['discount_write']);
        $expectedDiscount['reductionAmount'] = null;
        $expectedDiscount['reductionPercent'] = 0.43;
        $this->assertEquals($expectedDiscount, $updatedDiscount);
        $this->assertEquals($expectedDiscount, $this->getItem('/discounts/' . $discountId, ['discount_read']));

        // Unset the minimum amount
        $updatedDiscount = $this->partialUpdateItem('/discounts/' . $discountId, [
            'minimumAmount' => null,
        ], ['discount_write']);
        $expectedDiscount['minimumAmount'] = null;
        $this->assertEquals($expectedDiscount, $updatedDiscount);
        $this->assertEquals($expectedDiscount, $this->getItem('/discounts/' . $discountId, ['discount_read']));

        return $expectedDiscount;
    }

    /**
     * @depends testUpdateDiscountConditions
     *
     * @param array $expectedDiscount
     *
     * @return int
     */
    public function testUpdateDiscountProductConditions(array $expectedDiscount): int
    {
        $discountId = $expectedDiscount['discountId'];

        $simpleProductSelection = [
            [
                'quantity' => 5,
                'rules' => [
                    [
                        'type' => ProductRuleType::PRODUCTS->value,
                        'itemIds' => [1, 2, 3],
                    ],
                ],
                // This would be the default value even if left empty
                'type' => ProductRuleGroupType::AT_LEAST_ONE_PRODUCT_RULE->value,
            ],
        ];
        $productConditionsToTest = [
            'product selection' => $simpleProductSelection,
            'product segment' => [
                [
                    'quantity' => 5,
                    'rules' => [
                        [
                            'type' => ProductRuleType::CATEGORIES->value,
                            'itemIds' => [1, 2, 3],
                        ],
                    ],
                    // This would be the default value even if left empty
                    'type' => ProductRuleGroupType::ALL_PRODUCT_RULES->value,
                ],
            ],
            // Remove product conditions
            'no conditions' => [],
        ];

        foreach ($productConditionsToTest as $productConditionsUseCase => $productConditions) {
            $expectedDiscount['productConditions'] = $productConditions;
            $updatedDiscount = $this->partialUpdateItem('/discounts/' . $discountId, [
                'productConditions' => $productConditions,
            ], ['discount_write']);
            $this->assertEquals($expectedDiscount, $updatedDiscount, 'Unexpected value for use case ' . $productConditionsUseCase);
            // Also check that the discount is updated when we read it
            $this->assertEquals($expectedDiscount, $this->getItem('/discounts/' . $discountId, ['discount_read']));
        }

        // Special case: switch to the cheapest product, the product conditions is removed
        $updatedDiscount = $this->partialUpdateItem('/discounts/' . $discountId, [
            'cheapestProduct' => true,
        ], ['discount_write']);
        $expectedDiscount['productConditions'] = [];
        $expectedDiscount['cheapestProduct'] = true;
        $this->assertEquals($expectedDiscount, $updatedDiscount, 'Unexpected value for use case ' . $productConditionsUseCase);
        $this->assertEquals($expectedDiscount, $this->getItem('/discounts/' . $discountId, ['discount_read']));

        // And when we set a new product segment cheapestProduct is false
        $updatedDiscount = $this->partialUpdateItem('/discounts/' . $discountId, [
            'productConditions' => $simpleProductSelection,
        ], ['discount_write']);
        $expectedDiscount['productConditions'] = $simpleProductSelection;
        $expectedDiscount['cheapestProduct'] = false;
        $this->assertEquals($expectedDiscount, $updatedDiscount, 'Unexpected value for use case ' . $productConditionsUseCase);
        $this->assertEquals($expectedDiscount, $this->getItem('/discounts/' . $discountId, ['discount_read']));

        return $discountId;
    }

    /**
     * Test bulk enable discounts
     */
    public function testBulkEnableDiscounts(): array
    {
        // Create multiple discounts for bulk testing
        $discountIds = [];
        for ($i = 0; $i < 3; ++$i) {
            $discount = $this->createItem('/discounts', [
                'type' => self::CART_LEVEL,
                'names' => [
                    'en-US' => 'Bulk test discount ' . $i,
                    'fr-FR' => 'Discount test bulk ' . $i,
                ],
            ], ['discount_write']);
            $discountIds[] = $discount['discountId'];
        }

        // Bulk enable all discounts
        $this->partialUpdateItem('/discounts/bulk-update-status', [
            'discountIds' => $discountIds,
            'enabled' => true,
        ], ['discount_write'], Response::HTTP_NO_CONTENT);

        // Verify all discounts are enabled
        foreach ($discountIds as $discountId) {
            $discount = $this->getItem('/discounts/' . $discountId, ['discount_read']);
            $this->assertTrue($discount['enabled'], "Discount {$discountId} should be enabled");
        }

        return $discountIds;
    }

    /**
     * @depends testBulkEnableDiscounts
     *
     * Test bulk disable discounts
     */
    public function testBulkDisableDiscounts(array $discountIds): array
    {
        // Bulk disable all discounts
        $this->partialUpdateItem('/discounts/bulk-update-status', [
            'discountIds' => $discountIds,
            'enabled' => false,
        ], ['discount_write'], Response::HTTP_NO_CONTENT);

        // Verify all discounts are disabled
        foreach ($discountIds as $discountId) {
            $discount = $this->getItem('/discounts/' . $discountId, ['discount_read']);
            $this->assertFalse($discount['enabled'], "Discount {$discountId} should be disabled");
        }

        return $discountIds;
    }

    /**
     * @depends testBulkDisableDiscounts
     *
     * Test bulk delete discounts
     */
    public function testBulkDeleteDiscounts(array $discountIds): void
    {
        // Bulk delete all discounts
        $this->bulkDeleteItems('/discounts/bulk-delete', [
            'discountIds' => $discountIds,
        ], ['discount_write'], Response::HTTP_NO_CONTENT);

        // Verify all discounts are deleted
        foreach ($discountIds as $discountId) {
            $this->getItem('/discounts/' . $discountId, ['discount_read'], 404);
        }
    }

    /**
     * Test bulk enable with mixed valid and invalid IDs
     */
    public function testBulkEnableWithInvalidIds(): void
    {
        // Create one valid discount
        $discount = $this->createItem('/discounts', [
            'type' => self::CART_LEVEL,
            'names' => [
                'en-US' => 'Valid discount',
                'fr-FR' => 'Discount valide',
            ],
        ], ['discount_write']);
        $validId = $discount['discountId'];

        // Try to bulk enable with mixed valid and invalid IDs
        $this->partialUpdateItem('/discounts/bulk-update-status', [
            'discountIds' => [$validId, 999999],
            'enabled' => true,
        ], ['discount_write'], 422);
    }

    /**
     * Test bulk delete with empty array
     */
    public function testBulkDeleteWithEmptyArray(): void
    {
        $this->bulkDeleteItems('/discounts/bulk-delete', [
            'discountIds' => [],
        ], ['discount_write'], 422);
    }

    /**
     * Test bulk toggle status with empty array
     */
    public function testBulkToggleStatusWithEmptyArray(): void
    {
        $this->partialUpdateItem('/discounts/bulk-update-status', [
            'discountIds' => [],
            'enabled' => true,
        ], ['discount_write'], 422);
    }

    /**
     * Test bulk delete without providing discountIds parameter
     */
    public function testBulkDeleteWithMissingParameter(): void
    {
        $bearerToken = $this->getBearerToken(['discount_write']);
        static::createClient()->request('DELETE', '/discounts/bulk-delete', [
            'auth_bearer' => $bearerToken,
            'json' => [],
        ]);

        self::assertResponseStatusCodeSame(422);
    }

    /**
     * Test duplicate discount
     *
     * @depends testCreateDiscountForUpdateTests
     *
     * @param array $createdDiscount
     *
     * @return void
     */
    public function testDuplicateDiscount(array $createdDiscount): void
    {
        $discountId = $createdDiscount['discountId'];
        $originalDiscount = $this->getItem(
            '/discounts/' . $discountId,
            ['discount_read']
        );
        $newDiscount = $this->requestApi(
            httpMethod: Request::METHOD_POST,
            endPointUrl: '/discounts/' . $discountId . '/duplicate',
            scopes: ['discount_write']
        );

        $expectedDiscount = [
            'discountId' => $newDiscount['discountId'],
            'names' => [
                'en-US' => 'copy of ' . $originalDiscount['names']['en-US'],
                'fr-FR' => 'copie de ' . $originalDiscount['names']['fr-FR'],
            ],
            // New code has been generated
            'code' => $newDiscount['code'],
            // Status is forced to disabled
            'enabled' => false,
        ] + $originalDiscount;

        $this->assertEquals($expectedDiscount, $newDiscount);
        // New ID has been created
        $this->assertNotEquals($originalDiscount['discountId'], $newDiscount['discountId']);
        // New code has been created
        $this->assertNotEquals($originalDiscount['code'], $newDiscount['code']);
    }
}
