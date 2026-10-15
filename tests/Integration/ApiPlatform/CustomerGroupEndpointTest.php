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

use Group;
use Symfony\Component\HttpFoundation\Response;
use Tests\Resources\DatabaseDump;

class CustomerGroupEndpointTest extends ApiTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        DatabaseDump::restoreTables(['group', 'group_lang', 'group_reduction', 'group_shop', 'category_group']);
        // Pre-create the API Client with the needed scopes, this way we reduce the number of created API Clients
        self::createApiClient(['customer_group_write', 'customer_group_read']);
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        // Reset DB as it was before this test
        DatabaseDump::restoreTables(['group', 'group_lang', 'group_reduction', 'group_shop', 'category_group']);
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'get endpoint' => [
            'GET',
            '/customers/groups/1',
        ];

        yield 'create endpoint' => [
            'POST',
            '/customers/groups',
        ];

        yield 'update endpoint' => [
            'PATCH',
            '/customers/groups/1',
        ];

        yield 'delete endpoint' => [
            'DELETE',
            '/customers/groups/1',
        ];

        yield 'list endpoint' => [
            'GET',
            '/customers/groups',
        ];
    }

    public function testAddCustomerGroupWithoutShopIds(): void
    {
        $postData = [
            'names' => [
                'en-US' => 'test1',
                'fr-FR' => 'test1 FR',
            ],
            'reductionPercent' => 10.3,
            'displayPriceTaxExcluded' => true,
            'showPrice' => true,
        ];

        // The shops of the context are the default value of shopIds from PrestaShop 9.2.0, it is required before
        if (!self::isVersionAtLeast('9.2.0')) {
            $validationErrorsResponse = $this->createItem('/customers/groups', $postData, ['customer_group_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
            $this->assertIsArray($validationErrorsResponse);
            $this->assertValidationErrors([
                [
                    'propertyPath' => 'shopIds',
                    'message' => 'This value should not be null.',
                ],
            ], $validationErrorsResponse);

            return;
        }

        $customerGroup = $this->createItem('/customers/groups', $postData, ['customer_group_write']);
        $this->assertArrayHasKey('customerGroupId', $customerGroup);
        $customerGroupId = $customerGroup['customerGroupId'];
        $this->assertEquals(['customerGroupId' => $customerGroupId, 'shopIds' => [1]] + $postData, $customerGroup);
    }

    public function testInvalidCustomerGroup(): void
    {
        // The command requires both flags, without them the creation failed with a 500
        $validationErrorsResponse = $this->createItem('/customers/groups', [
            'names' => [
                'en-US' => 'test invalid',
            ],
            'reductionPercent' => 10,
        ], ['customer_group_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertIsArray($validationErrorsResponse);
        $this->assertValidationErrors([
            [
                'propertyPath' => 'displayPriceTaxExcluded',
                'message' => 'This value should not be null.',
            ],
            [
                'propertyPath' => 'showPrice',
                'message' => 'This value should not be null.',
            ],
        ], $validationErrorsResponse);

        // The reduction is checked by the core command
        $this->createItem('/customers/groups', [
            'names' => [
                'en-US' => 'test invalid',
            ],
            'reductionPercent' => 150,
            'displayPriceTaxExcluded' => false,
            'showPrice' => true,
            // Required before PrestaShop 9.2.0
            'shopIds' => [1],
        ], ['customer_group_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testAddCustomerGroupWithInvalidShops(): void
    {
        $postData = [
            'names' => [
                'en-US' => 'test invalid shops',
            ],
            'reductionPercent' => 10,
            'displayPriceTaxExcluded' => false,
            'showPrice' => true,
        ];
        $this->createItem('/customers/groups', ['shopIds' => [999]] + $postData, ['customer_group_write'], Response::HTTP_NOT_FOUND);
        $this->createItem('/customers/groups', ['shopIds' => [0]] + $postData, ['customer_group_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testPartialUpdateCustomerGroupNotFound(): void
    {
        $this->partialUpdateItem('/customers/groups/99999', ['reductionPercent' => 10], ['customer_group_write'], Response::HTTP_NOT_FOUND);
    }

    public function testDeleteCustomerGroupNotFound(): void
    {
        $this->deleteItem('/customers/groups/99999', ['customer_group_write'], Response::HTTP_NOT_FOUND);
    }

    public function testAddCustomerGroup(): int
    {
        $itemsCount = $this->countItems('/customers/groups', ['customer_group_read']);

        $postData = [
            'names' => [
                'en-US' => 'test1',
                'fr-FR' => 'test1 FR',
            ],
            'reductionPercent' => 10.3,
            'displayPriceTaxExcluded' => true,
            'showPrice' => true,
            'shopIds' => [1],
        ];
        $customerGroup = $this->createItem('/customers/groups', $postData, ['customer_group_write']);
        $this->assertArrayHasKey('customerGroupId', $customerGroup);
        $customerGroupId = $customerGroup['customerGroupId'];
        $this->assertEquals(['customerGroupId' => $customerGroupId] + $postData, $customerGroup);

        $newItemsCount = $this->countItems('/customers/groups', ['customer_group_read']);
        $this->assertEquals($itemsCount + 1, $newItemsCount);

        return $customerGroupId;
    }

    /**
     * @depends testAddCustomerGroup
     *
     * @param int $customerGroupId
     *
     * @return int
     */
    public function testUpdateCustomerGroup(int $customerGroupId): int
    {
        $itemsCount = $this->countItems('/customers/groups', ['customer_group_read']);

        $updatedCustomerGroup = $this->partialUpdateItem('/customers/groups/' . $customerGroupId, [
            'names' => [
                'en-US' => 'new_test1',
                'fr-FR' => 'new_test1 FR',
            ],
            'displayPriceTaxExcluded' => false,
            'shopIds' => [1],
        ], ['customer_group_write']);

        // Returned data has modified fields, the others haven't changed
        $this->assertEquals(
            [
                'customerGroupId' => $customerGroupId,
                'names' => [
                    'en-US' => 'new_test1',
                    'fr-FR' => 'new_test1 FR',
                ],
                'reductionPercent' => 10.3,
                'displayPriceTaxExcluded' => false,
                'showPrice' => true,
                'shopIds' => [1],
            ],
            $updatedCustomerGroup
        );

        // Only the sent field is updated
        $this->assertEquals(
            [
                'customerGroupId' => $customerGroupId,
                'names' => [
                    'en-US' => 'new_test1',
                    'fr-FR' => 'new_test1 FR',
                ],
                'reductionPercent' => 5.5,
                'displayPriceTaxExcluded' => false,
                'showPrice' => true,
                'shopIds' => [1],
            ],
            $this->partialUpdateItem('/customers/groups/' . $customerGroupId, ['reductionPercent' => 5.5], ['customer_group_write'])
        );

        // No new group
        $newItemsCount = $this->countItems('/customers/groups', ['customer_group_read']);
        $this->assertEquals($itemsCount, $newItemsCount);

        return $customerGroupId;
    }

    /**
     * @depends testUpdateCustomerGroup
     *
     * @param int $customerGroupId
     *
     * @return int
     */
    public function testGetCustomerGroup(int $customerGroupId): int
    {
        $customerGroup = $this->getItem('/customers/groups/' . $customerGroupId, ['customer_group_read']);
        // Fetching the updated item returns the same data as the previous response content
        $this->assertEquals(
            [
                'customerGroupId' => $customerGroupId,
                'names' => [
                    'en-US' => 'new_test1',
                    'fr-FR' => 'new_test1 FR',
                ],
                'reductionPercent' => 5.5,
                'displayPriceTaxExcluded' => false,
                'showPrice' => true,
                'shopIds' => [1],
            ],
            $customerGroup
        );

        return $customerGroupId;
    }

    /**
     * @depends testGetCustomerGroup
     */
    public function testListCustomerGroups(int $customerGroupId): int
    {
        $customerGroups = $this->listItems('/customers/groups', ['customer_group_read']);
        // It should be at least four (3 in the fixtures, and at least one created by the tests)
        $this->assertGreaterThanOrEqual(4, $customerGroups['totalItems']);

        $testCustomerGroup = null;
        foreach ($customerGroups['items'] as $customerGroup) {
            if ($customerGroup['customerGroupId'] === $customerGroupId) {
                $testCustomerGroup = $customerGroup;
                break;
            }
        }
        $this->assertNotNull($testCustomerGroup);
        $this->assertEquals([
            'customerGroupId' => $customerGroupId,
            'name' => 'new_test1',
            'reductionPercent' => 5.5,
            'customers' => 0,
            'showPrice' => true,
        ], $testCustomerGroup);

        // Sorting and filters use the API field names
        $sortedGroups = $this->listItems('/customers/groups?orderBy=customerGroupId&sortOrder=desc', ['customer_group_read']);
        $this->assertEquals('customerGroupId', $sortedGroups['orderBy']);
        $this->assertEquals('desc', $sortedGroups['sortOrder']);
        $this->assertEquals($customerGroupId, $sortedGroups['items'][0]['customerGroupId']);

        $filteredGroups = $this->listItems('/customers/groups', ['customer_group_read'], ['customerGroupId' => $customerGroupId]);
        $this->assertEquals(['customerGroupId' => (string) $customerGroupId], $filteredGroups['filters']);
        $this->assertContains($customerGroupId, array_column($filteredGroups['items'], 'customerGroupId'));
        $this->assertLessThan($customerGroups['totalItems'], $filteredGroups['totalItems']);

        return $customerGroupId;
    }

    /**
     * @depends testListCustomerGroups
     *
     * @param int $customerGroupId
     *
     * @return void
     */
    public function testDeleteCustomerGroup(int $customerGroupId): void
    {
        // Delete the item
        $this->deleteItem('/customers/groups/' . $customerGroupId, ['customer_group_write']);
        // Fetching the item returns a 404 indicatjng it no longer exists
        $this->getItem('/customers/groups/' . $customerGroupId, ['customer_group_read'], Response::HTTP_NOT_FOUND);
    }
}
