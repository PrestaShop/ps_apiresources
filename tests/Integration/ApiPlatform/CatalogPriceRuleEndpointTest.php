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

class CatalogPriceRuleEndpointTest extends ApiTestCase
{
    private const CATALOG_PRICE_RULE_TABLES = [
        'specific_price_rule',
        'specific_price_rule_condition_group',
        'specific_price_rule_condition',
        'specific_price',
    ];

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
        yield 'create endpoint' => ['POST', '/catalog-price-rules'];
        yield 'get endpoint' => ['GET', '/catalog-price-rules/1'];
        yield 'update endpoint' => ['PATCH', '/catalog-price-rules/1'];
        yield 'delete endpoint' => ['DELETE', '/catalog-price-rules/1'];
        yield 'list endpoint' => ['GET', '/catalog-price-rules'];
        yield 'bulk delete endpoint' => ['DELETE', '/catalog-price-rules/bulk-delete'];
    }

    public function testAddCatalogPriceRule(): int
    {
        $catalogPriceRule = $this->createItem('/catalog-price-rules', $this->getCreatePayload(), ['catalog_price_rule_write']);
        $this->assertEquals($this->expectedCatalogPriceRule($catalogPriceRule['catalogPriceRuleId']), $catalogPriceRule);

        return $catalogPriceRule['catalogPriceRuleId'];
    }

    /**
     * @depends testAddCatalogPriceRule
     */
    public function testGetCatalogPriceRule(int $catalogPriceRuleId): int
    {
        $this->assertEquals(
            $this->expectedCatalogPriceRule($catalogPriceRuleId),
            $this->getItem('/catalog-price-rules/' . $catalogPriceRuleId, ['catalog_price_rule_read'])
        );

        return $catalogPriceRuleId;
    }

    /**
     * @depends testGetCatalogPriceRule
     */
    public function testEditCatalogPriceRule(int $catalogPriceRuleId): int
    {
        $patchData = [
            'name' => 'API catalog price rule updated',
            'fromQuantity' => 2,
            'price' => 25.5,
            // The update accepts the value as a number, the reduction is sent whole
            'reduction' => ['type' => 'amount', 'value' => 15],
            'taxIncluded' => false,
        ];
        $expected = $this->expectedCatalogPriceRule($catalogPriceRuleId, $patchData);

        $updated = $this->partialUpdateItem('/catalog-price-rules/' . $catalogPriceRuleId, $patchData, ['catalog_price_rule_write']);
        $this->assertEquals($expected, $updated);
        // The persisted state matches the PATCH response
        $this->assertEquals($expected, $this->getItem('/catalog-price-rules/' . $catalogPriceRuleId, ['catalog_price_rule_read']));

        // A partial update with one field leaves the others untouched
        $updated = $this->partialUpdateItem('/catalog-price-rules/' . $catalogPriceRuleId, ['groupId' => 1], ['catalog_price_rule_write']);
        $this->assertEquals($this->expectedCatalogPriceRule($catalogPriceRuleId, $patchData + ['groupId' => 1]), $updated);

        return $catalogPriceRuleId;
    }

    /**
     * @depends testEditCatalogPriceRule
     */
    public function testListCatalogPriceRules(int $catalogPriceRuleId): int
    {
        // The whole envelope is asserted on a list the test fully controls: a filter on its own fixture
        $filters = ['name' => 'API catalog price rule updated'];
        $filtered = $this->listItems('/catalog-price-rules?orderBy=catalogPriceRuleId&sortOrder=asc', ['catalog_price_rule_read'], $filters);
        $expected = [
            'totalItems' => 1,
            'orderBy' => 'catalogPriceRuleId',
            'sortOrder' => 'asc',
            'limit' => 50,
            'offset' => 0,
            'filters' => $filters,
            'items' => [
                [
                    'catalogPriceRuleId' => $catalogPriceRuleId,
                    'name' => 'API catalog price rule updated',
                    'shopName' => $this->getShopName(1),
                    // currencyId and countryId 0: every currency and country
                    'currencyName' => null,
                    'countryName' => null,
                    // The name of the group 1 in the default language
                    'groupName' => $this->getGroupName(1),
                    'fromQuantity' => 2,
                    'reductionType' => 'amount',
                    'reductionValue' => 15.0,
                    'dateFrom' => '2026-01-01 00:00:00',
                    'dateTo' => '2026-12-31 23:59:59',
                ],
            ],
        ];
        if (self::isVersionUnder('9.2.2')) {
            // The first page has a null offset, kept by skip_null_values, PrestaShop 9.2.2 returns 0
            $expected['offset'] = null;
        }
        $this->assertEquals($expected, $filtered);

        // The sort and the pagination, with a second fixture: the newest rule has the highest id
        $second = $this->createItem('/catalog-price-rules', $this->getCreatePayload(['name' => 'API second rule']), ['catalog_price_rule_write']);
        $sorted = $this->listItems('/catalog-price-rules?orderBy=catalogPriceRuleId&sortOrder=desc&limit=2', ['catalog_price_rule_read']);
        $this->assertEquals(2, $sorted['limit']);
        $this->assertSame([$second['catalogPriceRuleId'], $catalogPriceRuleId], array_column($sorted['items'], 'catalogPriceRuleId'));
        $paged = $this->listItems('/catalog-price-rules?orderBy=catalogPriceRuleId&sortOrder=desc&limit=1&offset=1', ['catalog_price_rule_read']);
        $this->assertSame([$catalogPriceRuleId], array_column($paged['items'], 'catalogPriceRuleId'));
        $this->deleteItem('/catalog-price-rules/' . $second['catalogPriceRuleId'], ['catalog_price_rule_write']);

        return $catalogPriceRuleId;
    }

    /**
     * @depends testListCatalogPriceRules
     */
    public function testDeleteCatalogPriceRule(int $catalogPriceRuleId): void
    {
        // The endpoint returns an empty response and a 204 HTTP code
        $this->assertNull($this->deleteItem('/catalog-price-rules/' . $catalogPriceRuleId, ['catalog_price_rule_write']));
        $this->getItem('/catalog-price-rules/' . $catalogPriceRuleId, ['catalog_price_rule_read'], Response::HTTP_NOT_FOUND);
        $this->assertEquals(0, $this->countItems('/catalog-price-rules', ['catalog_price_rule_read'], ['name' => 'API catalog price rule updated']));
    }

    public function testAddCatalogPriceRuleWithoutDates(): void
    {
        $payload = $this->getCreatePayload(['name' => 'API rule without dates']);
        unset($payload['dateFrom'], $payload['dateTo']);

        $catalogPriceRule = $this->createItem('/catalog-price-rules', $payload, ['catalog_price_rule_write']);
        $expected = $this->expectedCatalogPriceRule($catalogPriceRule['catalogPriceRuleId'], ['name' => 'API rule without dates', 'dateFrom' => null, 'dateTo' => null]);
        $this->assertEquals($expected, $catalogPriceRule);
        $this->assertEquals($expected, $this->getItem('/catalog-price-rules/' . $catalogPriceRule['catalogPriceRuleId'], ['catalog_price_rule_read']));

        // The list returns the zero date of the database as null from PrestaShop 9.2.0
        $item = $this->listItems('/catalog-price-rules', ['catalog_price_rule_read'], ['name' => 'API rule without dates'])['items'][0];
        $this->assertEquals($catalogPriceRule['catalogPriceRuleId'], $item['catalogPriceRuleId']);
        $emptyDate = self::isVersionAtLeast('9.2.0') ? null : '-0001-11-30 00:00:00';
        $this->assertSame([$emptyDate, $emptyDate], [$item['dateFrom'], $item['dateTo']]);

        $this->deleteItem('/catalog-price-rules/' . $catalogPriceRule['catalogPriceRuleId'], ['catalog_price_rule_write']);
    }

    public function testNotFoundCatalogPriceRule(): void
    {
        $this->getItem('/catalog-price-rules/999999', ['catalog_price_rule_read'], Response::HTTP_NOT_FOUND);
        // EditCatalogPriceRuleHandler does not check that the rule exists: it validates an empty legacy object and
        // answers 422 (invalid field values) instead of 404, a core fix to come
        $this->partialUpdateItem('/catalog-price-rules/999999', ['name' => 'Unknown rule'], ['catalog_price_rule_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->deleteItem('/catalog-price-rules/999999', ['catalog_price_rule_write'], Response::HTTP_NOT_FOUND);
    }

    public function testInvalidCatalogPriceRuleId(): void
    {
        // The id passes the \d+ requirement and is refused by the value object: a mapped exception, no field list
        $this->getItem('/catalog-price-rules/0', ['catalog_price_rule_read'], Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->partialUpdateItem('/catalog-price-rules/0', ['name' => 'Invalid rule'], ['catalog_price_rule_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->deleteItem('/catalog-price-rules/0', ['catalog_price_rule_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testAddCatalogPriceRuleWithInvalidData(): void
    {
        $response = $this->createItem('/catalog-price-rules', ['name' => ''], ['catalog_price_rule_write'], Response::HTTP_UNPROCESSABLE_ENTITY);

        $notNull = ['message' => 'This value should not be null.'];
        $this->assertValidationErrors([
            ['propertyPath' => 'name', 'message' => 'This value should not be blank.'],
            ['propertyPath' => 'shopId'] + $notNull,
            ['propertyPath' => 'currencyId'] + $notNull,
            ['propertyPath' => 'countryId'] + $notNull,
            ['propertyPath' => 'groupId'] + $notNull,
            ['propertyPath' => 'fromQuantity'] + $notNull,
            ['propertyPath' => 'price'] + $notNull,
            ['propertyPath' => 'reduction'] + $notNull,
            ['propertyPath' => 'taxIncluded'] + $notNull,
        ], $response);
    }

    public function testAddCatalogPriceRuleWithInvalidValues(): void
    {
        $response = $this->createItem('/catalog-price-rules', $this->getCreatePayload([
            'name' => str_repeat('a', 256),
            'shopId' => 0,
            'currencyId' => -1,
            'countryId' => -1,
            'groupId' => -1,
            'fromQuantity' => -1,
            'reduction' => ['type' => 'free'],
        ]), ['catalog_price_rule_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertValidationErrors([
            ['propertyPath' => 'name', 'message' => 'This value is too long. It should have 255 characters or less.'],
            ['propertyPath' => 'shopId', 'message' => 'This value should be positive.'],
            ['propertyPath' => 'currencyId', 'message' => 'This value should be either positive or zero.'],
            ['propertyPath' => 'countryId', 'message' => 'This value should be either positive or zero.'],
            ['propertyPath' => 'groupId', 'message' => 'This value should be either positive or zero.'],
            ['propertyPath' => 'fromQuantity', 'message' => 'This value should be either positive or zero.'],
            ['propertyPath' => 'reduction.type', 'message' => 'The value you selected is not a valid choice.'],
            ['propertyPath' => 'reduction.value'] + ['message' => 'This value should not be null.'],
        ], $response);

        // Refused by the CleanHtml constraint: the message carries the value, only the field is asserted
        $response = $this->createItem('/catalog-price-rules', $this->getCreatePayload(['name' => '<script>alert(1)</script>']), ['catalog_price_rule_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertValidationErrors([['propertyPath' => 'name']], $response);
    }

    public function testInvalidReductionValue(): void
    {
        // Refused by the Reduction value object: a mapped exception, no field list
        foreach ([['type' => 'percentage', 'value' => '150'], ['type' => 'amount', 'value' => '-5']] as $reduction) {
            $this->createItem('/catalog-price-rules', $this->getCreatePayload(['reduction' => $reduction]), ['catalog_price_rule_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $catalogPriceRuleId = $this->createItem('/catalog-price-rules', $this->getCreatePayload(['name' => 'API rule reduction']), ['catalog_price_rule_write'])['catalogPriceRuleId'];
        $this->partialUpdateItem('/catalog-price-rules/' . $catalogPriceRuleId, ['reduction' => ['type' => 'percentage', 'value' => 101]], ['catalog_price_rule_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
        // The reduction is sent whole: a value without type is refused by the constraints
        $response = $this->partialUpdateItem('/catalog-price-rules/' . $catalogPriceRuleId, ['reduction' => ['value' => 5]], ['catalog_price_rule_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertValidationErrors([
            ['propertyPath' => 'reduction.type', 'message' => 'This value should not be blank.'],
        ], $response);
        // Nothing changed
        $this->assertEquals(
            $this->expectedCatalogPriceRule($catalogPriceRuleId, ['name' => 'API rule reduction']),
            $this->getItem('/catalog-price-rules/' . $catalogPriceRuleId, ['catalog_price_rule_read'])
        );

        $this->deleteItem('/catalog-price-rules/' . $catalogPriceRuleId, ['catalog_price_rule_write']);
    }

    public function testDateRange(): void
    {
        // An inverse range is refused on creation by every version
        $this->createItem('/catalog-price-rules', $this->getCreatePayload([
            'dateFrom' => '2026-12-31 00:00:00',
            'dateTo' => '2026-01-01 00:00:00',
        ]), ['catalog_price_rule_write'], Response::HTTP_UNPROCESSABLE_ENTITY);

        $catalogPriceRuleId = $this->createItem('/catalog-price-rules', $this->getCreatePayload(['name' => 'API rule dates']), ['catalog_price_rule_write'])['catalogPriceRuleId'];
        $expected = $this->expectedCatalogPriceRule($catalogPriceRuleId, ['name' => 'API rule dates']);

        // From PrestaShop 9.2.2 the update checks the resulting range: a valid range moved after the stored end date
        // is accepted and an end date before the stored start date is refused. Older versions compare a new start
        // date with the stored end date only.
        $movedRange = ['dateFrom' => '2027-01-01 00:00:00', 'dateTo' => '2027-12-31 00:00:00'];
        $inverseEnd = ['dateTo' => '2025-06-01 00:00:00'];
        if (self::isVersionAtLeast('9.2.2')) {
            $expected = array_replace($expected, $movedRange);
            $this->assertEquals($expected, $this->partialUpdateItem('/catalog-price-rules/' . $catalogPriceRuleId, $movedRange, ['catalog_price_rule_write']));
            $this->partialUpdateItem('/catalog-price-rules/' . $catalogPriceRuleId, $inverseEnd, ['catalog_price_rule_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
        } else {
            $this->partialUpdateItem('/catalog-price-rules/' . $catalogPriceRuleId, $movedRange, ['catalog_price_rule_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
            $expected = array_replace($expected, $inverseEnd);
            $this->assertEquals($expected, $this->partialUpdateItem('/catalog-price-rules/' . $catalogPriceRuleId, $inverseEnd, ['catalog_price_rule_write']));
        }
        $this->assertEquals($expected, $this->getItem('/catalog-price-rules/' . $catalogPriceRuleId, ['catalog_price_rule_read']));

        $this->deleteItem('/catalog-price-rules/' . $catalogPriceRuleId, ['catalog_price_rule_write']);
    }

    public function testBulkDeleteCatalogPriceRules(): void
    {
        $first = $this->createItem('/catalog-price-rules', $this->getCreatePayload(['name' => 'API bulk rule 1']), ['catalog_price_rule_write'])['catalogPriceRuleId'];
        $second = $this->createItem('/catalog-price-rules', $this->getCreatePayload(['name' => 'API bulk rule 2']), ['catalog_price_rule_write'])['catalogPriceRuleId'];
        $third = $this->createItem('/catalog-price-rules', $this->getCreatePayload(['name' => 'API bulk rule 3']), ['catalog_price_rule_write'])['catalogPriceRuleId'];

        $this->assertNull($this->bulkDeleteItems('/catalog-price-rules/bulk-delete', ['catalogPriceRuleIds' => [$first]], ['catalog_price_rule_write']));
        $this->getItem('/catalog-price-rules/' . $first, ['catalog_price_rule_read'], Response::HTTP_NOT_FOUND);

        // The core bulk command stops at the first unknown rule: the rules before it are deleted, the others kept
        $this->bulkDeleteItems('/catalog-price-rules/bulk-delete', ['catalogPriceRuleIds' => [$second, 999999, $third]], ['catalog_price_rule_write'], Response::HTTP_NOT_FOUND);
        $this->getItem('/catalog-price-rules/' . $second, ['catalog_price_rule_read'], Response::HTTP_NOT_FOUND);
        $this->assertEquals(
            $this->expectedCatalogPriceRule($third, ['name' => 'API bulk rule 3']),
            $this->getItem('/catalog-price-rules/' . $third, ['catalog_price_rule_read'])
        );
        $this->assertNull($this->bulkDeleteItems('/catalog-price-rules/bulk-delete', ['catalogPriceRuleIds' => [$third]], ['catalog_price_rule_write']));
        $this->getItem('/catalog-price-rules/' . $third, ['catalog_price_rule_read'], Response::HTTP_NOT_FOUND);

        // The list is validated before anything runs
        $response = $this->bulkDeleteItems('/catalog-price-rules/bulk-delete', ['catalogPriceRuleIds' => []], ['catalog_price_rule_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertValidationErrors([
            ['propertyPath' => 'catalogPriceRuleIds', 'message' => 'This value should not be blank.'],
        ], $response);
        $response = $this->bulkDeleteItems('/catalog-price-rules/bulk-delete', ['catalogPriceRuleIds' => ['3abc', 0]], ['catalog_price_rule_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertValidationErrors([
            ['propertyPath' => 'catalogPriceRuleIds[0]', 'message' => 'This value should be of type integer.'],
            ['propertyPath' => 'catalogPriceRuleIds[1]', 'message' => 'This value should be positive.'],
        ], $response);
    }

    /**
     * The payload of the fixture, the expected payloads are built from it. The reduction value is a string: the
     * creation command does not accept a number.
     *
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    private function getCreatePayload(array $overrides = []): array
    {
        return array_replace([
            'name' => 'API catalog price rule',
            'shopId' => 1,
            'currencyId' => 0,
            'countryId' => 0,
            'groupId' => 0,
            'fromQuantity' => 1,
            'price' => -1,
            'reduction' => [
                'type' => 'percentage',
                'value' => '10',
            ],
            'taxIncluded' => true,
            'dateFrom' => '2026-01-01 00:00:00',
            'dateTo' => '2026-12-31 23:59:59',
        ], $overrides);
    }

    /**
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    private function expectedCatalogPriceRule(int $catalogPriceRuleId, array $overrides = []): array
    {
        $expected = array_replace(['catalogPriceRuleId' => $catalogPriceRuleId] + $this->getCreatePayload(), $overrides);
        // The decimals are returned as numbers
        $expected['price'] = (float) $expected['price'];
        $expected['reduction']['value'] = (float) $expected['reduction']['value'];

        return $expected;
    }

    private function getShopName(int $shopId): string
    {
        return (new \Shop($shopId))->name;
    }

    private function getGroupName(int $groupId): string
    {
        return (new \Group($groupId, (int) \Configuration::get('PS_LANG_DEFAULT')))->name;
    }
}
