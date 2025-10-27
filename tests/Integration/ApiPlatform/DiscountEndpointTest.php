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

        LanguageResetter::resetLanguages();
        DatabaseDump::restoreTables([
            'cart_cart_rule',
            'cart_rule',
            'cart_rule_carrier',
            'cart_rule_combination',
            'cart_rule_country',
            'cart_rule_group',
            'cart_rule_lang',
            'cart_rule_product_rule',
            'cart_rule_product_rule_group',
            'cart_rule_product_rule_value',
            'cart_rule_shop',
        ]);

        self::addLanguageByLocale('fr-FR');
        // Check if the command exists, if it doesn't the scopes are not usable
        if (class_exists(AddDiscountCommand::class)) {
            self::createApiClient(['discount_write', 'discount_read']);
        }
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();

        LanguageResetter::resetLanguages();
    }

    protected function setUp(): void
    {
        parent::setUp();
        // If the discount domain does not exist we can skip all the tests here
        if (!class_exists(AddDiscountCommand::class)) {
            $this->markTestSkipped('AddDiscountCommand class does not exist');
        }
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
        $json = [
            'type' => $type,
            'names' => $names,
        ];
        if ($data !== null) {
            $json = array_merge($json, $data);
        }

        $discount = $this->createItem('/discount', $json, ['discount_write']);
        $this->assertArrayHasKey('discountId', $discount);
        $discountId = $discount['discountId'];

        $expectedDiscount = [
            'discountId' => $discountId,
            'type' => $type,
            'names' => $names,
            'priority' => 1,
            'active' => false,
            'totalQuantity' => 1,
            'quantityPerUser' => 1,
            'description' => '',
            'code' => '',
            'customerId' => 0,
            'highlightInCart' => false,
            'allowPartialUse' => true,
            'currencyId' => 1,
            'reductionProduct' => 0,
            // These two values are dynamic, we can't hard-code the expected value
            'validFrom' => $discount['validFrom'],
            'validTo' => $discount['validTo'],
        ];
        if ($data !== null) {
            $expectedDiscount = array_merge($expectedDiscount, $data);
        }

        $this->assertEquals($expectedDiscount, $discount);
        // Now test that the GET request returns the same expected result
        $this->assertEquals($expectedDiscount, $this->getItem('/discount/' . $discountId, ['discount_read']));

        return $discountId;
    }

    public function discountTypesDataProvider(): array
    {
        return [
            [
                self::CART_LEVEL,
                [
                    'en-US' => 'new cart level discount',
                    'fr-FR' => 'nouveau discount panier',
                ],
                null,
            ],
            [
                self::PRODUCT_LEVEL,
                [
                    'en-US' => 'new product level discount',
                    'fr-FR' => 'nouveau discount produit',
                ],
                [
                    'reductionProduct' => -1,
                    'percentDiscount' => 20.0,
                ],
            ],
            // todo: This one must be improved, the naming productId is not correct, it should be giftProductId
            /*[
                self::FREE_GIFT,
                [
                    'en-US' => 'new free gift discount',
                    'fr-FR' => 'nouveau discount produit offert',
                ],
                [
                    'productId' => 1,
                ],
            ],*/
            [
                self::FREE_SHIPPING,
                [
                    'en-US' => 'new free shipping discount',
                    'fr-FR' => 'nouveau discount frais de port offert',
                ],
                null,
            ],
            [
                self::ORDER_LEVEL,
                [
                    'en-US' => 'new order level discount',
                    'fr-FR' => 'nouveau discount commande',
                ],
                null,
            ],
        ];
    }

    /**
     * @depends testAddDiscountAndGet
     */
    public function testListDiscount(): void
    {
        $paginatedDiscounts = $this->listItems('/discounts', ['discount_read']);
        $createdDiscountData = $this->discountTypesDataProvider();
        $this->assertEquals(count($createdDiscountData), $paginatedDiscounts['totalItems']);

        foreach ($paginatedDiscounts['items'] as $key => $discount) {
            $creationData = $createdDiscountData[$key];
            $expectedDiscount = [
                'discountId' => $discount['discountId'],
                'type' => $creationData[0],
                'name' => $creationData[1]['en-US'],
                'active' => false,
                'code' => '',
            ];
            $this->assertEquals($expectedDiscount, $discount);
        }
    }

    public function testDeleteDiscount(): void
    {
        $bearerToken = $this->getBearerToken(['discount_write']);
        static::createClient()->request('DELETE', '/discount/1', [
            'auth_bearer' => $bearerToken,
        ]);
        self::assertResponseStatusCodeSame(204);
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'get endpoint' => [
            'GET',
            '/discount/1',
        ];

        yield 'create endpoint' => [
            'POST',
            '/discount',
        ];

        yield 'update endpoint' => [
            'PATCH',
            '/discount/1',
        ];

        yield 'list endpoint' => [
            'GET',
            '/discounts',
        ];

        yield 'delete endpoint' => [
            'DELETE',
            '/discount/1',
        ];

        yield 'get conditions endpoint' => [
            'GET',
            '/discount/1/conditions',
        ];

        yield 'update conditions endpoint' => [
            'PATCH',
            '/discount/1/conditions',
        ];
    }

    /**
     * Create a discount specifically for update tests
     *
     * @return int
     */
    public function testCreateDiscountForUpdateTests(): int
    {
        $discount = $this->createItem('/discount', [
            'type' => self::CART_LEVEL,
            'names' => [
                'en-US' => 'Discount for update tests',
                'fr-FR' => 'Discount pour tests de mise à jour',
            ],
        ], ['discount_write']);
        $this->assertArrayHasKey('discountId', $discount);

        return $discount['discountId'];
    }

    /**
     * @depends testCreateDiscountForUpdateTests
     *
     * @param int $discountId
     *
     * @return int
     */
    public function testPartialUpdateDiscount(int $discountId): int
    {
        $updatedDiscount = $this->partialUpdateItem('/discount/' . $discountId, [
            'description' => 'Updated description',
        ], ['discount_write']);
        $this->assertEquals('Updated description', $updatedDiscount['description']);

        $updatedDiscount = $this->partialUpdateItem('/discount/' . $discountId, [
            'code' => 'NEWCODE123',
        ], ['discount_write']);
        $this->assertEquals('NEWCODE123', $updatedDiscount['code']);

        $updatedDiscount = $this->partialUpdateItem('/discount/' . $discountId, [
            'active' => true,
        ], ['discount_write']);
        $this->assertEquals(true, $updatedDiscount['active']);

        $updatedDiscount = $this->partialUpdateItem('/discount/' . $discountId, [
            'totalQuantity' => 100,
        ], ['discount_write']);
        $this->assertEquals(100, $updatedDiscount['totalQuantity']);

        $updatedDiscount = $this->partialUpdateItem('/discount/' . $discountId, [
            'quantityPerUser' => 5,
        ], ['discount_write']);
        $this->assertEquals(5, $updatedDiscount['quantityPerUser']);

        $updatedDiscount = $this->partialUpdateItem('/discount/' . $discountId, [
            'highlightInCart' => true,
        ], ['discount_write']);
        $this->assertEquals(true, $updatedDiscount['highlightInCart']);

        $updatedDiscount = $this->partialUpdateItem('/discount/' . $discountId, [
            'names' => [
                'en-US' => 'Updated EN name',
                'fr-FR' => 'Updated FR name',
            ],
        ], ['discount_write']);
        $this->assertEquals('Updated EN name', $updatedDiscount['names']['en-US']);
        $this->assertEquals('Updated FR name', $updatedDiscount['names']['fr-FR']);

        return $discountId;
    }

    /**
     * @depends testPartialUpdateDiscount
     *
     * @param int $discountId
     *
     * @return int
     */
    public function testGetUpdatedDiscount(int $discountId): int
    {
        $discount = $this->getItem('/discount/' . $discountId, ['discount_read']);
        $this->assertEquals('Updated description', $discount['description']);
        $this->assertEquals('NEWCODE123', $discount['code']);
        $this->assertEquals(true, $discount['active']);
        $this->assertEquals(100, $discount['totalQuantity']);
        $this->assertEquals(5, $discount['quantityPerUser']);
        $this->assertEquals(true, $discount['highlightInCart']);
        $this->assertEquals('Updated EN name', $discount['names']['en-US']);
        $this->assertEquals('Updated FR name', $discount['names']['fr-FR']);

        return $discountId;
    }

    /**
     * @depends testGetUpdatedDiscount
     *
     * @param int $discountId
     *
     * @return int
     */
    public function testGetDiscountConditions(int $discountId): int
    {
        $conditions = $this->getItem('/discount/' . $discountId . '/conditions', ['discount_read']);
        $this->assertArrayHasKey('discountId', $conditions);
        $this->assertEquals($discountId, $conditions['discountId']);

        return $discountId;
    }

    /**
     * @depends testGetDiscountConditions
     *
     * @param int $discountId
     *
     * @return int
     */
    public function testUpdateDiscountConditions(int $discountId): int
    {
        $updatedConditions = $this->partialUpdateItem('/discount/' . $discountId . '/conditions', [
            'minimumProductsQuantity' => 5,
        ], ['discount_write']);
        $this->assertArrayHasKey('discountId', $updatedConditions);
        $this->assertEquals($discountId, $updatedConditions['discountId']);

        $updatedConditions = $this->partialUpdateItem('/discount/' . $discountId . '/conditions', [
            'carrierIds' => [1, 2],
        ], ['discount_write']);
        $this->assertArrayHasKey('discountId', $updatedConditions);
        $this->assertEquals($discountId, $updatedConditions['discountId']);

        $updatedConditions = $this->partialUpdateItem('/discount/' . $discountId . '/conditions', [
            'countryIds' => [1, 2, 3],
        ], ['discount_write']);
        $this->assertArrayHasKey('discountId', $updatedConditions);
        $this->assertEquals($discountId, $updatedConditions['discountId']);

        $updatedConditions = $this->partialUpdateItem('/discount/' . $discountId . '/conditions', [
            'amountDiscount' => '50.00',
            'currencyId' => 1,
            'taxIncluded' => true,
        ], ['discount_write']);
        $this->assertArrayHasKey('discountId', $updatedConditions);
        $this->assertEquals($discountId, $updatedConditions['discountId']);

        return $discountId;
    }

    /**
     * @depends testUpdateDiscountConditions
     *
     * @param int $discountId
     *
     * @return int
     */
    public function testUpdateDiscountProductConditions(int $discountId): int
    {
        $updatedConditions = $this->partialUpdateItem('/discount/' . $discountId . '/conditions', [
            'productConditions' => [],
        ], ['discount_write']);
        $this->assertArrayHasKey('discountId', $updatedConditions);
        $this->assertEquals($discountId, $updatedConditions['discountId']);

        return $discountId;
    }

    /**
     * @depends testUpdateDiscountProductConditions
     *
     * @param int $discountId
     *
     * @return void
     */
    public function testGetUpdatedDiscountConditions(int $discountId): void
    {
        $conditions = $this->getItem('/discount/' . $discountId . '/conditions', ['discount_read']);
        $this->assertArrayHasKey('discountId', $conditions);
        $this->assertEquals($discountId, $conditions['discountId']);

        $this->assertArrayHasKey('minimumProductsQuantity', $conditions);
        $this->assertArrayHasKey('productConditions', $conditions);
        $this->assertArrayHasKey('carrierIds', $conditions);
        $this->assertArrayHasKey('countryIds', $conditions);

        $this->addToAssertionCount(1);
    }
}
