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
 * The carts of a customer: GET /customers/{customerId}/carts lists the carts that never became an order (PrestaShop
 * 9.2.1 and later), POST /customers/{customerId}/carts creates one (CreateEmptyCustomerCartCommand).
 */
class CustomerCartsEndpointTest extends ApiTestCase
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
            'cart',
            'cart_product',
            'customer',
            'customer_group',
        ]);
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'create customer cart endpoint' => ['POST', '/customers/1/carts'];
        if (self::isVersionAtLeast('9.2.1')) {
            yield 'get customer carts endpoint' => ['GET', '/customers/1/carts'];
        }
    }

    public function testCreateCustomerCart(): void
    {
        $customer = $this->createItem('/customers', [
            'firstName' => 'Jane',
            'lastName' => 'CREATION',
            'email' => 'jane.cart.creation@example.com',
            'password' => 'TestPassword123!',
            'defaultGroupId' => 3,
            'groupIds' => [3],
        ], ['customer_write']);
        $customerId = $customer['customerId'];

        $cart = $this->createItem('/customers/' . $customerId . '/carts', null, ['customer_write']);
        $this->assertSame($customerId, $cart['customerId']);
        $this->assertIsInt($cart['cartId']);

        // The core reuses the last empty cart of the customer instead of creating another one
        $sameCart = $this->createItem('/customers/' . $customerId . '/carts', null, ['customer_write']);
        $this->assertSame($cart['cartId'], $sameCart['cartId']);
    }

    public function testCreateCustomerCartInvalidCustomerId(): void
    {
        // 0 matches the \d+ requirement but is rejected by the CustomerId value object
        $this->createItem('/customers/0/carts', null, ['customer_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testCreateCustomerCartUnknownCustomerNotFound(): void
    {
        // Older cores create a cart with no customer and answer 201, fixed by PrestaShop/PrestaShop#43116
        $expectedHttpCode = self::isVersionAtLeast('9.2.1') ? Response::HTTP_NOT_FOUND : Response::HTTP_CREATED;

        $this->createItem('/customers/999999/carts', null, ['customer_write'], $expectedHttpCode);
    }

    public function testCustomerCartsListTheCartsNeverOrdered(): void
    {
        // Older cores answer 500 on a cart holding products, the listing requires PrestaShop/PrestaShop#43116
        $this->markTestSkippedByMinVersion('9.2.1');

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

        $cart = $this->createItem('/customers/' . $customerId . '/carts', null, ['customer_write']);

        // The cart never became an order, so the query lists it
        $carts = $this->getItem('/customers/' . $customerId . '/carts', ['customer_read']);
        $this->assertCount(1, $carts);
        $this->assertSame($customerId, $carts[0]['customerId']);
        $this->assertSame($cart['cartId'], $carts[0]['cartId']);
        $this->assertIsString($carts[0]['creationDate']);
        $this->assertIsString($carts[0]['totalPrice']);
    }

    public function testCustomerCartsWithProducts(): void
    {
        // Older cores answer 500 on a cart holding products, the listing requires PrestaShop/PrestaShop#43116
        $this->markTestSkippedByMinVersion('9.2.1');

        $customer = $this->createItem('/customers', [
            'firstName' => 'John',
            'lastName' => 'CARTS',
            'email' => 'john.carts@example.com',
            'password' => 'TestPassword123!',
            'defaultGroupId' => 3,
            'groupIds' => [3],
        ], ['customer_write']);
        $customerId = $customer['customerId'];
        $cartId = $this->createItem('/customers/' . $customerId . '/carts', null, ['customer_write'])['cartId'];

        // @todo Add the products through the cart endpoints once they are available (PrestaShop/ps_apiresources#201)
        $cart = new \Cart($cartId);
        $cart->updateQty(2, 2, 9);

        // Before PrestaShop/PrestaShop#43116 the total of a cart holding products was computed without a context
        // cart nor employee, and answered 500
        $carts = $this->getItem('/customers/' . $customerId . '/carts', ['customer_read']);
        $this->assertCount(1, $carts);
        $this->assertSame($cartId, $carts[0]['cartId']);
        // Two discounted Hummingbird printed sweaters with the test shop prices
        $this->assertSame('$64.44', $carts[0]['totalPrice']);
    }

    public function testCustomerCartsInvalidCustomerId(): void
    {
        // Older cores answer 500 on a cart holding products, the listing requires PrestaShop/PrestaShop#43116
        $this->markTestSkippedByMinVersion('9.2.1');

        // 0 matches the \d+ requirement but is rejected by the CustomerId value object
        $this->getItem('/customers/0/carts', ['customer_read'], Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testCustomerCartsUnknownCustomerNotFound(): void
    {
        // Older cores answer 500 on a cart holding products, the listing requires PrestaShop/PrestaShop#43116
        $this->markTestSkippedByMinVersion('9.2.1');

        $this->getItem('/customers/999999/carts', ['customer_read'], Response::HTTP_NOT_FOUND);
    }
}
