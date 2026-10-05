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

/**
 * GET /customers/{customerId}/carts on a customer who really has a cart. The cart is created through
 * POST /carts (CreateEmptyCustomerCartCommand), so this class depends on the Cart resource and shares
 * its version gate.
 */
class CustomerCartsEndpointTest extends ApiTestCase
{
    public static function setUpBeforeClass(): void
    {
        if (self::isVersionUnder('9.2.0')) {
            static::markTestSkipped('POST /carts requires PrestaShop >= 9.2.0, see Cart::VERSION_GATE');

            return;
        }

        parent::setUpBeforeClass();
        self::resetTables();
        self::createApiClient(['cart_write', 'customer_read', 'customer_write']);
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        self::resetTables();
    }

    protected static function resetTables(): void
    {
        DatabaseDump::restoreTables([
            'cart',
            'customer',
            'customer_group',
        ]);
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'get customer carts endpoint' => ['GET', '/customers/1/carts'];
    }

    public function testCustomerCartsListTheCartsNeverOrdered(): void
    {
        $customer = $this->createItem('/customers', [
            'firstName' => 'Jane',
            'lastName' => 'CARTS',
            'email' => 'jane.carts@example.com',
            'password' => 'TestPassword123!',
            'genderId' => 2,
            'defaultGroupId' => 3,
            'groupIds' => [3],
            'enabled' => true,
        ], ['customer_write']);
        $customerId = $customer['customerId'];

        $this->assertSame([], $this->getItem('/customers/' . $customerId . '/carts', ['customer_read']));

        $cart = $this->createItem('/carts', ['customerId' => $customerId], ['cart_write']);

        // The cart never became an order, so the query lists it
        $carts = $this->getItem('/customers/' . $customerId . '/carts', ['customer_read']);
        $this->assertCount(1, $carts);
        $this->assertSame($customerId, $carts[0]['customerId']);
        $this->assertSame($cart['cartId'], $carts[0]['cartId']);
        $this->assertIsString($carts[0]['creationDate']);
        $this->assertIsString($carts[0]['totalPrice']);
    }

    public function testCustomerCartsUnknownCustomerNotFound(): void
    {
        $this->getItem('/customers/999999/carts', ['customer_read'], Response::HTTP_NOT_FOUND);
    }
}
