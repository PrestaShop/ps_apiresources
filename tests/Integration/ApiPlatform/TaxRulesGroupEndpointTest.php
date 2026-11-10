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

class TaxRulesGroupEndpointTest extends ApiTestCase
{
    private const TAX_RULE_FIXTURES = [
        'FR' => ['countryName' => 'France', 'behavior' => 0, 'zipCode' => '75000-75999', 'expectedZipcode' => '75000 - 75999'],
        'IT' => ['countryName' => 'Italy', 'behavior' => 1, 'stateIsoCode' => 'AG', 'stateName' => 'Agrigento'],
        'DE' => ['countryName' => 'Germany', 'behavior' => 2],
    ];

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        $scopes = ['tax_rules_group_write', 'tax_rules_group_read', 'tax_write'];
        // The tax rule endpoints, used to create the fixtures of the tax rule list tests, only exist since 9.2.0
        if (self::isVersionAtLeast('9.2.0')) {
            $scopes[] = 'tax_rule_write';
        }
        self::createApiClient($scopes);
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        // Reset DB as it was before this test
        DatabaseDump::restoreTables(['tax', 'tax_lang', 'tax_rules_group', 'tax_rules_group_shop', 'tax_rule']);
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'create endpoint' => [
            'POST',
            '/tax-rules-groups',
        ];

        yield 'get endpoint' => [
            'GET',
            '/tax-rules-groups/1',
        ];

        yield 'update endpoint' => [
            'PATCH',
            '/tax-rules-groups/1',
        ];

        yield 'delete endpoint' => [
            'DELETE',
            '/tax-rules-groups/1',
        ];

        yield 'list endpoint' => [
            'GET',
            '/tax-rules-groups',
        ];

        yield 'bulk delete endpoint' => [
            'DELETE',
            '/tax-rules-groups/bulk-delete',
        ];

        yield 'bulk toggle status endpoint' => [
            'PUT',
            '/tax-rules-groups/bulk-update-status',
        ];

        yield 'toggle status endpoint' => [
            'PATCH',
            '/tax-rules-groups/1/set-status',
        ];

