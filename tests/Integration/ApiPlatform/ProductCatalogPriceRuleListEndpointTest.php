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

use Symfony\Component\HttpFoundation\Response;
use Tests\Resources\DatabaseDump;

class ProductCatalogPriceRuleListEndpointTest extends ApiTestCase
{
    private const CATALOG_PRICE_RULE_TABLES = [
        'specific_price_rule',
        'specific_price_rule_condition_group',
        'specific_price_rule_condition',
        'specific_price',
    ];

    // A product of the demo data
    private const PRODUCT_ID = 1;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        DatabaseDump::restoreTables(self::CATALOG_PRICE_RULE_TABLES);
        self::createApiClient(['catalog_price_rule_read', 'catalog_price_rule_write']);
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        DatabaseDump::restoreTables(self::CATALOG_PRICE_RULE_TABLES);
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'list endpoint' => ['GET', '/products/1/catalog-price-rules'];
    }

    public function testListProductCatalogPriceRules(): array
    {
        // Rules without condition apply to every product
        $fixtures = [
            'withDates' => $this->createItem('/catalog-price-rules', [
                'name' => 'API product rule with dates',
                'shopId' => 1,
                'currencyId' => 0,
                'countryId' => 0,
                'groupId' => 0,
                'fromQuantity' => 1,
                'price' => -1,
                'reduction' => ['type' => 'percentage', 'value' => '10'],
                'taxIncluded' => true,
                'dateFrom' => '2026-01-01 00:00:00',
                'dateTo' => '2026-12-31 23:59:59',
            ], ['catalog_price_rule_write'])['catalogPriceRuleId'],
            'withoutDates' => $this->createItem('/catalog-price-rules', [
                'name' => 'API product rule without dates',
                'shopId' => 1,
                'currencyId' => 0,
                'countryId' => 0,
                'groupId' => 0,
                'fromQuantity' => 3,
                'price' => -1,
                'reduction' => ['type' => 'amount', 'value' => '2.5'],
                'taxIncluded' => false,
            ], ['catalog_price_rule_write'])['catalogPriceRuleId'],
        ];

        $response = $this->listItems('/products/' . self::PRODUCT_ID . '/catalog-price-rules', ['catalog_price_rule_read']);
        $expected = [
            'totalItems' => 2,
            // No filters class: no default orderBy, returned as null
            'orderBy' => null,
            'sortOrder' => 'asc',
            'limit' => 10,
            'offset' => 0,
            'filters' => [],
            'items' => [
                $this->expectedWithDates(self::PRODUCT_ID, $fixtures['withDates']),
                $this->expectedWithoutDates(self::PRODUCT_ID, $fixtures['withoutDates']),
            ],
        ];
        if (self::isVersionUnder('9.2.2')) {
            // The first page has a null offset, kept by skip_null_values, PrestaShop 9.2.2 returns 0
            $expected['offset'] = null;
        }
        $this->assertEquals($expected, $response);

        return $fixtures;
    }

    /**
     * @depends testListProductCatalogPriceRules
     */
    public function testListProductCatalogPriceRulesPagination(array $fixtures): void
    {
        $response = $this->listItems('/products/' . self::PRODUCT_ID . '/catalog-price-rules?limit=1&offset=1', ['catalog_price_rule_read']);
        $this->assertEquals([
            'totalItems' => 2,
            'orderBy' => null,
            'sortOrder' => 'asc',
            'limit' => 1,
            'offset' => 1,
            'filters' => [],
            'items' => [
                $this->expectedWithoutDates(self::PRODUCT_ID, $fixtures['withoutDates']),
            ],
        ], $response);
    }

    /**
     * @depends testListProductCatalogPriceRules
     */
    public function testListUnknownProductCatalogPriceRules(array $fixtures): void
    {
        // The product is not checked: the rules without condition apply to any product id
        $response = $this->listItems('/products/999999/catalog-price-rules', ['catalog_price_rule_read']);
        $this->assertEquals(2, $response['totalItems']);
        $this->assertEquals(
            [
                $this->expectedWithDates(999999, $fixtures['withDates']),
                $this->expectedWithoutDates(999999, $fixtures['withoutDates']),
            ],
            $response['items']
        );
    }

    public function testInvalidProductId(): void
    {
        // The id passes the \d+ requirement and is refused by the value object: a mapped exception, no field list
        $this->getItem('/products/0/catalog-price-rules', ['catalog_price_rule_read'], Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /**
     * @return array<string, mixed>
     */
    private function expectedWithDates(int $productId, int $catalogPriceRuleId): array
    {
        return [
            'productId' => $productId,
            'catalogPriceRuleId' => $catalogPriceRuleId,
            'name' => 'API product rule with dates',
            'shopName' => (new \Shop(1))->name,
            // currencyId, countryId and groupId 0: every currency, country and group
            'currencyName' => null,
            'currencyIso' => null,
            'countryName' => null,
            'groupName' => null,
            'fromQuantity' => 1,
            'reductionType' => 'percentage',
            'reductionValue' => 10.0,
            'taxIncluded' => true,
            'dateFrom' => '2026-01-01 00:00:00',
            'dateTo' => '2026-12-31 23:59:59',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function expectedWithoutDates(int $productId, int $catalogPriceRuleId): array
    {
        // The zero date of the database is returned as null from PrestaShop 9.2.0
        $emptyDate = self::isVersionAtLeast('9.2.0') ? null : '-0001-11-30 00:00:00';

        return [
            'productId' => $productId,
            'catalogPriceRuleId' => $catalogPriceRuleId,
            'name' => 'API product rule without dates',
            'shopName' => (new \Shop(1))->name,
            'currencyName' => null,
            'currencyIso' => null,
            'countryName' => null,
            'groupName' => null,
            'fromQuantity' => 3,
            'reductionType' => 'amount',
            'reductionValue' => 2.5,
            'taxIncluded' => false,
            'dateFrom' => $emptyDate,
            'dateTo' => $emptyDate,
        ];
    }
}
