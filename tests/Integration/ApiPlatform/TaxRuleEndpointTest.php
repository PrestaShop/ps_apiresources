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

use PrestaShop\PrestaShop\Core\Domain\TaxRulesGroup\TaxRule\Command\AddTaxRuleCommand;
use Symfony\Component\HttpFoundation\Response;
use Tests\Resources\DatabaseDump;

class TaxRuleEndpointTest extends ApiTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::createApiClient(['tax_rule_read', 'tax_rule_write', 'tax_write', 'tax_rules_group_write']);
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        // Reset DB as it was before this test
        DatabaseDump::restoreTables(['tax', 'tax_lang', 'tax_rule', 'tax_rules_group', 'tax_rules_group_shop']);
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'list endpoint' => [
            'GET',
            '/tax-rules',
        ];

        yield 'create endpoint' => [
            'POST',
            '/tax-rules',
        ];

        yield 'delete endpoint' => [
            'DELETE',
            '/tax-rules/1',
        ];
    }

    public function testListTaxRules(): array
    {
        $fixtures = $this->createTaxRuleFixtures();

        // Filtered by group: works the same on every version, so also reveals whether the
        // extended columns (taxRulesGroupId, countryId, stateId, taxName) exist on this Core.
        $taxRules = $this->listItems('/tax-rules', ['tax_rule_read'], [
            'taxRulesGroupId' => $fixtures['taxRulesGroupId'],
        ]);
        $this->assertEquals(3, $taxRules['totalItems']);

        $hasExtendedColumns = $taxRules['items'][0]['taxRulesGroupId'] !== null;

        $this->assertEquals(
            [
                'taxRuleId' => $fixtures['taxRuleIds']['FR'],
                'taxRulesGroupId' => $hasExtendedColumns ? $fixtures['taxRulesGroupId'] : null,
                'countryId' => $hasExtendedColumns ? $fixtures['countryIds']['FR'] : null,
                'countryName' => 'France',
                'stateId' => $hasExtendedColumns ? 0 : null,
                'stateName' => '--',
                'zipcode' => '--',
                'behavior' => 0,
                'rate' => 10.0,
                'taxName' => $hasExtendedColumns ? 'API Test Tax' : null,
                'description' => 'API test rule FR',
            ],
            $taxRules['items'][0]
        );

        // Check the filters details are returned with the API field names
        $this->assertEquals([
            'taxRulesGroupId' => $fixtures['taxRulesGroupId'],
        ], $taxRules['filters']);

        // Unfiltered listing only works once the group filter is optional (9.2+); empty on older Core.
        $allTaxRules = $this->listItems('/tax-rules', ['tax_rule_read']);
        if ($hasExtendedColumns) {
            $this->assertGreaterThanOrEqual(3, $allTaxRules['totalItems']);
        } else {
            $this->assertEquals(0, $allTaxRules['totalItems']);
        }

        $fixtures['hasExtendedColumns'] = $hasExtendedColumns;

        return $fixtures;
    }

    /**
     * @depends testListTaxRules
     */
    public function testFilterTaxRules(array $fixtures): array
    {
        // Filter by behavior: predates the extended columns, works everywhere
        $taxRules = $this->listItems('/tax-rules', ['tax_rule_read'], [
            'taxRulesGroupId' => $fixtures['taxRulesGroupId'],
            'behavior' => 1,
        ]);
        $this->assertEquals(1, $taxRules['totalItems']);
        $this->assertEquals($fixtures['taxRuleIds']['IT'], $taxRules['items'][0]['taxRuleId']);
        $this->assertEquals('Italy', $taxRules['items'][0]['countryName']);

        // These filter keys only exist since the extended columns landed; ignored on older Core
        if ($fixtures['hasExtendedColumns']) {
            // Filter by country name (partial match)
            $taxRules = $this->listItems('/tax-rules', ['tax_rule_read'], [
                'taxRulesGroupId' => $fixtures['taxRulesGroupId'],
                'countryName' => 'Germ',
            ]);
            $this->assertEquals(1, $taxRules['totalItems']);
            $this->assertEquals($fixtures['taxRuleIds']['DE'], $taxRules['items'][0]['taxRuleId']);

            // Filter by country id (exact match)
            $taxRules = $this->listItems('/tax-rules', ['tax_rule_read'], [
                'taxRulesGroupId' => $fixtures['taxRulesGroupId'],
                'countryId' => $fixtures['countryIds']['IT'],
            ]);
            $this->assertEquals(1, $taxRules['totalItems']);
            $this->assertEquals($fixtures['taxRuleIds']['IT'], $taxRules['items'][0]['taxRuleId']);
            $this->assertEquals($fixtures['countryIds']['IT'], $taxRules['items'][0]['countryId']);
        }

        // Matches all fixture rules either way, since they share the same tax
        $taxRules = $this->listItems('/tax-rules', ['tax_rule_read'], [
            'taxRulesGroupId' => $fixtures['taxRulesGroupId'],
            'taxName' => 'API Test Tax',
        ]);
        $this->assertEquals(3, $taxRules['totalItems']);

        return $fixtures;
    }

    /**
     * @depends testFilterTaxRules
     */
    public function testListTaxRulesPagination(array $fixtures): array
    {
        $firstPage = $this->listItems('/tax-rules?limit=2', ['tax_rule_read'], [
            'taxRulesGroupId' => $fixtures['taxRulesGroupId'],
        ]);
        $this->assertEquals(3, $firstPage['totalItems']);
        $this->assertCount(2, $firstPage['items']);
        $this->assertEquals(2, $firstPage['limit']);

        $secondPage = $this->listItems('/tax-rules?limit=2&offset=2', ['tax_rule_read'], [
            'taxRulesGroupId' => $fixtures['taxRulesGroupId'],
        ]);
        $this->assertEquals(3, $secondPage['totalItems']);
        $this->assertCount(1, $secondPage['items']);

        $firstPageIds = array_column($firstPage['items'], 'taxRuleId');
        $secondPageIds = array_column($secondPage['items'], 'taxRuleId');
        $this->assertEmpty(array_intersect($firstPageIds, $secondPageIds));

        return $fixtures;
    }

    public function testListTaxRuleWithoutTax(): void
    {
        // A tax rule with id_tax = 0 has no associated tax: rate and taxName must be null, not a 500 error
        $taxRulesGroup = $this->createItem('/tax-rules-groups', [
            'name' => 'API Test No Tax Group',
            'enabled' => true,
            'shopIds' => [1],
        ], ['tax_rules_group_write']);
        $taxRulesGroupId = $taxRulesGroup['taxRulesGroupId'];

        $countryId = (int) \Country::getByIso('FR');
        $this->assertGreaterThan(0, $countryId);
        $noTaxRuleId = $this->createTaxRuleFixture($taxRulesGroupId, $countryId, 0, 0, 'API test rule no tax');

        $taxRules = $this->listItems('/tax-rules', ['tax_rule_read'], [
            'taxRulesGroupId' => $taxRulesGroupId,
        ]);

        $this->assertEquals(1, $taxRules['totalItems']);
        $noTaxItem = $taxRules['items'][0];
        $this->assertEquals($noTaxRuleId, $noTaxItem['taxRuleId']);
        $this->assertNull($noTaxItem['rate'] ?? null, 'rate must be null or absent when no tax is associated');
        $this->assertNull($noTaxItem['taxName'] ?? null, 'taxName must be null or absent when no tax is associated');
    }

    /**
     * @depends testListTaxRulesPagination
     */
    public function testSortTaxRules(array $fixtures): void
    {
        // Sort by ID in descending order, the last created rule comes first
        $taxRules = $this->listItems('/tax-rules?orderBy=taxRuleId&sortOrder=desc', ['tax_rule_read'], [
            'taxRulesGroupId' => $fixtures['taxRulesGroupId'],
        ]);
        $this->assertEquals('taxRuleId', $taxRules['orderBy']);
        $this->assertEquals($fixtures['taxRuleIds']['DE'], $taxRules['items'][0]['taxRuleId']);

        // Sort by country name in ascending order: France, Germany, Italy
        $taxRules = $this->listItems('/tax-rules?orderBy=countryName&sortOrder=asc', ['tax_rule_read'], [
            'taxRulesGroupId' => $fixtures['taxRulesGroupId'],
        ]);
        $this->assertEquals('countryName', $taxRules['orderBy']);
        $this->assertEquals(
            ['France', 'Germany', 'Italy'],
            array_column($taxRules['items'], 'countryName')
        );
    }

    /**
     * @return array{taxRuleId: int, taxRulesGroupId: int}
     */
    public function testAddTaxRule(): array
    {
        if (!class_exists(AddTaxRuleCommand::class)) {
            $this->markTestSkipped('AddTaxRuleCommand class does not exist, this PrestaShop version does not support tax rule creation via the API yet');
        }

        $taxRulesGroup = $this->createItem('/tax-rules-groups', [
            'name' => 'API Test Add/Delete Tax Rule Group',
            'enabled' => true,
            'shopIds' => [1],
        ], ['tax_rules_group_write']);
        $taxRulesGroupId = $taxRulesGroup['taxRulesGroupId'];

        $tax = $this->createItem('/taxes', [
            'names' => ['en-US' => 'API Test Add Tax', 'fr-FR' => 'API Test Add Tax'],
            'enabled' => true,
            'rate' => 15.0,
        ], ['tax_write']);
        $taxId = $tax['taxId'];

        $countryId = (int) \Country::getByIso('ES');
        $this->assertGreaterThan(0, $countryId);

        $taxRule = $this->createItem('/tax-rules', [
            'taxRulesGroupId' => $taxRulesGroupId,
            'countryId' => $countryId,
            'taxId' => $taxId,
            'behavior' => 1,
            'description' => 'API test add tax rule',
        ], ['tax_rule_write']);
        $this->assertArrayHasKey('taxRuleId', $taxRule);
        $taxRuleId = $taxRule['taxRuleId'];
        $this->assertNotNull($taxRuleId);

        $taxRules = $this->listItems('/tax-rules', ['tax_rule_read'], [
            'taxRulesGroupId' => $taxRulesGroupId,
        ]);
        $this->assertEquals(1, $taxRules['totalItems']);
        $this->assertEquals(
            [
                'taxRuleId' => $taxRuleId,
                'taxRulesGroupId' => $taxRulesGroupId,
                'countryId' => $countryId,
                'countryName' => 'Spain',
                'stateId' => 0,
                'stateName' => '--',
                'zipcode' => '--',
                'behavior' => 1,
                'rate' => 15.0,
                'taxName' => 'API Test Add Tax',
                'description' => 'API test add tax rule',
            ],
            $taxRules['items'][0]
        );

        return ['taxRuleId' => $taxRuleId, 'taxRulesGroupId' => $taxRulesGroupId];
    }

    /**
     * @depends testAddTaxRule
     *
     * @param array{taxRuleId: int, taxRulesGroupId: int} $fixtures
     */
    public function testDeleteTaxRule(array $fixtures): void
    {
        $this->deleteItem('/tax-rules/' . $fixtures['taxRuleId'], ['tax_rule_write']);

        $taxRules = $this->listItems('/tax-rules', ['tax_rule_read'], [
            'taxRulesGroupId' => $fixtures['taxRulesGroupId'],
        ]);
        $this->assertEquals(0, $taxRules['totalItems']);
    }

    public function testInvalidTaxRule(): void
    {
        if (!class_exists(AddTaxRuleCommand::class)) {
            $this->markTestSkipped('AddTaxRuleCommand class does not exist, this PrestaShop version does not support tax rule creation via the API yet');
        }

        // countryId = 0 is Core's "all active countries" fan-out sentinel: rejected here since this
        // endpoint always creates exactly one tax rule per call
        $validationErrorsResponse = $this->createItem('/tax-rules', [
            'taxRulesGroupId' => 1,
            'countryId' => 0,
            'taxId' => 1,
        ], ['tax_rule_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertIsArray($validationErrorsResponse);
        $this->assertValidationErrors([
            [
                'propertyPath' => 'countryId',
                'message' => 'This value should be greater than 0.',
            ],
        ], $validationErrorsResponse);

        // behavior must be 0 (this tax only), 1 (combine) or 2 (one after another)
        $validationErrorsResponse = $this->createItem('/tax-rules', [
            'taxRulesGroupId' => 1,
            'countryId' => 1,
            'taxId' => 1,
            'behavior' => 5,
        ], ['tax_rule_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertIsArray($validationErrorsResponse);
        $this->assertValidationErrors([
            [
                'propertyPath' => 'behavior',
                'message' => 'This value should be between 0 and 2.',
            ],
        ], $validationErrorsResponse);
    }

    /**
     * Creates a dedicated tax rules group containing three tax rules (France, Italy, Germany)
     * sharing the same tax, so the listing assertions rely on deterministic data.
     *
     * @return array{taxRulesGroupId: int, taxId: int, taxRuleIds: array<string, int>, countryIds: array<string, int>}
     */
    private function createTaxRuleFixtures(): array
    {
        $tax = $this->createItem('/taxes', [
            'names' => ['en-US' => 'API Test Tax', 'fr-FR' => 'API Test Tax'],
            'enabled' => true,
            'rate' => 10.0,
        ], ['tax_write']);
        $taxId = $tax['taxId'];

        $taxRulesGroup = $this->createItem('/tax-rules-groups', [
            'name' => 'API Test Tax Rules Group',
            'enabled' => true,
            'shopIds' => [1],
        ], ['tax_rules_group_write']);
        $taxRulesGroupId = $taxRulesGroup['taxRulesGroupId'];

        $taxRuleIds = [];
        $countryIds = [];
        $countriesData = [
            'FR' => ['behavior' => 0, 'description' => 'API test rule FR'],
            'IT' => ['behavior' => 1, 'description' => 'API test rule IT'],
            'DE' => ['behavior' => 2, 'description' => 'API test rule DE'],
        ];
        foreach ($countriesData as $isoCode => $countryData) {
            $countryId = (int) \Country::getByIso($isoCode);
            $this->assertGreaterThan(0, $countryId);
            $countryIds[$isoCode] = $countryId;
            $taxRuleIds[$isoCode] = $this->createTaxRuleFixture($taxRulesGroupId, $countryId, $taxId, $countryData['behavior'], $countryData['description']);
        }

        return [
            'taxRulesGroupId' => $taxRulesGroupId,
            'taxId' => $taxId,
            'taxRuleIds' => $taxRuleIds,
            'countryIds' => $countryIds,
        ];
    }

    /**
     * Creates a single tax rule via the API when it's available (PrestaShop 9.2+), otherwise falls back to the
     * legacy ObjectModel so the list/filter/sort tests still have real data to exercise on older Core versions,
     * where the /tax-rules create endpoint doesn't exist yet.
     */
    private function createTaxRuleFixture(int $taxRulesGroupId, int $countryId, int $taxId, int $behavior, string $description): int
    {
        if (class_exists(AddTaxRuleCommand::class)) {
            $taxRule = $this->createItem('/tax-rules', [
                'taxRulesGroupId' => $taxRulesGroupId,
                'countryId' => $countryId,
                'taxId' => $taxId,
                'behavior' => $behavior,
                'description' => $description,
            ], ['tax_rule_write']);

            return $taxRule['taxRuleId'];
        }

        $taxRule = new \TaxRule();
        $taxRule->id_tax_rules_group = $taxRulesGroupId;
        $taxRule->id_country = $countryId;
        $taxRule->id_state = 0;
        $taxRule->zipcode_from = '0';
        $taxRule->zipcode_to = '0';
        $taxRule->id_tax = $taxId;
        $taxRule->behavior = $behavior;
        $taxRule->description = $description;
        $this->assertNotFalse($taxRule->add());

        return (int) $taxRule->id;
    }
}
