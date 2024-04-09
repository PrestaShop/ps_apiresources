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
use Tests\Resources\DatabaseDump;

class CustomerGroupApiTest extends ApiTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        DatabaseDump::restoreTables(['group', 'group_lang', 'group_reduction', 'group_shop', 'category_group']);
        self::createApiClient(['customer_group_write', 'customer_group_read']);
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        DatabaseDump::restoreTables(['group', 'group_lang', 'group_reduction', 'group_shop', 'category_group']);
    }

    public function getProtectedEndpoints(): iterable
    {
        yield 'get endpoint' => [
            'GET',
            '/customers/group/1',
        ];

        yield 'create endpoint' => [
            'POST',
            '/customers/group',
        ];

        yield 'update endpoint' => [
            'PUT',
            '/customers/group/1',
        ];

        yield 'delete endpoint' => [
            'DELETE',
            '/customers/group/1',
        ];
    }

    public function testAddCustomerGroup(): int
    {
        $numberOfGroups = count(\Group::getGroups(\Context::getContext()->language->id));

        $bearerToken = $this->getBearerToken(['customer_group_write']);
        $response = static::createClient()->request('POST', '/customers/group', [
            'auth_bearer' => $bearerToken,
            'json' => [
                'localizedNames' => [
                    1 => 'test1',
                ],
                'reductionPercent' => 10.3,
                'displayPriceTaxExcluded' => true,
                'showPrice' => true,
                'shopIds' => [1],
            ],
        ]);
        self::assertResponseStatusCodeSame(201);
        self::assertCount($numberOfGroups + 1, \Group::getGroups(\Context::getContext()->language->id));

        $decodedResponse = json_decode($response->getContent(), true);
        $this->assertNotFalse($decodedResponse);
        $this->assertArrayHasKey('customerGroupId', $decodedResponse);
        $customerGroupId = $decodedResponse['customerGroupId'];
        $this->assertEquals(
            [
                'customerGroupId' => $customerGroupId,
                'localizedNames' => [
                    1 => 'test1',
                ],
                'reductionPercent' => 10.3,
                'displayPriceTaxExcluded' => true,
                'showPrice' => true,
                'shopIds' => [1],
            ],
            $decodedResponse
        );

        return $customerGroupId;
    }

    public function testAddCustomerGroupWithoutShopIds(): int
    {
        $numberOfGroups = count(\Group::getGroups(\Context::getContext()->language->id));

        $bearerToken = $this->getBearerToken(['customer_group_write']);
        $response = static::createClient()->request('POST', '/customers/group', [
            'auth_bearer' => $bearerToken,
            'json' => [
                'localizedNames' => [
                    1 => 'test1',
                ],
                'reductionPercent' => 10.3,
                'displayPriceTaxExcluded' => true,
                'showPrice' => true,
            ],
        ]);
        self::assertResponseStatusCodeSame(201);
        self::assertCount($numberOfGroups + 1, \Group::getGroups(\Context::getContext()->language->id));

        $decodedResponse = json_decode($response->getContent(), true);
        $this->assertNotFalse($decodedResponse);
        $this->assertArrayHasKey('customerGroupId', $decodedResponse);
        $customerGroupId = $decodedResponse['customerGroupId'];
        $this->assertEquals(
            [
                'customerGroupId' => $customerGroupId,
                'localizedNames' => [
                    1 => 'test1',
                ],
                'reductionPercent' => 10.3,
                'displayPriceTaxExcluded' => true,
                'showPrice' => true,
                'shopIds' => [1],
            ],
            $decodedResponse
        );

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
        $numberOfGroups = count(\Group::getGroups(\Context::getContext()->language->id));

        $bearerToken = $this->getBearerToken(['customer_group_write']);
        // Update customer group with partial data
        $response = static::createClient()->request('PUT', '/customers/group/' . $customerGroupId, [
            'auth_bearer' => $bearerToken,
            'json' => [
                'localizedNames' => [
                    1 => 'new_test1',
                ],
                'displayPriceTaxExcluded' => false,
                'shopIds' => [1],
            ],
        ]);
        self::assertResponseStatusCodeSame(200);
        // No new group
        self::assertCount($numberOfGroups, \Group::getGroups(\Context::getContext()->language->id));

        $decodedResponse = json_decode($response->getContent(), true);
        $this->assertNotFalse($decodedResponse);
        // Returned data has modified fields, the others haven't changed
        $this->assertEquals(
            [
                'customerGroupId' => $customerGroupId,
                'localizedNames' => [
                    1 => 'new_test1',
                ],
                'reductionPercent' => 10.3,
                'displayPriceTaxExcluded' => false,
                'showPrice' => true,
                'shopIds' => [1],
            ],
            $decodedResponse
        );

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
        $bearerToken = $this->getBearerToken(['customer_group_read']);
        $response = static::createClient()->request('GET', '/customers/group/' . $customerGroupId, [
            'auth_bearer' => $bearerToken,
        ]);
        self::assertResponseStatusCodeSame(200);

        $decodedResponse = json_decode($response->getContent(), true);
        $this->assertNotFalse($decodedResponse);
        // Returned data has modified fields, the others haven't changed
        $this->assertEquals(
            [
                'customerGroupId' => $customerGroupId,
                'localizedNames' => [
                    1 => 'new_test1',
                ],
                'reductionPercent' => 10.3,
                'displayPriceTaxExcluded' => false,
                'showPrice' => true,
                'shopIds' => [1],
            ],
            $decodedResponse
        );

        return $customerGroupId;
    }

    /**
     * @depends testGetCustomerGroup
     *
     * @param int $customerGroupId
     *
     * @return void
     */
    public function testDeleteCustomerGroup(int $customerGroupId): void
    {
        $bearerToken = $this->getBearerToken(['customer_group_read', 'customer_group_write']);
        // Update customer group with partial data
        $response = static::createClient()->request('DELETE', '/customers/group/' . $customerGroupId, [
            'auth_bearer' => $bearerToken,
        ]);
        self::assertResponseStatusCodeSame(204);
        $this->assertEmpty($response->getContent());

        static::createClient()->request('GET', '/customers/group/' . $customerGroupId, [
            'auth_bearer' => $bearerToken,
        ]);
        self::assertResponseStatusCodeSame(404);
    }
}
