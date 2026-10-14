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

class CustomerEndpointTest extends ApiTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        DatabaseDump::restoreTables(['customer', 'customer_group']);
        self::createApiClient(['customer_write', 'customer_read']);
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        // Reset DB as it was before this test
        DatabaseDump::restoreTables(['customer', 'customer_group']);
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'create customer endpoint' => [
            'POST',
            '/customers',
        ];

        yield 'get customer endpoint' => [
            'GET',
            '/customers/1',
        ];

        yield 'get customer details endpoint' => [
            'GET',
            '/customers/1/details',
        ];

        yield 'get customer orders endpoint' => [
            'GET',
            '/customers/1/orders',
        ];

        yield 'update customer endpoint' => [
            'PATCH',
            '/customers/1',
        ];

        yield 'set private note endpoint' => [
            'PATCH',
            '/customers/1/set-private-note',
        ];

        yield 'delete customer endpoint' => [
            'DELETE',
            '/customers/1',
        ];

        yield 'bulk delete customers endpoint' => [
            'DELETE',
            '/customers/bulk-delete',
        ];

        yield 'bulk disable customers endpoint' => [
            'PUT',
            '/customers/bulk-disable',
        ];

        yield 'bulk enable customers endpoint' => [
            'PUT',
            '/customers/bulk-enable',
        ];

        yield 'search customers endpoint' => [
            'GET',
            '/customers/search?phrases[]=test',
        ];
    }

    public function testAddCustomer(): int
    {
        $postData = [
            'firstName' => 'Jane',
            'lastName' => 'Doe',
            'email' => 'jane.doe@example.com',
            'password' => 'TestPassword123!',
            'defaultGroupId' => 3,
            'groupIds' => [3],
            'genderId' => 1,
            'enabled' => true,
            'partnerOffersSubscribed' => true,
            'birthday' => '1990-01-15',
            'guest' => false,
        ];

        $customer = $this->createItem('/customers', $postData, ['customer_write']);
        $this->assertArrayHasKey('customerId', $customer);
        $customerId = $customer['customerId'];

        // Check that all data was saved correctly
        $expectedData = [
            'customerId' => $customerId,
            'firstName' => 'Jane',
            'lastName' => 'Doe',
            'email' => 'jane.doe@example.com',
            'defaultGroupId' => 3,
            'groupIds' => [3],
            'genderId' => 1,
            'enabled' => true,
            'partnerOffersSubscribed' => true,
            'birthday' => '1990-01-15',
            'guest' => false,
            'newsletterSubscribed' => false,
            'companyName' => '',
            'siretCode' => '',
            'apeCode' => '',
            'website' => '',
            'allowedOutstandingAmount' => 0.0,
            'maxPaymentDays' => 0,
            'riskId' => 0,
        ];
        $this->assertEquals($expectedData, $customer);

        return $customerId;
    }

    public function testAddCustomerSubscribedToNewsletter(): void
    {
        $customer = $this->createItem('/customers', [
            'firstName' => 'Jane',
            'lastName' => 'Newsletter',
            'email' => 'jane.newsletter@example.com',
            'password' => 'TestPassword123!',
            'defaultGroupId' => 3,
            'groupIds' => [3],
            'newsletterSubscribed' => true,
        ], ['customer_write']);

        // Older cores have no newsletter in AddCustomerCommand and ignore the field, fixed by PrestaShop/PrestaShop#43116
        $this->assertSame(self::isVersionAtLeast('9.2.1'), $customer['newsletterSubscribed']);
    }

    public function testAddCustomerWithB2bFields(): int
    {
        $postData = [
            'firstName' => 'Jane',
            'lastName' => 'Smith',
            'email' => 'jane.smith@example.com',
            'password' => 'TestPassword321!',
            'defaultGroupId' => 3,
            'groupIds' => [3],
            'genderId' => 2,
            'enabled' => true,
            'partnerOffersSubscribed' => true,
            'birthday' => '1985-05-20',
            'guest' => false,
            'companyName' => 'Test Company',
            'siretCode' => '12345678901234',
            'apeCode' => '1234Z',
            'website' => 'https://www.example.com',
            'allowedOutstandingAmount' => 1000.50,
            'maxPaymentDays' => 30,
            'riskId' => 1,
        ];

        $customer = $this->createItem('/customers', $postData, ['customer_write']);
        $this->assertArrayHasKey('customerId', $customer);
        $customerId = $customer['customerId'];

        // Check that all data was saved correctly
        $expectedData = [
            'customerId' => $customerId,
            'firstName' => 'Jane',
            'lastName' => 'Smith',
            'email' => 'jane.smith@example.com',
            'defaultGroupId' => 3,
            'groupIds' => [3],
            'genderId' => 2,
            'enabled' => true,
            'partnerOffersSubscribed' => true,
            'birthday' => '1985-05-20',
            'guest' => false,
            'newsletterSubscribed' => false,
            'companyName' => 'Test Company',
            'siretCode' => '12345678901234',
            'apeCode' => '1234Z',
            'website' => 'https://www.example.com',
            'allowedOutstandingAmount' => 1000.50,
            'maxPaymentDays' => 30,
            'riskId' => 1,
        ];
        $this->assertEquals($expectedData, $customer);

        return $customerId;
    }

    public function testAddCustomerWithMinimalData(): int
    {
        $postData = [
            'firstName' => 'Minimal',
            'lastName' => 'Customer',
            'email' => 'minimal.customer@example.com',
            'password' => 'TestPassword456!',
            'defaultGroupId' => 3,
            'groupIds' => [3],
        ];

        $customer = $this->createItem('/customers', $postData, ['customer_write']);
        $this->assertArrayHasKey('customerId', $customer);
        $customerId = $customer['customerId'];

        // Check that all required data was saved correctly
        $expectedData = [
            'customerId' => $customerId,
            'firstName' => 'Minimal',
            'lastName' => 'Customer',
            'email' => 'minimal.customer@example.com',
            'defaultGroupId' => 3,
            'groupIds' => [3],
            'genderId' => 0,
            'enabled' => true,
            'partnerOffersSubscribed' => false,
            'birthday' => '0000-00-00',
            'guest' => false,
            'newsletterSubscribed' => false,
            'companyName' => '',
            'siretCode' => '',
            'apeCode' => '',
            'website' => '',
            'allowedOutstandingAmount' => 0.0,
            'maxPaymentDays' => 0,
            'riskId' => 0,
        ];
        $this->assertEquals($expectedData, $customer);

        return $customerId;
    }

    public function testAddGuestCustomer(): int
    {
        $postData = [
            'firstName' => 'Jane',
            'lastName' => 'GUEST',
            'email' => 'jane.guest@example.com',
            'password' => 'INVALID',
            'genderId' => 2,
            'guest' => true,
            'defaultGroupId' => 1,
            'groupIds' => [1],
            'enabled' => false,
        ];

        $customer = $this->createItem('/customers', $postData, ['customer_write']);
        $this->assertArrayHasKey('customerId', $customer);
        $customerId = $customer['customerId'];

        // Check that all data was saved correctly
        $expectedData = [
            'customerId' => $customerId,
            'firstName' => 'Jane',
            'lastName' => 'GUEST',
            'email' => 'jane.guest@example.com',
            'defaultGroupId' => 1,
            'groupIds' => [1],
            'genderId' => 2,
            'enabled' => false,
            'partnerOffersSubscribed' => false,
            'birthday' => '0000-00-00',
            'guest' => true,
            'newsletterSubscribed' => false,
            'companyName' => '',
            'siretCode' => '',
            'apeCode' => '',
            'website' => '',
            'allowedOutstandingAmount' => 0.0,
            'maxPaymentDays' => 0,
            'riskId' => 0,
        ];
        $this->assertEquals($expectedData, $customer);

        return $customerId;
    }

    public function testAddCustomerValidationErrors(): void
    {
        // Test with missing required fields
        $invalidData = [
            'firstName' => '',
            'lastName' => 'Doe',
            'email' => 'invalid-email',
            'password' => '',
            'defaultGroupId' => 3,
            'groupIds' => [],
        ];

        $validationErrorsResponse = $this->createItem('/customers', $invalidData, ['customer_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertIsArray($validationErrorsResponse);

        // Check that we have validation errors
        $this->assertIsArray($validationErrorsResponse);
        $this->assertNotEmpty($validationErrorsResponse);

        // Check for specific validation errors (messages are lang-dependant. We check the key)
        $propertyPaths = array_column($validationErrorsResponse, 'propertyPath');
        $this->assertContains('firstName', $propertyPaths);
        $this->assertContains('email', $propertyPaths);
        $this->assertContains('password', $propertyPaths);
        $this->assertContains('groupIds', $propertyPaths);
    }

    public function testAddCustomerDuplicateEmail(): void
    {
        // First, create a customer
        $postData = [
            'firstName' => 'First',
            'lastName' => 'Customer',
            'email' => 'duplicate@example.com',
            'password' => 'TestPassword123!',
            'defaultGroupId' => 3,
            'groupIds' => [3],
        ];

        $firstCustomer = $this->createItem('/customers', $postData, ['customer_write']);
        $this->assertArrayHasKey('customerId', $firstCustomer);

        // Try to create another customer with the same email (should fail)
        $duplicateData = [
            'firstName' => 'Second',
            'lastName' => 'Customer',
            'email' => 'duplicate@example.com',
            'password' => 'TestPassword123!',
            'defaultGroupId' => 3,
            'groupIds' => [3],
        ];

        $this->createItem('/customers', $duplicateData, ['customer_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /**
     * @depends testPartialUpdateCustomer
     */
    public function testPartialUpdateCustomerMultipleFields(int $customerId): int
    {
        // Update multiple fields with unique email to avoid conflicts
        $uniqueEmail = 'jane.smith.' . time() . '@example.com';
        $updatedCustomer = $this->partialUpdateItem('/customers/' . $customerId, [
            'lastName' => 'Smith',
            'email' => $uniqueEmail,
            'enabled' => false,
            'newsletterSubscribed' => true,
            'partnerOffersSubscribed' => true,
        ], ['customer_write']);

        $this->assertEquals('Jane', $updatedCustomer['firstName']);
        $this->assertEquals('Smith', $updatedCustomer['lastName']);
        $this->assertEquals($uniqueEmail, $updatedCustomer['email']);
        $this->assertFalse($updatedCustomer['enabled']);
        $this->assertTrue($updatedCustomer['newsletterSubscribed']);
        $this->assertTrue($updatedCustomer['partnerOffersSubscribed']);

        return $customerId;
    }

    /**
     * @depends testPartialUpdateCustomerMultipleFields
     */
    public function testPartialUpdateCustomerWithB2bFields(int $customerId): int
    {
        // Update with B2B fields
        $updatedCustomer = $this->partialUpdateItem('/customers/' . $customerId, [
            'companyName' => 'Test Company',
            'siretCode' => '12345678901234',
            'apeCode' => '1234Z',
            'website' => 'https://www.example.com',
            'allowedOutstandingAmount' => 1000.50,
            'maxPaymentDays' => 30,
            'riskId' => 1,
        ], ['customer_write']);

        $this->assertEquals('Test Company', $updatedCustomer['companyName']);
        $this->assertEquals('12345678901234', $updatedCustomer['siretCode']);
        $this->assertEquals('1234Z', $updatedCustomer['apeCode']);
        $this->assertEquals('https://www.example.com', $updatedCustomer['website']);
        $this->assertEquals(1000.50, $updatedCustomer['allowedOutstandingAmount']);
        $this->assertEquals(30, $updatedCustomer['maxPaymentDays']);
        $this->assertEquals(1, $updatedCustomer['riskId']);

        return $customerId;
    }

    public function testPartialUpdateCustomerNotFound(): void
    {
        // Try to update a non-existent customer
        $this->partialUpdateItem('/customers/99999', [
            'firstName' => 'Test',
        ], ['customer_write'], Response::HTTP_NOT_FOUND);
    }

    public function testPartialUpdateCustomerValidationErrors(): void
    {
        // First, create a customer
        $postData = [
            'firstName' => 'Test',
            'lastName' => 'Customer',
            'email' => 'test.validation@example.com',
            'password' => 'TestPassword123!',
            'defaultGroupId' => 3,
            'groupIds' => [3],
        ];

        $customer = $this->createItem('/customers', $postData, ['customer_write']);
        $customerId = $customer['customerId'];

        // Try to update with invalid email
        $validationErrorsResponse = $this->partialUpdateItem('/customers/' . $customerId, [
            'email' => 'invalid-email',
        ], ['customer_write'], Response::HTTP_UNPROCESSABLE_ENTITY);

        $this->assertIsArray($validationErrorsResponse);
        $this->assertNotEmpty($validationErrorsResponse);

        // Check for email validation error
        $propertyPaths = array_column($validationErrorsResponse, 'propertyPath');
        $this->assertContains('email', $propertyPaths);
    }

    public function testPartialUpdateCustomerDuplicateEmail(): void
    {
        // Create first customer
        $this->createItem('/customers', [
            'firstName' => 'First',
            'lastName' => 'Customer',
            'email' => 'first.customer@example.com',
            'password' => 'TestPassword123!',
            'defaultGroupId' => 3,
            'groupIds' => [3],
        ], ['customer_write']);

        // Create second customer
        $secondCustomer = $this->createItem('/customers', [
            'firstName' => 'Second',
            'lastName' => 'Customer',
            'email' => 'second.customer@example.com',
            'password' => 'TestPassword123!',
            'defaultGroupId' => 3,
            'groupIds' => [3],
        ], ['customer_write']);

        // Try to update second customer with first customer's email (should fail)
        $this->partialUpdateItem('/customers/' . $secondCustomer['customerId'], [
            'email' => 'first.customer@example.com',
        ], ['customer_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testPartialUpdateCustomerPassword(): int
    {
        // Create a customer
        $customer = $this->createItem('/customers', [
            'firstName' => 'Password',
            'lastName' => 'Test',
            'email' => 'password.test@example.com',
            'password' => 'OldPassword123!',
            'defaultGroupId' => 3,
            'groupIds' => [3],
        ], ['customer_write']);

        $customerId = $customer['customerId'];

        // Update password
        $updatedCustomer = $this->partialUpdateItem('/customers/' . $customerId, [
            'password' => 'NewPassword123!',
        ], ['customer_write']);

        $this->assertEquals($customerId, $updatedCustomer['customerId']);
        // Password should not be returned in response, but customer should be updated
        $this->assertArrayNotHasKey('password', $updatedCustomer);

        return $customerId;
    }

    public function testPartialUpdateCustomerGroups(): int
    {
        // Create a customer
        $customer = $this->createItem('/customers', [
            'firstName' => 'Group',
            'lastName' => 'Test',
            'email' => 'group.test@example.com',
            'password' => 'TestPassword123!',
            'defaultGroupId' => 3,
            'groupIds' => [3],
        ], ['customer_write']);

        $customerId = $customer['customerId'];

        // Update groups - use group 3 which should exist
        $updatedCustomer = $this->partialUpdateItem('/customers/' . $customerId, [
            'defaultGroupId' => 3,
            'groupIds' => [3],
        ], ['customer_write']);

        $this->assertEquals(3, $updatedCustomer['defaultGroupId']);
        $this->assertContains(3, $updatedCustomer['groupIds']);

        return $customerId;
    }

    /**
     * @depends testAddCustomer
     */
    public function testGetCustomer(int $customerId): void
    {
        $customer = $this->getItem('/customers/' . $customerId, ['customer_read']);

        // Verify basic structure of EditableCustomer response (same as POST/PATCH)
        $this->assertArrayHasKey('customerId', $customer);
        $this->assertEquals($customerId, $customer['customerId']);
        $this->assertArrayHasKey('firstName', $customer);
        $this->assertArrayHasKey('lastName', $customer);
        $this->assertArrayHasKey('email', $customer);
        $this->assertArrayHasKey('defaultGroupId', $customer);
        $this->assertArrayHasKey('groupIds', $customer);
    }

    /**
     * @depends testAddCustomer
     */
    public function testSetCustomerPrivateNote(int $customerId): int
    {
        $privateNote = 'A private note about this customer';

        // The response is the customer details, read back after the update
        $updatedDetails = $this->partialUpdateItem(
            '/customers/' . $customerId . '/set-private-note',
            ['privateNote' => $privateNote],
            ['customer_write']
        );
        $this->assertSame($customerId, $updatedDetails['customerId']);
        $this->assertSame($privateNote, $updatedDetails['generalInformation']['privateNote']);
        // The input field is not part of the output, the note is only read back in generalInformation
        $this->assertArrayNotHasKey('privateNote', $updatedDetails);

        $details = $this->getItem('/customers/' . $customerId . '/details', ['customer_read']);
        $this->assertSame($updatedDetails, $details);

        return $customerId;
    }

    public function testSetCustomerPrivateNoteNotFound(): void
    {
        $this->partialUpdateItem('/customers/99999/set-private-note', ['privateNote' => 'A note'], ['customer_write'], Response::HTTP_NOT_FOUND);
    }

    /**
     * @depends testAddCustomer
     */
    public function testInvalidCustomerPrivateNote(int $customerId): void
    {
        $expectedErrors = [
            [
                'propertyPath' => 'privateNote',
                'message' => 'This value should not be null.',
            ],
        ];

        // Omitting the field and sending an explicit null must both end up in the same 422. The
        // empty object is sent as a raw body: an empty $data array would send no body at all.
        $invalidBodies = [
            'omitted privateNote' => '{}',
            'null privateNote' => '{"privateNote":null}',
        ];
        foreach ($invalidBodies as $invalidBody) {
            $validationErrorsResponse = $this->partialUpdateItem(
                '/customers/' . $customerId . '/set-private-note',
                null,
                ['customer_write'],
                Response::HTTP_UNPROCESSABLE_ENTITY,
                ['body' => $invalidBody]
            );
            $this->assertIsArray($validationErrorsResponse);
            $this->assertValidationErrors($expectedErrors, $validationErrorsResponse);
        }

        // Same CleanHtml rule as the note form of the back office
        $validationErrorsResponse = $this->partialUpdateItem(
            '/customers/' . $customerId . '/set-private-note',
            ['privateNote' => '<script>alert(1)</script>'],
            ['customer_write'],
            Response::HTTP_UNPROCESSABLE_ENTITY
        );
        $this->assertIsArray($validationErrorsResponse);
        $this->assertValidationErrors([['propertyPath' => 'privateNote']], $validationErrorsResponse);
    }

    /**
     * @depends testAddCustomer
     */
    public function testGetCustomerOrders(int $customerId): void
    {
        $response = $this->getItem('/customers/' . $customerId . '/orders', ['customer_read']);

        // The query returns a list, so the endpoint is a collection: a freshly created customer
        // has no orders yet, hence an empty list
        $this->assertSame([], $response);
    }

    public function testGetCustomerOrdersNotFound(): void
    {
        $this->getItem('/customers/99999/orders', ['customer_read'], Response::HTTP_NOT_FOUND);
    }

    public function testGetCustomerOrdersInvalidCustomerId(): void
    {
        // 0 matches the \d+ requirement but is rejected by the CustomerId value object
        $this->getItem('/customers/0/orders', ['customer_read'], Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /**
     * @depends testSetCustomerPrivateNote
     */
    public function testGetCustomerDetails(int $customerId): void
    {
        $customer = $this->getItem('/customers/' . $customerId . '/details', ['customer_read']);

        // Dates and age are formatted for display and depend on when the test runs
        $personalInformation = $customer['personalInformation'];
        $this->assertMatchesRegularExpression('#^\d{2}/\d{2}/\d{4} \d{2}:\d{2}:\d{2}$#', $personalInformation['registrationDate']);
        $this->assertMatchesRegularExpression('#^\d{2}/\d{2}/\d{4} \d{2}:\d{2}:\d{2}$#', $personalInformation['lastUpdateDate']);
        $this->assertMatchesRegularExpression('#^\d+ years old \(birth date: 01/15/1990\)$#', $personalInformation['birthday']);

        $this->assertEquals([
            'customerId' => $customerId,
            'personalInformation' => [
                'firstName' => 'Jane',
                'lastName' => 'Doe',
                'email' => 'jane.doe@example.com',
                'guest' => false,
                'socialTitle' => 'Mr.',
                'birthday' => $personalInformation['birthday'],
                'registrationDate' => $personalInformation['registrationDate'],
                'lastUpdateDate' => $personalInformation['lastUpdateDate'],
                'lastVisitDate' => 'Never',
                'rankBySales' => null,
                'shopName' => 'test_shop',
                'languageName' => 'English (English)',
                'subscriptions' => [
                    'newsletterSubscribed' => false,
                    'partnerOffersSubscribed' => true,
                ],
                'enabled' => true,
            ],
            'ordersInformation' => [
                'validOrders' => [],
                'invalidOrders' => [],
                'totalSpent' => '$0.00',
            ],
            'messagesInformation' => [],
            'sentEmailsInformation' => [],
            'lastConnectionsInformation' => [],
            'groupsInformation' => [
                [
                    'groupId' => 3,
                    'name' => 'Customer',
                    'default' => true,
                ],
            ],
            'generalInformation' => [
                'privateNote' => 'A private note about this customer',
                // The customer itself is found by its email
                'customerBySameEmailExists' => true,
            ],
        ], $customer);
    }

    /**
     * @depends testAddCustomerWithMinimalData
     */
    public function testGetMinimalCustomerDetails(int $customerId): void
    {
        $customer = $this->getItem('/customers/' . $customerId . '/details', ['customer_read']);

        // Dates are formatted for display and depend on when the test runs
        $personalInformation = $customer['personalInformation'];
        $this->assertMatchesRegularExpression('#^\d{2}/\d{2}/\d{4} \d{2}:\d{2}:\d{2}$#', $personalInformation['registrationDate']);
        $this->assertMatchesRegularExpression('#^\d{2}/\d{2}/\d{4} \d{2}:\d{2}:\d{2}$#', $personalInformation['lastUpdateDate']);

        $this->assertEquals([
            'customerId' => $customerId,
            'personalInformation' => [
                'firstName' => 'Minimal',
                'lastName' => 'Customer',
                'email' => 'minimal.customer@example.com',
                'guest' => false,
                'socialTitle' => 'Unknown',
                'birthday' => 'Unknown',
                'registrationDate' => $personalInformation['registrationDate'],
                'lastUpdateDate' => $personalInformation['lastUpdateDate'],
                'lastVisitDate' => 'Never',
                'rankBySales' => null,
                'shopName' => 'test_shop',
                'languageName' => 'English (English)',
                'subscriptions' => [
                    'newsletterSubscribed' => false,
                    'partnerOffersSubscribed' => false,
                ],
                'enabled' => true,
            ],
            'ordersInformation' => [
                'validOrders' => [],
                'invalidOrders' => [],
                'totalSpent' => '$0.00',
            ],
            'messagesInformation' => [],
            'sentEmailsInformation' => [],
            'lastConnectionsInformation' => [],
            'groupsInformation' => [
                [
                    'groupId' => 3,
                    'name' => 'Customer',
                    'default' => true,
                ],
            ],
            'generalInformation' => [
                // No private note set yet
                'privateNote' => null,
                'customerBySameEmailExists' => true,
            ],
        ], $customer);
    }

    /**
     * @depends testAddCustomer
     */
    public function testCustomerDetailsTotalSpentInDefaultCurrency(int $customerId): void
    {
        // Formatted with the context currency before PrestaShop 9.2.1
        $this->markTestSkippedByMinVersion('9.2.1');

        // @todo: create the currency with the API once a currency endpoint exists
        $currency = new \Currency();
        $currency->iso_code = 'EUR';
        $currency->numeric_iso_code = '978';
        $currency->precision = 2;
        $currency->conversion_rate = 0.9;
        $currency->name = [1 => 'Euro'];
        $currency->symbol = [1 => '€'];
        $currency->active = true;
        $currency->add();

        try {
            $customer = $this->getItem('/customers/' . $customerId . '/details?currencyId=' . $currency->id, ['customer_read']);
            // The orders total is converted to the default currency, whatever the context currency is
            $this->assertEquals('$0.00', $customer['ordersInformation']['totalSpent']);
        } finally {
            DatabaseDump::restoreTables(['currency', 'currency_lang', 'currency_shop']);
        }
    }

    public function testGetCustomerDetailsOrders(): void
    {
        // The fixture customer has orders, none of them is valid
        $customer = $this->getItem('/customers/1/details', ['customer_read']);
        $this->assertEquals([
            'validOrders' => [],
            'invalidOrders' => [
                ['orderId' => 1, 'valid' => false, 'totalPaidTaxIncl' => 68.2, 'conversionRate' => 1],
                ['orderId' => 2, 'valid' => false, 'totalPaidTaxIncl' => 169.9, 'conversionRate' => 1],
                ['orderId' => 3, 'valid' => false, 'totalPaidTaxIncl' => 21.3, 'conversionRate' => 1],
                ['orderId' => 4, 'valid' => false, 'totalPaidTaxIncl' => 21.3, 'conversionRate' => 1],
                ['orderId' => 5, 'valid' => false, 'totalPaidTaxIncl' => 27.3, 'conversionRate' => 1],
            ],
            'totalSpent' => '$0.00',
        ], $customer['ordersInformation']);
    }

    public function testGetCustomerNotFound(): void
    {
        // Try to get a non-existent customer
        $this->requestApi('GET', '/customers/99999', null, ['customer_read'], Response::HTTP_NOT_FOUND);
    }

    public function testGetCustomerDetailsNotFound(): void
    {
        // Try to get a non-existent customer details
        $this->requestApi('GET', '/customers/99999/details', null, ['customer_read'], Response::HTTP_NOT_FOUND);
    }

    public function testDeleteCustomer(): void
    {
        // First, create a customer to delete
        $postData = [
            'firstName' => 'ToDelete',
            'lastName' => 'Customer',
            'email' => 'todelete@example.com',
            'password' => 'TestPassword123!',
            'defaultGroupId' => 3,
            'groupIds' => [3],
        ];

        $customer = $this->createItem('/customers', $postData, ['customer_write']);
        $customerId = $customer['customerId'];

        // Delete with allow_registration_after method
        $deleteData = [
            'deleteMethod' => 'allow_registration_after',
        ];
        $this->requestApi('DELETE', '/customers/' . $customerId, $deleteData, ['customer_write'], Response::HTTP_NO_CONTENT);

        // Verify that the customer is deleted from the database (GET should return 404)
        $this->requestApi('GET', '/customers/' . $customerId, null, ['customer_read'], Response::HTTP_NOT_FOUND);
    }

    public function testDeleteCustomerWithDenyRegistration(): void
    {
        // First, create a customer to delete
        $postData = [
            'firstName' => 'ToDeleteDeny',
            'lastName' => 'Customer',
            'email' => 'todeletedeny@example.com',
            'password' => 'TestPassword123!',
            'defaultGroupId' => 3,
            'groupIds' => [3],
        ];

        $customer = $this->createItem('/customers', $postData, ['customer_write']);
        $customerId = $customer['customerId'];

        // Delete with deny_registration_after method
        $deleteData = [
            'deleteMethod' => 'deny_registration_after',
        ];
        $this->requestApi('DELETE', '/customers/' . $customerId, $deleteData, ['customer_write'], Response::HTTP_NO_CONTENT);

        // After deletion, verify that the customer still exists in the database but with deleted = 1
        $db = $this->getContainer()->get('doctrine.dbal.default_connection');
        $deletedValue = $db->fetchOne('SELECT deleted FROM ps_customer WHERE id_customer = ?', [$customerId]);
        $this->assertEquals('1', (string) $deletedValue, 'Customer should be soft deleted (deleted = 1)');
    }

    public function testDeleteCustomerNotFound(): void
    {
        // Try to delete a non-existent customer
        $deleteData = [
            'deleteMethod' => 'allow_registration_after',
        ];
        $this->requestApi('DELETE', '/customers/99999', $deleteData, ['customer_write'], Response::HTTP_NOT_FOUND);
    }

    public function testDeleteCustomerInvalidMethod(): void
    {
        // First, create a customer
        $postData = [
            'firstName' => 'ToDeleteInvalid',
            'lastName' => 'Customer',
            'email' => 'todeleteinvalid@example.com',
            'password' => 'TestPassword123!',
            'defaultGroupId' => 3,
            'groupIds' => [3],
        ];

        $customer = $this->createItem('/customers', $postData, ['customer_write']);
        $customerId = $customer['customerId'];

        // Try to delete with invalid method
        $deleteData = [
            'deleteMethod' => 'invalid_method',
        ];
        $this->requestApi('DELETE', '/customers/' . $customerId, $deleteData, ['customer_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testBulkDeleteCustomers(): void
    {
        // Create multiple customers for bulk delete
        $firstNames = ['BulkDeleteOne', 'BulkDeleteTwo', 'BulkDeleteThree'];
        $customers = [];
        foreach ($firstNames as $index => $firstName) {
            $postData = [
                'firstName' => $firstName,
                'lastName' => 'Customer',
                'email' => 'bulkdelete' . ($index + 1) . '@example.com',
                'password' => 'TestPassword123!',
                'defaultGroupId' => 3,
                'groupIds' => [3],
            ];
            $customer = $this->createItem('/customers', $postData, ['customer_write']);
            $customers[] = $customer['customerId'];
        }

        // Bulk delete with allow_registration_after method
        $bulkDeleteData = [
            'customerIds' => $customers,
            'deleteMethod' => 'allow_registration_after',
        ];
        $this->bulkDeleteItems('/customers/bulk-delete', $bulkDeleteData, ['customer_write']);

        // Verify customers were deleted (check in database)
        $db = $this->getContainer()->get('doctrine.dbal.default_connection');
        foreach ($customers as $customerId) {
            $customerExists = $db->fetchOne('SELECT COUNT(*) FROM ps_customer WHERE id_customer = ?', [$customerId]);
            $this->assertEquals('0', (string) $customerExists, 'Customer should be deleted from database');
        }
    }

    public function testBulkDeleteCustomersWithDenyRegistration(): void
    {
        // Create multiple customers for bulk delete
        $firstNames = ['BulkDeleteDenyOne', 'BulkDeleteDenyTwo'];
        $customers = [];
        foreach ($firstNames as $index => $firstName) {
            $postData = [
                'firstName' => $firstName,
                'lastName' => 'Customer',
                'email' => 'bulkdeletedeny' . ($index + 1) . '@example.com',
                'password' => 'TestPassword123!',
                'defaultGroupId' => 3,
                'groupIds' => [3],
            ];
            $customer = $this->createItem('/customers', $postData, ['customer_write']);
            $customers[] = $customer['customerId'];
        }

        // Bulk delete with deny_registration_after method
        $bulkDeleteData = [
            'customerIds' => $customers,
            'deleteMethod' => 'deny_registration_after',
        ];
        $this->bulkDeleteItems('/customers/bulk-delete', $bulkDeleteData, ['customer_write']);

        // Verify customers are soft deleted (deleted = 1)
        $db = $this->getContainer()->get('doctrine.dbal.default_connection');
        foreach ($customers as $customerId) {
            $deletedValue = $db->fetchOne('SELECT deleted FROM ps_customer WHERE id_customer = ?', [$customerId]);
            $this->assertEquals('1', (string) $deletedValue, 'Customer should be soft deleted (deleted = 1)');
        }
    }

    /**
     * @todo: previously the default value was forced by the Processor that built the command with default value
     *        This processor was removed because overkill, the BulkDeleteCustomers has a default value but it is not
     *        used during the serialization of the command, we need to find a solution to re-implement this behaviour,
     *        ideally from the module side to avoid an update on the core (which implies a major version of the module
     *        only compatible with 9.1), but if it's not possible only from module then the core will need to evolve
     */
    /*public function testBulkDeleteCustomersWithDefaultMethod(): void
    {
        // Create multiple customers for bulk delete
        $firstNames = ['BulkDeleteDefaultOne', 'BulkDeleteDefaultTwo'];
        $customers = [];
        foreach ($firstNames as $index => $firstName) {
            $postData = [
                'firstName' => $firstName,
                'lastName' => 'Customer',
                'email' => 'bulkdeletedefault' . ($index + 1) . '@example.com',
                'password' => 'TestPassword123!',
                'defaultGroupId' => 3,
                'groupIds' => [3],
            ];
            $customer = $this->createItem('/customers', $postData, ['customer_write']);
            $customers[] = $customer['customerId'];
        }

        // Bulk delete without deleteMethod (should use default allow_registration_after)
        $bulkDeleteData = [
            'customerIds' => $customers,
        ];
        $this->bulkDeleteItems('/customers/bulk-delete', $bulkDeleteData, ['customer_write']);

        // Verify customers were deleted (check in database)
        $db = $this->getContainer()->get('doctrine.dbal.default_connection');
        foreach ($customers as $customerId) {
            $customerExists = $db->fetchOne('SELECT COUNT(*) FROM ps_customer WHERE id_customer = ?', [$customerId]);
            $this->assertEquals('0', (string) $customerExists, 'Customer should be deleted from database');
        }
    }*/

    public function testBulkDeleteCustomersValidationErrors(): void
    {
        // Test with empty customerIds - should return 422 validation error
        $bulkDeleteData = [
            'customerIds' => [],
        ];
        $this->bulkDeleteItems('/customers/bulk-delete', $bulkDeleteData, ['customer_write'], Response::HTTP_UNPROCESSABLE_ENTITY);

        // Test with invalid deleteMethod - should return 422 validation error
        $bulkDeleteData = [
            'customerIds' => [1, 2],
            'deleteMethod' => 'invalid_method',
        ];
        $this->bulkDeleteItems('/customers/bulk-delete', $bulkDeleteData, ['customer_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testBulkDeleteCustomersNotFound(): void
    {
        // Try to bulk delete non-existent customers
        $bulkDeleteData = [
            'customerIds' => [99999, 99998],
            'deleteMethod' => 'allow_registration_after',
        ];
        $this->bulkDeleteItems('/customers/bulk-delete', $bulkDeleteData, ['customer_write'], Response::HTTP_NOT_FOUND);
    }

    public function testBulkDisableCustomers(): void
    {
        // Create multiple customers for bulk disable
        $firstNames = ['BulkDisableOne', 'BulkDisableTwo', 'BulkDisableThree'];
        $customers = [];
        foreach ($firstNames as $index => $firstName) {
            $postData = [
                'firstName' => $firstName,
                'lastName' => 'Customer',
                'email' => 'bulkdisable' . ($index + 1) . '@example.com',
                'password' => 'TestPassword123!',
                'defaultGroupId' => 3,
                'groupIds' => [3],
                'enabled' => true,
            ];
            $customer = $this->createItem('/customers', $postData, ['customer_write']);
            $customers[] = $customer['customerId'];
        }

        // Bulk disable
        $bulkDisableData = [
            'customerIds' => $customers,
        ];
        $this->updateItem('/customers/bulk-disable', $bulkDisableData, ['customer_write'], Response::HTTP_NO_CONTENT);

        // Verify customers were disabled (check in database)
        $db = $this->getContainer()->get('doctrine.dbal.default_connection');
        foreach ($customers as $customerId) {
            $activeValue = $db->fetchOne('SELECT active FROM ps_customer WHERE id_customer = ?', [$customerId]);
            $this->assertEquals('0', (string) $activeValue, 'Customer should be disabled (active = 0)');
        }
    }

    public function testBulkEnableCustomers(): void
    {
        // Create multiple customers for bulk enable
        $firstNames = ['BulkEnableOne', 'BulkEnableTwo', 'BulkEnableThree'];
        $customers = [];
        foreach ($firstNames as $index => $firstName) {
            $postData = [
                'firstName' => $firstName,
                'lastName' => 'Customer',
                'email' => 'bulkenable' . ($index + 1) . '@example.com',
                'password' => 'TestPassword123!',
                'defaultGroupId' => 3,
                'groupIds' => [3],
                'enabled' => false,
            ];
            $customer = $this->createItem('/customers', $postData, ['customer_write']);
            $customers[] = $customer['customerId'];
        }

        // Bulk enable
        $bulkEnableData = [
            'customerIds' => $customers,
        ];
        $this->updateItem('/customers/bulk-enable', $bulkEnableData, ['customer_write'], Response::HTTP_NO_CONTENT);

        // Verify customers were enabled (check in database)
        $db = $this->getContainer()->get('doctrine.dbal.default_connection');
        foreach ($customers as $customerId) {
            $activeValue = $db->fetchOne('SELECT active FROM ps_customer WHERE id_customer = ?', [$customerId]);
            $this->assertEquals('1', (string) $activeValue, 'Customer should be enabled (active = 1)');
        }
    }

    public function testBulkDisableCustomersValidationErrors(): void
    {
        // Test with empty customerIds - should return 422 validation error
        $bulkDisableData = [
            'customerIds' => [],
        ];
        $this->updateItem('/customers/bulk-disable', $bulkDisableData, ['customer_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testBulkEnableCustomersValidationErrors(): void
    {
        // Test with empty customerIds - should return 422 validation error
        $bulkEnableData = [
            'customerIds' => [],
        ];
        $this->updateItem('/customers/bulk-enable', $bulkEnableData, ['customer_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testBulkDisableCustomersNotFound(): void
    {
        // Try to bulk disable non-existent customers
        $bulkDisableData = [
            'customerIds' => [99999, 99998],
        ];
        $this->updateItem('/customers/bulk-disable', $bulkDisableData, ['customer_write'], Response::HTTP_NOT_FOUND);
    }

    public function testBulkEnableCustomersNotFound(): void
    {
        // Try to bulk enable non-existent customers
        $bulkEnableData = [
            'customerIds' => [99999, 99998],
        ];
        $this->updateItem('/customers/bulk-enable', $bulkEnableData, ['customer_write'], Response::HTTP_NOT_FOUND);
    }

    public function testSearchCustomers(): void
    {
        // First, create a customer to search for
        $postData = [
            'firstName' => 'Search',
            'lastName' => 'Test',
            'email' => 'search.test@example.com',
            'password' => 'TestPassword123!',
            'defaultGroupId' => 3,
            'groupIds' => [3],
            'enabled' => true,
        ];

        $customer = $this->createItem('/customers', $postData, ['customer_write']);
        $customerId = $customer['customerId'];

        // Search for the customer by first name
        $searchResults = $this->getItem('/customers/search?phrases[]=Search', ['customer_read']);

        $this->assertIsArray($searchResults);
        $this->assertNotEmpty($searchResults);
        // Older cores keep the query result indexed by customer id
        if (self::isVersionAtLeast('9.2.1')) {
            $this->assertTrue(array_is_list($searchResults));
        }

        // Find our customer in the results
        $foundCustomer = null;
        foreach ($searchResults as $result) {
            if ($result['customerId'] === $customerId) {
                $foundCustomer = $result;
                break;
            }
        }

        $this->assertNotNull($foundCustomer, 'Created customer should be found in search results');
        $this->assertEquals('Search', $foundCustomer['firstName']);
        $this->assertEquals('Test', $foundCustomer['lastName']);
        $this->assertTrue($foundCustomer['enabled']);
        $this->assertEquals('search.test@example.com', $foundCustomer['email']);
        $this->assertArrayHasKey('fullnameAndEmail', $foundCustomer);
        $this->assertSame(3, $foundCustomer['defaultGroupId']);
        $this->assertSame([['groupId' => 3, 'name' => 'Customer', 'default' => true]], $foundCustomer['groups']);
    }

    public function testSearchCustomersByEmail(): void
    {
        // Create a customer
        $postData = [
            'firstName' => 'Email',
            'lastName' => 'Search',
            'email' => 'email.search@example.com',
            'password' => 'TestPassword123!',
            'defaultGroupId' => 3,
            'groupIds' => [3],
            'enabled' => true,
        ];

        $customer = $this->createItem('/customers', $postData, ['customer_write']);
        $customerId = $customer['customerId'];

        // Search by email
        $searchResults = $this->getItem('/customers/search?phrases[]=email.search@example.com', ['customer_read']);

        $this->assertIsArray($searchResults);
        $this->assertNotEmpty($searchResults);

        $foundCustomer = null;
        foreach ($searchResults as $result) {
            if ($result['customerId'] === $customerId) {
                $foundCustomer = $result;
                break;
            }
        }

        $this->assertNotNull($foundCustomer, 'Customer should be found by email');
        $this->assertEquals('email.search@example.com', $foundCustomer['email']);
    }

    public function testSearchCustomersEmptyPhrases(): void
    {
        // Try to search with empty phrases (should return 422 validation error)
        $this->requestApi('GET', '/customers/search', null, ['customer_read'], Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testSearchCustomersMultiplePhrases(): void
    {
        // Create two customers
        $postData1 = [
            'firstName' => 'Multi',
            'lastName' => 'One',
            'email' => 'multi.one@example.com',
            'password' => 'TestPassword123!',
            'defaultGroupId' => 3,
            'groupIds' => [3],
            'enabled' => true,
        ];

        $postData2 = [
            'firstName' => 'Multi',
            'lastName' => 'Two',
            'email' => 'multi.two@example.com',
            'password' => 'TestPassword123!',
            'defaultGroupId' => 3,
            'groupIds' => [3],
            'enabled' => true,
        ];

        $customer1 = $this->createItem('/customers', $postData1, ['customer_write']);
        $customer2 = $this->createItem('/customers', $postData2, ['customer_write']);

        // Search with multiple phrases
        $searchResults = $this->getItem('/customers/search?phrases[]=Multi&phrases[]=One', ['customer_read']);

        $this->assertIsArray($searchResults);
        $this->assertNotEmpty($searchResults);

        // Both customers should be found (they both match "Multi")
        $foundIds = array_column($searchResults, 'customerId');
        $this->assertContains($customer1['customerId'], $foundIds);
        $this->assertContains($customer2['customerId'], $foundIds);
    }

    public function testPartialUpdateCustomer(): int
    {
        // First, create a customer to update
        $postData = [
            'firstName' => 'John',
            'lastName' => 'Doe',
            'email' => 'john.doe@example.com',
            'password' => 'TestPassword123!',
            'defaultGroupId' => 3,
            'groupIds' => [3],
            'genderId' => 1,
            'enabled' => true,
            'partnerOffersSubscribed' => false,
            'birthday' => '1990-01-15',
            'guest' => false,
        ];

        $customer = $this->createItem('/customers', $postData, ['customer_write']);
        $customerId = $customer['customerId'];

        // Update only firstName
        $updatedCustomer = $this->partialUpdateItem('/customers/' . $customerId, [
            'firstName' => 'Jane',
        ], ['customer_write']);

        $this->assertEquals('Jane', $updatedCustomer['firstName']);
        $this->assertEquals('Doe', $updatedCustomer['lastName']);
        $this->assertEquals('john.doe@example.com', $updatedCustomer['email']);
        $this->assertEquals($customerId, $updatedCustomer['customerId']);

        return $customerId;
    }
}
