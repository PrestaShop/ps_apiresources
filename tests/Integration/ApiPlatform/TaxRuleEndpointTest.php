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

use Tests\Resources\DatabaseDump;

class TaxRuleEndpointTest extends ApiTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::createApiClient(['tax_rule_read']);
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
    }

    public function testListTaxRules(): array
    {
        $fixtures = $this->createTaxRuleFixtures();

        // Global listing without any filter returns tax rules across all groups
        $allTaxRules = $this->listItems('/tax-rules', ['tax_rule_read']);
        $this->assertGreaterThanOrEqual(3, $allTaxRules['totalItems']);

        // Filtered by group the listing only contains the fixture rules, ordered by ID by default
        $taxRules = $this->listItems('/tax-rules', ['tax_rule_read'], [
            'taxRulesGroupId' => $fixtures['taxRulesGroupId'],
        ]);
        $this->assertEquals(3, $taxRules['totalItems']);
        $this->assertEquals(
            [
                'taxRuleId' => $fixtures['taxRuleIds']['FR'],
                'taxRulesGroupId' => $fixtures['taxRulesGroupId'],
                'countryId' => $fixtures['countryIds']['FR'],
                'countryName' => 'France',
                'stateId' => 0,
                'stateName' => '--',
                'zipcode' => '--',
                'behavior' => 0,
                'rate' => 10.0,
                'taxName' => 'API Test Tax',
                'description' => 'API test rule FR',
            ],
            $taxRules['items'][0]
        );

        // Check the filters details are returned with the API field names
        $this->assertEquals([
            'taxRulesGroupId' => $fixtures['taxRulesGroupId'],
        ], $taxRules['filters']);

        return $fixtures;
    }

    /**
     * @depends testListTaxRules
     */
    public function testFilterTaxRules(array $fixtures): array
    {
        // Filter by behavior inside the fixtures group
        $taxRules = $this->listItems('/tax-rules', ['tax_rule_read'], [
            'taxRulesGroupId' => $fixtures['taxRulesGroupId'],
            'behavior' => 1,
        ]);
        $this->assertEquals(1, $taxRules['totalItems']);
        $this->assertEquals($fixtures['taxRuleIds']['IT'], $taxRules['items'][0]['taxRuleId']);
        $this->assertEquals('Italy', $taxRules['items'][0]['countryName']);

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

        // Filter by tax name matches all the fixture rules (they share the same tax)
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

    public function testListTaxRuleWithoutTax(): void
    {
        // A tax rule with id_tax = 0 has no associated tax: rate and taxName must be null, not a 500 error
        $taxRulesGroup = new \TaxRulesGroup();
        $taxRulesGroup->name = 'API Test No Tax Group';
        $taxRulesGroup->active = true;
        $this->assertNotFalse($taxRulesGroup->add());

        $countryId = (int) \Country::getByIso('FR');
        $taxRule = new \TaxRule();
        $taxRule->id_tax_rules_group = (int) $taxRulesGroup->id;
        $taxRule->id_country = $countryId;
        $taxRule->id_state = 0;
        $taxRule->zipcode_from = '0';
        $taxRule->zipcode_to = '0';
        $taxRule->id_tax = 0;
        $taxRule->behavior = 0;
        $taxRule->description = 'API test rule no tax';
        $this->assertNotFalse($taxRule->add());
        $noTaxRuleId = (int) $taxRule->id;

        $taxRules = $this->listItems('/tax-rules', ['tax_rule_read'], [
            'taxRulesGroupId' => (int) $taxRulesGroup->id,
        ]);

        $this->assertEquals(1, $taxRules['totalItems']);
        $noTaxItem = $taxRules['items'][0];
        $this->assertEquals($noTaxRuleId, $noTaxItem['taxRuleId']);
        $this->assertNull($noTaxItem['rate'] ?? null, 'rate must be null or absent when no tax is associated');
        $this->assertNull($noTaxItem['taxName'] ?? null, 'taxName must be null or absent when no tax is associated');
    }

    /**
     * Creates a dedicated tax rules group containing three tax rules (France, Italy, Germany)
     * sharing the same tax, so the listing assertions rely on deterministic data.
     *
     * @return array{taxRulesGroupId: int, taxId: int, taxRuleIds: array<string, int>, countryIds: array<string, int>}
     */
    private function createTaxRuleFixtures(): array
    {
        $tax = new \Tax();
        $tax->rate = 10.0;
        $tax->active = true;
        foreach (\Language::getIDs(false) as $langId) {
            $tax->name[(int) $langId] = 'API Test Tax';
        }
        $this->assertNotFalse($tax->add());

        $taxRulesGroup = new \TaxRulesGroup();
        $taxRulesGroup->name = 'API Test Tax Rules Group';
        $taxRulesGroup->active = true;
        $this->assertNotFalse($taxRulesGroup->add());

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

            $taxRule = new \TaxRule();
            $taxRule->id_tax_rules_group = (int) $taxRulesGroup->id;
            $taxRule->id_country = $countryId;
            $taxRule->id_state = 0;
            $taxRule->zipcode_from = '0';
            $taxRule->zipcode_to = '0';
            $taxRule->id_tax = (int) $tax->id;
            $taxRule->behavior = $countryData['behavior'];
            $taxRule->description = $countryData['description'];
            $this->assertNotFalse($taxRule->add());
            $taxRuleIds[$isoCode] = (int) $taxRule->id;
        }

        return [
            'taxRulesGroupId' => (int) $taxRulesGroup->id,
            'taxId' => (int) $tax->id,
            'taxRuleIds' => $taxRuleIds,
            'countryIds' => $countryIds,
        ];
    }
}
