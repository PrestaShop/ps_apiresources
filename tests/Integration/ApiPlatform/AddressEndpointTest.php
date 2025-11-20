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

class AddressEndpointTest extends ApiTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        DatabaseDump::restoreTables(['address', 'customer']);
        self::createApiClient(['address_write', 'address_read']);
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        // Reset DB as it was before this test
        DatabaseDump::restoreTables(['address', 'customer']);
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'get customer address endpoint' => [
            'GET',
            '/addresses/customers/1',
        ];

        yield 'create customer address endpoint' => [
            'POST',
            '/addresses/customers',
        ];

        yield 'update customer address endpoint' => [
            'PATCH',
            '/addresses/customers/1',
        ];

        yield 'delete address endpoint' => [
            'DELETE',
            '/addresses/1',
        ];

        yield 'list addresses endpoint' => [
            'GET',
            '/addresses',
        ];

        yield 'bulk delete addresses endpoint' => [
            'PUT',
            '/addresses/delete',
        ];
    }

    public function testAddCustomerAddress(): int
    {
        $postData = [
            'customerId' => 1,
            'addressAlias' => 'Test Address',
            'firstName' => 'John',
            'lastName' => 'Doe',
            'address' => '123 Test Street',
            'address2' => 'Building 1',
            'city' => 'Test City',
            'postCode' => '12345',
            'countryId' => 8, // France
            'dni' => '1234567890',
            'company' => 'Test Company',
            'vatNumber' => 'FR12345678901',
            'stateId' => 0,
            'homePhone' => '0123456789',
            'mobilePhone' => '0612345678',
            'other' => 'Test Other',
        ];

        $customerAddress = $this->createItem('/addresses/customers', $postData, ['address_write']);
        $this->assertArrayHasKey('addressId', $customerAddress);
        $addressId = $customerAddress['addressId'];

        // Check that all data was saved correctly
        foreach ($postData as $key => $value) {
            $this->assertEquals($value, $customerAddress[$key], 'Address data mismatch for key: ' . $key);
        }

        return $addressId;
    }

    /**
     * @depends testAddCustomerAddress
     *
     * @param int $addressId
     *
     * @return int
     */
    public function testListAddresses(int $addressId): int
    {
        $addresses = $this->listItems('/addresses?orderBy=addressId&sortOrder=desc', ['address_read']);
        $this->assertGreaterThan(0, $addresses['totalItems']);

        // Check the details to make sure filters mapping is correct
        $this->assertEquals('addressId', $addresses['orderBy']);

        // Test address should be the first returned in the list
        $testAddress = $addresses['items'][0];
        $expectedAddress = [
            'addressId' => $addressId,
            'address1' => '123 Test Street',
            'city' => 'Test City',
        ];
        foreach ($expectedAddress as $key => $value) {
            $this->assertEquals($value, $testAddress[$key], 'Address data mismatch for key: ' . $key);
        }

        return $addressId;
    }

    /**
     * @depends testAddCustomerAddress
     *
     * @param int $addressId
     *
     * @return int
     */
    public function testUpdateCustomerAddress(int $addressId): int
    {
        $updatedData = [
            'addressAlias' => 'Updated Test Address',
            'firstName' => 'Jane',
            'lastName' => 'Smith',
            'address' => '456 Updated Street',
            'city' => 'Updated City',
            'postCode' => '54321',
            'company' => 'Updated Company',
        ];

        $updatedAddress = $this->partialUpdateItem('/addresses/customers/' . $addressId, $updatedData, ['address_write']);

        // Check that the data was updated correctly
        $this->assertEquals($addressId, $updatedAddress['addressId']);
        foreach ($updatedData as $key => $value) {
            $this->assertEquals($value, $updatedAddress[$key], 'Address data mismatch for key: ' . $key);
        }

        return $addressId;
    }

    /**
     * @depends testUpdateCustomerAddress
     *
     * @param int $addressId
     *
     * @return int
     */
    public function testGetCustomerAddress(int $addressId): int
    {
        $expectedData = [
            'addressAlias' => 'Updated Test Address',
            'firstName' => 'Jane',
            'lastName' => 'Smith',
            'address' => '456 Updated Street',
            'city' => 'Updated City',
            'postCode' => '54321',
            'company' => 'Updated Company',
        ];

        $customerAddress = $this->getItem('/addresses/customers/' . $addressId, ['address_read']);

        $this->assertEquals($addressId, $customerAddress['addressId']);
        foreach ($expectedData as $key => $value) {
            $this->assertEquals($value, $customerAddress[$key], 'Address data mismatch for key: ' . $key);
        }

        return $addressId;
    }

    /**
     * @depends testGetCustomerAddress
     */
    public function testBulkDeleteAddresses(int $addressId): void
    {
        // Create a second address for bulk delete test
        $postData = [
            'customerId' => 1,
            'addressAlias' => 'Second Test Address',
            'firstName' => 'Bob',
            'lastName' => 'Wilson',
            'address' => '789 Second Street',
            'city' => 'Second City',
            'postCode' => '67890',
            'countryId' => 8,
            'stateId' => 0,
        ];
        $secondAddress = $this->createItem('/addresses/customers', $postData, ['address_write']);
        $secondAddressId = $secondAddress['addressId'];

        // Test bulk delete
        $bulkDeleteData = [
            'addressIds' => [$addressId, $secondAddressId],
        ];
        $this->updateItem('/addresses/delete', $bulkDeleteData, ['address_write'], 204);

        // Verify addresses were deleted
        $this->getItem('/addresses/customers/' . $addressId, ['address_read'], Response::HTTP_NOT_FOUND);
        $this->getItem('/addresses/customers/' . $secondAddressId, ['address_read'], Response::HTTP_NOT_FOUND);
    }

    public function testDeleteSingleAddress(): void
    {
        // Create an address to delete
        $postData = [
            'customerId' => 1,
            'addressAlias' => 'Address to Delete',
            'firstName' => 'Delete',
            'lastName' => 'Test',
            'address' => '999 Delete Street',
            'city' => 'Delete City',
            'postCode' => '99999',
            'countryId' => 8,
            'stateId' => 0,
        ];
        $address = $this->createItem('/addresses/customers', $postData, ['address_write']);
        $addressId = $address['addressId'];

        // Delete and check
        $this->deleteItem('/addresses/' . $addressId, ['address_write']);
        $this->getItem('/addresses/customers/' . $addressId, ['address_read'], Response::HTTP_NOT_FOUND);
    }

    public function testAddressValidation(): void
    {
        // Test with invalid data to check validation
        $invalidData = [
            'customerId' => 1,
            'addressAlias' => 'Test',
            'firstName' => 'John',
            'lastName' => 'Doe',
            'address' => '', // Empty address should fail validation
            'city' => 'Test City',
            'postCode' => '12345',
            'countryId' => 8,
            'stateId' => 0,
        ];

        // Use the createItem method but expect it to fail with validation error
        $this->createItem('/addresses/customers', $invalidData, ['address_write'], 422);
    }
}