        // minVersion 9.2.0: an older core answers 404, not 401
        if (self::isVersionAtLeast('9.2.0')) {
            yield 'list tax rules endpoint' => [
                'GET',
                '/tax-rules-groups/1/tax-rules',
            ];
        }
    }

    public function testAddTaxRulesGroup(): int
    {
        $itemsCount = $this->countItems('/tax-rules-groups', ['tax_rules_group_read']);

        $taxRulesGroup = $this->createItem('/tax-rules-groups', [
            'name' => 'My Tax Rules Group',
            'enabled' => false,
            'shopIds' => [1],
        ], ['tax_rules_group_write']);
        $this->assertArrayHasKey('taxRulesGroupId', $taxRulesGroup);
        $taxRulesGroupId = $taxRulesGroup['taxRulesGroupId'];
        $this->assertEquals(
            [
                'taxRulesGroupId' => $taxRulesGroupId,
            ],
            $taxRulesGroup
        );

        $newItemsCount = $this->countItems('/tax-rules-groups', ['tax_rules_group_read']);
        $this->assertEquals($itemsCount + 1, $newItemsCount);

        return $taxRulesGroupId;
    }

    /**
     * @depends testAddTaxRulesGroup
     *
     * @param int $taxRulesGroupId
     *
     * @return int
     */
    public function testGetTaxRulesGroup(int $taxRulesGroupId): int
    {
        $taxRulesGroup = $this->getItem('/tax-rules-groups/' . $taxRulesGroupId, ['tax_rules_group_read']);
        $this->assertEquals(
            [
                'taxRulesGroupId' => $taxRulesGroupId,
                'name' => 'My Tax Rules Group',
                'enabled' => false,
                'shopIds' => [1],
            ],
            $taxRulesGroup
        );

        return $taxRulesGroupId;
    }

    /**
     * @depends testGetTaxRulesGroup
     *
     * @param int $taxRulesGroupId
     *
     * @return int
     */
    public function testUpdateTaxRulesGroup(int $taxRulesGroupId): int
    {
        $updatedTaxRulesGroup = $this->partialUpdateItem('/tax-rules-groups/' . $taxRulesGroupId, [
            'name' => 'My Tax Rules Group updated',
        ], ['tax_rules_group_write']);
        $this->assertEquals(
            [
                'taxRulesGroupId' => $taxRulesGroupId,
                'name' => 'My Tax Rules Group updated',
                'enabled' => false,
                'shopIds' => [1],
            ],
            $updatedTaxRulesGroup
        );

        $updatedTaxRulesGroup = $this->partialUpdateItem('/tax-rules-groups/' . $taxRulesGroupId, [
            'enabled' => true,
        ], ['tax_rules_group_write']);
        $this->assertEquals(
            [
                'taxRulesGroupId' => $taxRulesGroupId,
                'name' => 'My Tax Rules Group updated',
                'enabled' => true,
                'shopIds' => [1],
            ],
            $updatedTaxRulesGroup
        );

        return $taxRulesGroupId;
    }

    /**
     * @depends testUpdateTaxRulesGroup
     *
     * @param int $taxRulesGroupId
     *
     * @return int
     */
    public function testGetUpdatedTaxRulesGroup(int $taxRulesGroupId): int
    {
        $taxRulesGroup = $this->getItem('/tax-rules-groups/' . $taxRulesGroupId, ['tax_rules_group_read']);
        $this->assertEquals(
            [
                'taxRulesGroupId' => $taxRulesGroupId,
                'name' => 'My Tax Rules Group updated',
                'enabled' => true,
                'shopIds' => [1],
            ],
            $taxRulesGroup
        );

        return $taxRulesGroupId;
    }

    /**
     * @depends testGetTaxRulesGroup
     *
     * @param int $taxRulesGroupId
     *
     * @return int
     */
    public function testToggleTaxRulesGroupStatus(int $taxRulesGroupId): void
    {
        $response = $this->partialUpdateItem(
            '/tax-rules-groups/' . $taxRulesGroupId . '/set-status',
            ['enabled' => false],
            ['tax_rules_group_write'],
            Response::HTTP_NO_CONTENT
        );
        $this->assertNull($response);

        $taxRulesGroup = $this->getItem('/tax-rules-groups/' . $taxRulesGroupId, ['tax_rules_group_read']);
        $this->assertEquals(
            [
                'taxRulesGroupId' => $taxRulesGroupId,
                'name' => 'My Tax Rules Group updated',
                'enabled' => false,
                'shopIds' => [1],
            ],
            $taxRulesGroup
        );

        $response = $this->partialUpdateItem(
            '/tax-rules-groups/' . $taxRulesGroupId . '/set-status',
            ['enabled' => true],
            ['tax_rules_group_write'],
            Response::HTTP_NO_CONTENT
        );
        $this->assertNull($response);

        $taxRulesGroup = $this->getItem('/tax-rules-groups/' . $taxRulesGroupId, ['tax_rules_group_read']);
        $this->assertEquals(
            [
                'taxRulesGroupId' => $taxRulesGroupId,
                'name' => 'My Tax Rules Group updated',
                'enabled' => true,
                'shopIds' => [1],
            ],
            $taxRulesGroup
        );
    }

    /**
     * @depends testGetUpdatedTaxRulesGroup
     *
     * @param int $taxRulesGroupId
     *
     * @return int
     */
    public function testListTaxRulesGroups(int $taxRulesGroupId): int
    {
        $taxRulesGroups = $this->listItems('/tax-rules-groups', ['tax_rules_group_read']);
        $this->assertCount(50, $taxRulesGroups['items']);
        $this->assertEquals(53, $taxRulesGroups['totalItems']);

        $taxRulesGroups = $this->listItems('/tax-rules-groups?limit=100', ['tax_rules_group_read']);
        $this->assertGreaterThanOrEqual(1, $taxRulesGroups['totalItems']);

        // Search for the one created previously during the tests and assert its data in the list
        $testTaxRulesGroup = null;
        foreach ($taxRulesGroups['items'] as $taxRulesGroup) {
            if ($taxRulesGroup['taxRulesGroupId'] === $taxRulesGroupId) {
                $testTaxRulesGroup = $taxRulesGroup;
            }
        }
        $this->assertNotNull($testTaxRulesGroup);
        $this->assertEquals(
            [
                'taxRulesGroupId' => $taxRulesGroupId,
                'name' => 'My Tax Rules Group updated',
                'enabled' => true,
            ],
            $testTaxRulesGroup
        );

        return $taxRulesGroupId;
    }

    /**
     * @depends testListTaxRulesGroups
     *
     * @param int $taxRulesGroupId
     *
     * @return int
     */
    public function testDeleteTaxRulesGroup(int $taxRulesGroupId): void
    {
        $return = $this->deleteItem('/tax-rules-groups/' . $taxRulesGroupId, ['tax_rules_group_write']);
        // This endpoint return empty response and 204 HTTP code
        $this->assertNull($return);

        // Getting the item should result in a 404 now
        $this->getItem('/tax-rules-groups/' . $taxRulesGroupId, ['tax_rules_group_read'], Response::HTTP_NOT_FOUND);
    }

    /**
     * @depends testDeleteTaxRulesGroup
     *
     * @param int $taxRulesGroupId
     *
     * @return int
     */
    public function testBulkDeleteSetStatus(): void
    {
        $taxRulesGroups = $this->listItems('/tax-rules-groups', ['tax_rules_group_read']);

        // There are taxRulesGroups in default fixtures
        $this->assertEquals(52, $taxRulesGroups['totalItems']);

        // We update the two taxRulesGroups
        $bulkTaxRulesGroups = [
            $taxRulesGroups['items'][2]['taxRulesGroupId'],
            $taxRulesGroups['items'][3]['taxRulesGroupId'],
        ];
        foreach ($bulkTaxRulesGroups as $taxRulesGroupId) {
            $taxRulesGroup = $this->getItem('/tax-rules-groups/' . $taxRulesGroupId, ['tax_rules_group_read']);

            $this->assertEquals(true, $taxRulesGroup['enabled']);
        }

        $this->updateItem('/tax-rules-groups/bulk-update-status', [
            'taxRulesGroupIds' => $bulkTaxRulesGroups,
            'enabled' => false,
        ], ['tax_rules_group_write'], Response::HTTP_NO_CONTENT);

        // Assert the provided taxRulesGroups have been removed
        foreach ($bulkTaxRulesGroups as $taxRulesGroupId) {
            $taxRulesGroup = $this->getItem('/tax-rules-groups/' . $taxRulesGroupId, ['tax_rules_group_read']);

            $this->assertEquals(false, $taxRulesGroup['enabled']);
        }

        $this->assertEquals(52, $this->countItems('/tax-rules-groups', ['tax_rules_group_read']));
    }

    /**
     * @depends testDeleteTaxRulesGroup
     *
     * @param int $taxRulesGroupId
     *
     * @return int
     */
    public function testBulkDeleteTaxRulesGroups(): void
    {
        $taxRulesGroups = $this->listItems('/tax-rules-groups', ['tax_rules_group_read']);

        // There are taxRulesGroups in default fixtures
        $this->assertEquals(52, $taxRulesGroups['totalItems']);

        // We remove the two taxRulesGroups
        $bulkTaxRulesGroups = [
            $taxRulesGroups['items'][2]['taxRulesGroupId'],
            $taxRulesGroups['items'][3]['taxRulesGroupId'],
        ];

        $this->bulkDeleteItems('/tax-rules-groups/bulk-delete', [
            'taxRulesGroupIds' => $bulkTaxRulesGroups,
        ], ['tax_rules_group_write']);

        // Assert the provided taxRulesGroups have been removed
        foreach ($bulkTaxRulesGroups as $taxRulesGroupId) {
            $this->getItem('/tax-rules-groups/' . $taxRulesGroupId, ['tax_rules_group_read'], Response::HTTP_NOT_FOUND);
        }

        $this->assertEquals(50, $this->countItems('/tax-rules-groups', ['tax_rules_group_read']));
    }

    public function testListTaxRulesOfGroup(): array
    {
        $this->markTestSkippedByMinVersion('9.2.0');
        $fixtures = $this->createTaxRuleFixtures();

        $response = $this->listItems('/tax-rules-groups/' . $fixtures['taxRulesGroupId'] . '/tax-rules', ['tax_rules_group_read']);
        $expected = [
            'totalItems' => 3,
            'sortOrder' => 'asc',
            'limit' => 10,
            'offset' => 0,
            'filters' => [],
            'items' => [
                $this->getExpectedTaxRule($fixtures, 'FR'),
                $this->getExpectedTaxRule($fixtures, 'IT'),
                $this->getExpectedTaxRule($fixtures, 'DE'),
            ],
        ];
        if (self::isVersionUnder('9.2.1')) {
            // The first page has a null offset the serializer drops, PrestaShop 9.2.1 returns 0
            unset($expected['offset']);
        }
        $this->assertEquals($expected, $response);

        return $fixtures;
    }

    /**
     * @depends testListTaxRulesOfGroup
     */
    public function testListTaxRulesPagination(array $fixtures): void
    {
        $response = $this->listItems('/tax-rules-groups/' . $fixtures['taxRulesGroupId'] . '/tax-rules?limit=1&offset=1', ['tax_rules_group_read']);
        $this->assertEquals([
            'totalItems' => 3,
            'sortOrder' => 'asc',
            'limit' => 1,
            'offset' => 1,
            'filters' => [],
            'items' => [
                $this->getExpectedTaxRule($fixtures, 'IT'),
            ],
        ], $response);
    }

    public function testListTaxRulesForMissingGroup(): void
    {
        $this->markTestSkippedByMinVersion('9.2.0');
        // GetTaxRuleList does not check that the group exists: an unknown group answers an empty list,
        // and TaxRulesGroupId accepts 0, so it answers the same
        $expected = [
            'totalItems' => 0,
            'sortOrder' => 'asc',
            'limit' => 10,
            'offset' => 0,
            'filters' => [],
            'items' => [],
        ];
        if (self::isVersionUnder('9.2.1')) {
            // The first page has a null offset the serializer drops, PrestaShop 9.2.1 returns 0
            unset($expected['offset']);
        }
        foreach ([999999, 0] as $taxRulesGroupId) {
            $response = $this->listItems('/tax-rules-groups/' . $taxRulesGroupId . '/tax-rules', ['tax_rules_group_read']);
            $this->assertEquals($expected, $response);
        }
    }

    public function testCreateInvalidTaxRulesGroup(): void
    {
        // Creating with invalid data should return a response with invalid constraint messages and use an http code 422
        $validationErrorsResponse = $this->createItem('/tax-rules-groups', [
            'name' => '',
        ], ['tax_rules_group_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertIsArray($validationErrorsResponse);
        $this->assertValidationErrors([
            [
                'propertyPath' => 'name',
                'message' => 'This value should not be blank.',
            ],
            [
                'propertyPath' => 'name',
                'message' => 'This value is too short. It should have 1 character or more.',
            ],
            [
                'propertyPath' => 'enabled',
                'message' => 'This value should not be null.',
            ],
        ], $validationErrorsResponse);
    }

    /**
     * Creates a tax rules group with three tax rules (France, Italy, Germany) sharing one tax.
     *
     * @return array{taxRulesGroupId: int, taxRuleIds: array<string, int>}
     */
    private function createTaxRuleFixtures(): array
    {
        $tax = $this->createItem('/taxes', [
            'names' => ['en-US' => 'API Test Tax EN', 'fr-FR' => 'API Test Tax FR'],
            'enabled' => true,
            'rate' => 10.0,
        ], ['tax_write']);

        $taxRulesGroup = $this->createItem('/tax-rules-groups', [
            'name' => 'API Test Tax Rules Group',
            'enabled' => true,
            'shopIds' => [1],
        ], ['tax_rules_group_write']);

        $taxRuleIds = [];
        foreach (self::TAX_RULE_FIXTURES as $isoCode => $fixture) {
            $countryId = (int) \Country::getByIso($isoCode);
            $taxRule = $this->createItem('/tax-rules', [
                'taxRulesGroupId' => $taxRulesGroup['taxRulesGroupId'],
                'countryId' => $countryId,
                'stateIds' => [isset($fixture['stateIsoCode']) ? (int) \State::getIdByIso($fixture['stateIsoCode'], $countryId) : 0],
                'taxId' => $tax['taxId'],
                'behavior' => $fixture['behavior'],
                'zipCode' => $fixture['zipCode'] ?? '',
                'description' => 'API test rule ' . $isoCode,
            ], ['tax_rule_write']);
            $taxRuleIds[$isoCode] = $taxRule['taxRuleId'];
        }

        return [
            'taxRulesGroupId' => $taxRulesGroup['taxRulesGroupId'],
            'taxRuleIds' => $taxRuleIds,
        ];
    }

    private function getExpectedTaxRule(array $fixtures, string $isoCode): array
    {
        return [
            'taxRulesGroupId' => $fixtures['taxRulesGroupId'],
            'taxRuleId' => $fixtures['taxRuleIds'][$isoCode],
            'countryName' => self::TAX_RULE_FIXTURES[$isoCode]['countryName'],
            'stateName' => self::TAX_RULE_FIXTURES[$isoCode]['stateName'] ?? '--',
            'zipcode' => self::TAX_RULE_FIXTURES[$isoCode]['expectedZipcode'] ?? '--',
            'behavior' => self::TAX_RULE_FIXTURES[$isoCode]['behavior'],
            // The list is in the language of the context, the default language of the shop
            'taxName' => 'API Test Tax EN',
            'rate' => 10.0,
            'description' => 'API test rule ' . $isoCode,
        ];
    }
}
