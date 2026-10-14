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

class CustomerListEndpointTest extends ApiTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::resetTables();
        self::createApiClient(['customer_read', 'customer_write']);
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        self::resetTables();
    }

    protected static function resetTables(): void
    {
        DatabaseDump::restoreTables([
            'customer',
            'customer_group',
        ]);
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'list customers endpoint' => ['GET', '/customers'];
    }

    /**
     * @return int[]
     */
    public function testListCustomers(): array
    {
        $initialCount = $this->countItems('/customers', ['customer_read']);

        $janeId = $this->createItem('/customers', [
            'firstName' => 'Jane',
            'lastName' => 'LISTED',
            'email' => 'jane.listed@example.com',
            'password' => 'TestPassword123!',
            'genderId' => 2,
            'defaultGroupId' => 3,
            'groupIds' => [3],
            'enabled' => true,
            'partnerOffersSubscribed' => false,
            'companyName' => 'Listed Company',
        ], ['customer_write'])['customerId'];
        // AddCustomerCommand has no newsletter parameter, only the edition sets it
        $this->partialUpdateItem('/customers/' . $janeId, ['newsletterSubscribed' => true], ['customer_write']);
        $johnId = $this->createItem('/customers', [
            'firstName' => 'John',
            'lastName' => 'LISTED',
            'email' => 'john.listed@example.com',
            'password' => 'TestPassword123!',
            'genderId' => 1,
            'defaultGroupId' => 3,
            'groupIds' => [3],
            'enabled' => false,
        ], ['customer_write'])['customerId'];

        $this->assertSame($initialCount + 2, $this->countItems('/customers', ['customer_read']));

        $paginatedCustomers = $this->listItems('/customers?orderBy=customerId&sortOrder=desc&limit=2', ['customer_read']);
        $this->assertSame('customerId', $paginatedCustomers['orderBy']);
        $this->assertCount(2, $paginatedCustomers['items']);

        $jane = $paginatedCustomers['items'][1];
        $this->assertIsString($jane['dateAdd']);
        unset($jane['dateAdd']);
        $this->assertSame([
            'customerId' => $janeId,
            'socialTitle' => 'Mrs.',
            'firstName' => 'Jane',
            'lastName' => 'LISTED',
            'email' => 'jane.listed@example.com',
            'companyName' => 'Listed Company',
            // No valid order yet
            'totalSpent' => null,
            'enabled' => true,
            'newsletterSubscribed' => true,
            'partnerOffersSubscribed' => false,
            'defaultGroupName' => 'Customer',
            'shopName' => $jane['shopName'],
            // Never connected to the front office
            'lastVisitDate' => null,
        ], $jane);
        $this->assertSame($johnId, $paginatedCustomers['items'][0]['customerId']);

        return [$janeId, $johnId];
    }

    /**
     * @depends testListCustomers
     *
     * @param int[] $customerIds
     */
    public function testFilterCustomers(array $customerIds): void
    {
        [$janeId, $johnId] = $customerIds;

        $listedIds = function (array $filters): array {
            $paginatedCustomers = $this->listItems('/customers', ['customer_read'], $filters);

            return array_column($paginatedCustomers['items'], 'customerId');
        };

        $this->assertSame([$janeId], $listedIds(['email' => 'jane.listed']));
        $this->assertEqualsCanonicalizing([$janeId, $johnId], $listedIds(['lastName' => 'LISTED']));
        $this->assertSame([$johnId], $listedIds(['lastName' => 'LISTED', 'enabled' => false]));
        $this->assertSame([$janeId], $listedIds(['lastName' => 'LISTED', 'newsletterSubscribed' => true]));
        $this->assertSame([$janeId], $listedIds(['companyName' => 'Listed']));
        $this->assertSame([$janeId], $listedIds(['lastName' => 'LISTED', 'genderId' => 2]));
        $this->assertEqualsCanonicalizing([$janeId, $johnId], $listedIds(['lastName' => 'LISTED', 'defaultGroupId' => 3]));
        $this->assertSame([], $listedIds(['lastName' => 'LISTED', 'defaultGroupId' => 1]));
    }
}
