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
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::resetTables();
        self::createApiClient(['catalog_price_rule_read', 'catalog_price_rule_write']);
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        self::resetTables();
    }

    protected static function resetTables(): void
    {
        DatabaseDump::restoreTables([
            'specific_price_rule',
            'specific_price_rule_condition_group',
            'specific_price_rule_condition',
            'specific_price',
        ]);
    }

    private function getCreateData(): array
    {
        return [
            'name' => 'Test catalog price rule',
            // 0 means "all" for currency / country / group
            'currencyId' => 0,
            'countryId' => 0,
            'groupId' => 0,
            'fromQuantity' => 1,
            'shopId' => 1,
            'includeTax' => true,
            // price command arg is a float -> send a number, not a string (Symfony rejects string->float)
            'price' => -1,
            'reductionType' => 'amount',
            'reductionValue' => '10',
        ];
    }

    /**
     * @param string[] $scopes
     *
     * @return int[]
     */
    private function listedIds(array $scopes): array
    {
        return array_column(
            $this->listItems('/catalog-price-rules?orderBy=catalogPriceRuleId&sortOrder=desc', $scopes)['items'],
            'catalogPriceRuleId'
        );
    }

    private function createRule(string $name): int
    {
        $data = $this->getCreateData();
        $data['name'] = $name;
        $created = $this->createItem('/catalog-price-rules', $data, ['catalog_price_rule_write']);

        return $created['catalogPriceRuleId'];
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'create endpoint' => ['POST', '/catalog-price-rules'];
        yield 'delete endpoint' => ['DELETE', '/catalog-price-rules/1'];
        yield 'list endpoint' => ['GET', '/catalog-price-rules'];
    }

    public function testAddCatalogPriceRule(): int
    {
        $rule = $this->createItem('/catalog-price-rules', $this->getCreateData(), ['catalog_price_rule_write']);
        // A create without a query read-back returns only the generated identifier;
        // the persisted values are asserted through the listing below.
        $this->assertArrayHasKey('catalogPriceRuleId', $rule);

        return $rule['catalogPriceRuleId'];
    }

    /**
     * @depends testAddCatalogPriceRule
     */
    public function testListCatalogPriceRules(int $catalogPriceRuleId): void
    {
        $paginated = $this->listItems('/catalog-price-rules?orderBy=catalogPriceRuleId&sortOrder=desc', ['catalog_price_rule_read']);
        $this->assertGreaterThanOrEqual(1, $paginated['totalItems']);
        $this->assertEquals('catalogPriceRuleId', $paginated['orderBy']);

        $first = $paginated['items'][0];
        $this->assertEquals($catalogPriceRuleId, $first['catalogPriceRuleId']);
        $this->assertSame('Test catalog price rule', $first['name']);
    }

    public function testDeleteCatalogPriceRule(): void
    {
        $catalogPriceRuleId = $this->createRule('Rule to delete');

        $return = $this->deleteItem('/catalog-price-rules/' . $catalogPriceRuleId, ['catalog_price_rule_write']);
        // This endpoint returns an empty response and a 204 HTTP code
        $this->assertNull($return);

        $this->assertNotContains($catalogPriceRuleId, $this->listedIds(['catalog_price_rule_read']));
    }

    public function testBulkDeleteCatalogPriceRules(): void
    {
        $bulkIds = [$this->createRule('Bulk rule A'), $this->createRule('Bulk rule B')];

        $this->bulkDeleteItems('/catalog-price-rules/bulk-delete', [
            'catalogPriceRuleIds' => $bulkIds,
        ], ['catalog_price_rule_write']);

        $listed = $this->listedIds(['catalog_price_rule_read']);
        foreach ($bulkIds as $catalogPriceRuleId) {
            $this->assertNotContains($catalogPriceRuleId, $listed);
        }
    }

    public function testInvalidCatalogPriceRule(): void
    {
        $invalidData = $this->getCreateData();
        $invalidData['name'] = '';

        $validationErrorsResponse = $this->createItem('/catalog-price-rules', $invalidData, ['catalog_price_rule_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertIsArray($validationErrorsResponse);

        $this->assertValidationErrors([
            [
                'propertyPath' => 'name',
                'message' => 'This value should not be blank.',
            ],
        ], $validationErrorsResponse);
    }
}
