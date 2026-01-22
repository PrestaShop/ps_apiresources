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
        DatabaseDump::restoreTables(['address', 'customer', 'manufacturer', 'cart']);
        self::createApiClient(['address_write', 'address_read']);
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        // Reset DB as it was before this test
        DatabaseDump::restoreTables(['address', 'customer', 'manufacturer', 'cart']);
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
            'DELETE',
            '/addresses/bulk-delete',
        ];

        yield 'get manufacturer address endpoint' => [
            'GET',
            '/addresses/manufacturers/1',
        ];

        yield 'create manufacturer address endpoint' => [
            'POST',
            '/addresses/manufacturers',
        ];

        yield 'update manufacturer address endpoint' => [
            'PATCH',
            '/addresses/manufacturers/1',
        ];

        yield 'update cart delivery address endpoint' => [
            'PATCH',
            '/addresses/carts/1',
        ];

        yield 'update order delivery address endpoint' => [
            'PATCH',
            '/addresses/orders/1',
        ];
    }

    public function testAddManufacturerAddress(): int
    {
        $postData = [
            'manufacturerId' => 1,
            'firstName' => 'Manu',
            'lastName' => 'Facturer',
            'address' => '11 Rue du Fabricant',
            'address2' => 'Bâtiment B',
            'city' => 'Paris',
            'postCode' => '75001',
            'countryId' => 8,
            'stateId' => 0,
            'homePhone' => '0123456789',
            'mobilePhone' => '0612345678',
            'other' => 'Entrée côté quai',
            'dni' => 'ABC123456',
        ];

        $manufacturerAddress = $this->createItem('/addresses/manufacturers', $postData, ['address_write']);
        $this->assertArrayHasKey('addressId', $manufacturerAddress);
        $addressId = $manufacturerAddress['addressId'];

        foreach ($postData as $key => $value) {
            $this->assertEquals($value, $manufacturerAddress[$key], 'Manufacturer address data mismatch for key: ' . $key);
        }

        return $addressId;
    }

    /**
     * @depends testAddManufacturerAddress
     */
    public function testUpdateManufacturerAddress(int $addressId): int
    {
        $updatedData = [
            'firstName' => 'Marie',
            'lastName' => 'Fab',
            'address' => '22 Avenue Industrie',
            'city' => 'Lyon',
            'postCode' => '69001',
            'homePhone' => '0199999999',
        ];

        $updated = $this->partialUpdateItem('/addresses/manufacturers/' . $addressId, $updatedData, ['address_write']);
        $this->assertEquals($addressId, $updated['addressId']);
        foreach ($updatedData as $key => $value) {
            $this->assertEquals($value, $updated[$key], 'Manufacturer address update mismatch for key: ' . $key);
        }

        return $addressId;
    }

    /**
     * @depends testUpdateManufacturerAddress
     */
    public function testGetManufacturerAddress(int $addressId): void
    {
        $expected = $this->getItem('/addresses/manufacturers/' . $addressId, ['address_read']);
        $this->assertEquals($addressId, $expected['addressId']);
        $this->assertEquals('Marie', $expected['firstName']);
        $this->assertEquals('Fab', $expected['lastName']);
        $this->assertEquals('22 Avenue Industrie', $expected['address']);
        $this->assertEquals('Lyon', $expected['city']);
        $this->assertEquals('69001', $expected['postCode']);
    }

    public function testUpdateCartAddress(): void
    {
        $updatedData = [
            'addressType' => 'delivery_address',
            'addressAlias' => 'Cart updated',
            'firstName' => 'John NEW',
            'lastName' => 'CART DELIVERY',
            'address' => '42 street Example',
            'address2' => 'line 42',
            'city' => 'Orleans',
            'postCode' => '45000',
            'countryId' => 8,
            'stateId' => 0,
            'company' => 'Test Company',
            'homePhone' => '0238123456',
            'mobilePhone' => '0612345678',
            'other' => 'other data',
            'dni' => '123456798',
            'vatNumber' => 'FR66497916635',
        ];

        // Use cart ID 1 from test database
        $updated = $this->partialUpdateItem('/addresses/carts/1', $updatedData, ['address_write']);
        $this->assertEquals(1, $updated['cartId']);

        // Verify all updated fields are returned (addressType is not returned, it's only an input parameter)
        foreach ($updatedData as $key => $value) {
            if ($key !== 'addressType') {
                $this->assertEquals($value, $updated[$key], 'Cart address update mismatch for key: ' . $key);
            }
        }
    }

    public function testUpdateCartAddressNotFound(): void
    {
        $updatedData = [
            'addressType' => 'delivery_address',
            'firstName' => 'Cart',
            'lastName' => 'User',
            'address' => '10 Rue du Panier',
            'city' => 'Marseille',
            'postCode' => '13001',
        ];

        // Expect 404 for non existing cart id
        $this->partialUpdateItem('/addresses/carts/999999', $updatedData, ['address_write'], Response::HTTP_NOT_FOUND);
    }

    public function testUpdateOrderAddress(): void
    {
        // First, create a customer address that we'll use for the order
        $addressData = [
            'customerId' => 1,
            'addressAlias' => 'Order Test Address',
            'firstName' => 'Order',
            'lastName' => 'Test',
            'address' => '1 street Example',
            'city' => 'Orleans',
            'postCode' => '45000',
            'countryId' => 8,
            'stateId' => 0,
        ];
        $customerAddress = $this->createItem('/addresses/customers', $addressData, ['address_write']);
        $addressId = $customerAddress['addressId'];
        $expectedAddress = [
            'addressId' => $addressId,
            'address2' => '',
            'dni' => '',
            'company' => '',
            'vatNumber' => '',
            'homePhone' => '',
            'mobilePhone' => '',
            'other' => '',
        ] + $addressData;
        $this->assertEquals($expectedAddress, $customerAddress);

        // Create a minimal order with this address
        $order = new \Order();
        $order->id_customer = 1;
        $order->id_address_invoice = $addressId;
        $order->id_address_delivery = $addressId;
        $order->id_cart = 1;
        $order->id_currency = 1;
        $order->id_carrier = 1;
        $order->id_shop = 1;
        $order->id_shop_group = 1;
        $order->payment = 'Payment by check';
        $order->module = 'ps_checkpayment';
        $order->total_paid = $order->total_paid_real = $order->total_paid_tax_incl = $order->total_paid_tax_excl = 42;
        $order->total_products = $order->total_products_wt = 42;
        $order->conversion_rate = 1.0;
        $order->save();
        $orderId = $order->id;

        $updatedData = [
            'addressType' => 'invoice_address',
            'addressAlias' => 'Test',
            'firstName' => 'John',
            'lastName' => 'Doe',
            'address' => '1 street Example',
            'city' => 'Orleans',
            'postCode' => '45000',
            'countryId' => 8,
            'stateId' => 0,
        ];

        $updated = $this->partialUpdateItem('/addresses/orders/' . $orderId, $updatedData, ['address_write']);
        $this->assertEquals($orderId, $updated['orderId']);

        // Verify all updated fields are returned (addressType is not returned, it's only an input parameter)
        foreach ($updatedData as $key => $value) {
            if ($key !== 'addressType') {
                $this->assertEquals($value, $updated[$key], 'Order address update mismatch for key: ' . $key);
            }
        }

        // Cleanup: delete the order
        $order->delete();
    }

    public function testUpdateOrderAddressNotFound(): void
    {
        $updatedData = [
            'addressType' => 'delivery_address',
            'firstName' => 'Order',
            'lastName' => 'User',
            'address' => '11 Rue de la Paix',
            'city' => 'Paris',
            'postCode' => '75002',
        ];

        // Expect 422 because underlying address resolution fails when order is invalid
        $this->partialUpdateItem('/addresses/orders/999999', $updatedData, ['address_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
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
        $this->assertArrayHasKey('addressId', $updatedAddress);
        $this->assertEquals($addressId, $updatedAddress['addressId'], 'Address ID mismatch after update');
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
        $this->bulkDeleteItems('/addresses/bulk-delete', $bulkDeleteData, ['address_write']);

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
