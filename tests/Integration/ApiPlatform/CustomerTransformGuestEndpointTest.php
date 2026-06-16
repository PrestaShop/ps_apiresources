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

class CustomerTransformGuestEndpointTest extends ApiTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::resetTables();
        self::createApiClient(['customer_write', 'customer_read']);
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
        yield 'transform endpoint' => ['PUT', '/customers/1/transform-to-customers'];
    }

    private function createGuest(): int
    {
        $guest = $this->createItem('/customers', [
            'firstName' => 'Jane',
            'lastName' => 'GUEST',
            'email' => 'jane.transform@example.com',
            'password' => 'INVALID',
            'genderId' => 2,
            'guest' => true,
            'defaultGroupId' => 1,
            'groupIds' => [1],
            'enabled' => false,
        ], ['customer_write']);

        $this->assertTrue($guest['guest']);

        return $guest['customerId'];
    }

    public function testTransformGuestToCustomer(): int
    {
        $customerId = $this->createGuest();

        $this->updateItem(
            '/customers/' . $customerId . '/transform-to-customers',
            [],
            ['customer_write'],
            Response::HTTP_NO_CONTENT
        );

        // The guest is now a registered customer
        $customer = $this->getItem('/customers/' . $customerId, ['customer_read']);
        $this->assertFalse($customer['guest']);

        return $customerId;
    }

    /**
     * @depends testTransformGuestToCustomer
     */
    public function testTransformAlreadyRegisteredCustomerFails(int $customerId): void
    {
        // Transforming a customer that is no longer a guest is rejected
        $this->updateItem(
            '/customers/' . $customerId . '/transform-to-customers',
            [],
            ['customer_write'],
            Response::HTTP_UNPROCESSABLE_ENTITY
        );
    }
}
