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
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::createApiClient(['tax_rules_group_write', 'tax_rules_group_read']);
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        // Reset DB as it was before this test
        DatabaseDump::restoreTables(['tax_rules_group', 'tax_rules_group_shop']);
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'create endpoint' => [
            'POST',
            '/tax-rules-group',
        ];

        yield 'get endpoint' => [
            'GET',
            '/tax-rules-group/1',
        ];

        yield 'update endpoint' => [
            'PATCH',
            '/tax-rules-group/1',
        ];

        yield 'delete endpoint' => [
            'DELETE',
            '/tax-rules-group/1',
        ];

        yield 'list endpoint' => [
            'GET',
            '/tax-rules-groups',
        ];

        yield 'bulk delete endpoint' => [
            'PUT',
            '/tax-rules-groups/delete',
        ];

        yield 'bulk toggle status endpoint' => [
            'PUT',
            '/tax-rules-groups/set-status',
        ];
    }

    public function testAddTaxRulesGroup(): int
    {
        $itemsCount = $this->countItems('/tax-rules-groups', ['tax_rules_group_read']);

        $taxRulesGroup = $this->createItem('/tax-rules-group', [
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
        $taxRulesGroup = $this->getItem('/tax-rules-group/' . $taxRulesGroupId, ['tax_rules_group_read']);
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
        $updatedTaxRulesGroup = $this->partialUpdateItem('/tax-rules-group/' . $taxRulesGroupId, [
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

        $updatedTaxRulesGroup = $this->partialUpdateItem('/tax-rules-group/' . $taxRulesGroupId, [
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
        $taxRulesGroup = $this->getItem('/tax-rules-group/' . $taxRulesGroupId, ['tax_rules_group_read']);
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
        $return = $this->deleteItem('/tax-rules-group/' . $taxRulesGroupId, ['tax_rules_group_write']);
        // This endpoint return empty response and 204 HTTP code
        $this->assertNull($return);

        // Getting the item should result in a 404 now
        $this->getItem('/tax-rules-group/' . $taxRulesGroupId, ['tax_rules_group_read'], Response::HTTP_NOT_FOUND);
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
            $taxRulesGroup = $this->getItem('/tax-rules-group/' . $taxRulesGroupId, ['tax_rules_group_read']);

            $this->assertEquals(true, $taxRulesGroup['enabled']);
        }

        $this->updateItem('/tax-rules-groups/set-status', [
            'taxRulesGroupIds' => $bulkTaxRulesGroups,
            'enabled' => false,
        ], ['tax_rules_group_write'], Response::HTTP_NO_CONTENT);

        // Assert the provided taxRulesGroups have been removed
        foreach ($bulkTaxRulesGroups as $taxRulesGroupId) {
            $taxRulesGroup = $this->getItem('/tax-rules-group/' . $taxRulesGroupId, ['tax_rules_group_read']);

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

        $this->updateItem('/tax-rules-groups/delete', [
            'taxRulesGroupIds' => $bulkTaxRulesGroups,
        ], ['tax_rules_group_write'], Response::HTTP_NO_CONTENT);

        // Assert the provided taxRulesGroups have been removed
        foreach ($bulkTaxRulesGroups as $taxRulesGroupId) {
            $this->getItem('/tax-rules-group/' . $taxRulesGroupId, ['tax_rules_group_read'], Response::HTTP_NOT_FOUND);
        }

        $this->assertEquals(50, $this->countItems('/tax-rules-groups', ['tax_rules_group_read']));
    }

    public function testCreateInvalidTaxRulesGroup(): void
    {
        // Creating with invalid data should return a response with invalid constraint messages and use an http code 422
        $validationErrorsResponse = $this->createItem('/tax-rules-group', [
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
}
