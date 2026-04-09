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

use PrestaShop\PrestaShop\Core\Domain\Discount\Command\AddDiscountCommand;
use Symfony\Component\HttpFoundation\Response;
use Tests\Resources\DatabaseDump;

class CartEndpointTest extends ApiTestCase
{
    protected static int $testCustomerId;
    protected static int $testAddressId;
    protected static ?int $testCartRuleId = null;
    protected static bool $discountApiAvailable = false;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::$discountApiAvailable = class_exists(AddDiscountCommand::class);

        $scopes = [
            'cart_read',
            'cart_write',
            'customer_read',
            'customer_write',
            'address_read',
            'address_write',
        ];

        if (self::$discountApiAvailable) {
            $scopes[] = 'discount_read';
            $scopes[] = 'discount_write';
        }

        self::createApiClient($scopes);
        self::resetTables();
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
            'cart_cart_rule',
            'customer',
            'address',
        ]);
        DatabaseDump::restoreMatchingTables('cart_rule*');
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'get cart endpoint' => ['GET', '/carts/1'];
        yield 'create cart endpoint' => ['POST', '/carts'];
        yield 'delete cart endpoint' => ['DELETE', '/carts/1'];
        yield 'bulk delete carts endpoint' => ['DELETE', '/carts/bulk-delete'];
        yield 'add cart rule to cart endpoint' => ['POST', '/carts/1/cart-rules'];
        yield 'remove cart rule from cart endpoint' => ['DELETE', '/carts/1/cart-rules'];
        yield 'add customization to cart endpoint' => ['POST', '/carts/1/customizations'];
        yield 'get cart for order creation endpoint' => ['GET', '/carts/1/order-creations'];
        yield 'add product to cart endpoint' => ['POST', '/carts/1/products'];
        yield 'remove product from cart endpoint' => ['DELETE', '/carts/1/products'];
        yield 'update product quantity in cart endpoint' => ['PUT', '/carts/1/products/quantities'];
        yield 'update product price in cart endpoint' => ['PUT', '/carts/1/products/1/prices'];
        yield 'update cart addresses endpoint' => ['PUT', '/carts/1/addresses'];
        yield 'update cart carrier endpoint' => ['PUT', '/carts/1/carriers'];
        yield 'update cart currency endpoint' => ['PUT', '/carts/1/currencies'];
        yield 'update cart language endpoint' => ['PUT', '/carts/1/languages'];
        yield 'update cart delivery settings endpoint' => ['PUT', '/carts/1/delivery-settings'];
        yield 'get customer last empty cart endpoint' => ['GET', '/carts/1/last-empty-carts'];
    }

    public function testCreateCustomerForCart(): int
    {
        $customer = $this->createItem('/customers', [
            'firstName' => 'Cart',
            'lastName' => 'TestUser',
            'email' => 'cart.testuser@example.com',
            'password' => 'password123',
            'defaultGroupId' => 3,
            'groupIds' => [3],
        ], ['customer_write']);

        $this->assertArrayHasKey('customerId', $customer);
        self::$testCustomerId = $customer['customerId'];

        return $customer['customerId'];
    }

    /**
     * @depends testCreateCustomerForCart
     */
    public function testCreateAddressForCustomer(int $customerId): int
    {
        $address = $this->createItem('/addresses/customers', [
            'customerId' => $customerId,
            'addressAlias' => 'Test Address',
            'firstName' => 'Cart',
            'lastName' => 'TestUser',
            'address' => '123 Test Street',
            'city' => 'Paris',
            'postCode' => '75001',
            'countryId' => 8,
            'stateId' => 0,
        ], ['address_write']);

        $this->assertArrayHasKey('addressId', $address);
        self::$testAddressId = $address['addressId'];

        return $customerId;
    }

    /**
     * @depends testCreateAddressForCustomer
     */
    public function testCreateCartRuleForTests(int $customerId): int
    {
        if (!self::$discountApiAvailable) {
            $this->markTestSkipped('Discount API not available');
        }

        $cartRule = $this->createItem('/discounts', [
            'type' => 'cart_level',
            'names' => [
                'en-US' => 'Test Cart Rule',
            ],
            'reductionPercent' => 10.0,
            'enabled' => true,
        ], ['discount_write']);

        $this->assertArrayHasKey('discountId', $cartRule);
        self::$testCartRuleId = $cartRule['discountId'];

        return $customerId;
    }

    /**
     * @depends testCreateAddressForCustomer
     */
    public function testCreateCart(int $customerId): int
    {
        $cart = $this->createItem('/carts', [
            'customerId' => $customerId,
        ], ['cart_write']);

        $this->assertArrayHasKey('cartId', $cart);

        return $cart['cartId'];
    }

    /**
     * @depends testCreateCart
     */
    public function testGetCart(int $cartId): int
    {
        $cart = $this->getItem('/carts/' . $cartId, ['cart_read']);

        $expectedCart = [
            'cartId' => $cartId,
            'customerId' => self::$testCustomerId,
            'currencyId' => $cart['currencyId'],
            'customerInformation' => $cart['customerInformation'],
            'orderInformation' => $cart['orderInformation'],
            'cartSummary' => $cart['cartSummary'],
        ];
        $this->assertEquals($expectedCart, $cart);

        $this->assertEquals($expectedCart, $this->getItem('/carts/' . $cartId, ['cart_read']));

        return $cartId;
    }

    /**
     * @depends testGetCart
     */
    public function testGetCartForOrderCreation(int $cartId): int
    {
        $cart = $this->getItem('/carts/' . $cartId . '/order-creations', ['cart_read']);

        $expectedCart = [
            'cartId' => $cartId,
            'products' => $cart['products'],
            'currencyId' => $cart['currencyId'],
            'langId' => $cart['langId'],
            'cartRules' => $cart['cartRules'],
            'addresses' => $cart['addresses'],
            'summary' => $cart['summary'],
            'shipping' => $cart['shipping'],
        ];
        $this->assertEquals($expectedCart, $cart);

        $this->assertEquals($expectedCart, $this->getItem('/carts/' . $cartId . '/order-creations', ['cart_read']));

        return $cartId;
    }

    /**
     * @depends testGetCartForOrderCreation
     */
    public function testGetCustomerLastEmptyCart(int $cartId): int
    {
        $result = $this->getItem('/carts/' . self::$testCustomerId . '/last-empty-carts', ['cart_read']);

        $this->assertEquals($cartId, $result['cartId']);

        return $cartId;
    }

    /**
     * @depends testGetCustomerLastEmptyCart
     */
    public function testAddProductToCart(int $cartId): int
    {
        $this->createItem('/carts/' . $cartId . '/products', [
            'productId' => 1,
            'quantity' => 2,
        ], ['cart_write'], Response::HTTP_NO_CONTENT);

        $cart = $this->getItem('/carts/' . $cartId . '/order-creations', ['cart_read']);
        $this->assertNotEmpty($cart['products'], 'Cart should contain products after adding one');

        return $cartId;
    }

    /**
     * @depends testAddProductToCart
     */
    public function testUpdateProductQuantityInCart(int $cartId): int
    {
        $this->updateItem('/carts/' . $cartId . '/products/quantities', [
            'productId' => 1,
            'quantity' => 5,
        ], ['cart_write'], Response::HTTP_NO_CONTENT);

        $cart = $this->getItem('/carts/' . $cartId . '/order-creations', ['cart_read']);
        $this->assertNotEmpty($cart['products'], 'Cart should still contain products after quantity update');

        return $cartId;
    }

    /**
     * @depends testUpdateProductQuantityInCart
     */
    public function testUpdateProductPriceInCart(int $cartId): int
    {
        $this->updateItem('/carts/' . $cartId . '/products/1/prices', [
            'combinationId' => 0,
            'price' => '15.99',
        ], ['cart_write'], Response::HTTP_NO_CONTENT);

        $cart = $this->getItem('/carts/' . $cartId . '/order-creations', ['cart_read']);
        $this->assertNotEmpty($cart['products'], 'Cart should still contain products after price update');

        return $cartId;
    }

    /**
     * @depends testUpdateProductPriceInCart
     */
    public function testUpdateCartAddresses(int $cartId): int
    {
        $this->updateItem('/carts/' . $cartId . '/addresses', [
            'deliveryAddressId' => self::$testAddressId,
            'invoiceAddressId' => self::$testAddressId,
        ], ['cart_write'], Response::HTTP_NO_CONTENT);

        $cart = $this->getItem('/carts/' . $cartId . '/order-creations', ['cart_read']);
        $this->assertNotEmpty($cart['addresses'], 'Cart should have addresses after update');

        return $cartId;
    }

    /**
     * @depends testUpdateCartAddresses
     */
    public function testUpdateCartCarrier(int $cartId): int
    {
        $this->updateItem('/carts/' . $cartId . '/carriers', [
            'carrierId' => 1,
        ], ['cart_write'], Response::HTTP_NO_CONTENT);

        $cart = $this->getItem('/carts/' . $cartId . '/order-creations', ['cart_read']);
        $this->assertArrayHasKey('shipping', $cart);

        return $cartId;
    }

    /**
     * @depends testUpdateCartCarrier
     */
    public function testUpdateCartCurrency(int $cartId): int
    {
        $this->updateItem('/carts/' . $cartId . '/currencies', [
            'currencyId' => 1,
        ], ['cart_write'], Response::HTTP_NO_CONTENT);

        $cart = $this->getItem('/carts/' . $cartId, ['cart_read']);
        $this->assertEquals(1, $cart['currencyId']);

        return $cartId;
    }

    /**
     * @depends testUpdateCartCurrency
     */
    public function testUpdateCartLanguage(int $cartId): int
    {
        $this->updateItem('/carts/' . $cartId . '/languages', [
            'languageId' => 1,
        ], ['cart_write'], Response::HTTP_NO_CONTENT);

        $cart = $this->getItem('/carts/' . $cartId . '/order-creations', ['cart_read']);
        $this->assertEquals(1, $cart['langId']);

        return $cartId;
    }

    /**
     * @depends testUpdateCartLanguage
     */
    public function testUpdateCartDeliverySettings(int $cartId): int
    {
        $this->updateItem('/carts/' . $cartId . '/delivery-settings', [
            'allowFreeShipping' => true,
            'isAGift' => false,
            'useRecycledPackaging' => false,
            'giftMessage' => '',
        ], ['cart_write'], Response::HTTP_NO_CONTENT);

        $cart = $this->getItem('/carts/' . $cartId, ['cart_read']);
        $this->assertEquals($cartId, $cart['cartId']);

        return $cartId;
    }

    /**
     * @depends testUpdateCartDeliverySettings
     */
    public function testRemoveProductFromCart(int $cartId): int
    {
        $this->bulkDeleteItems('/carts/' . $cartId . '/products', [
            'productId' => 1,
        ], ['cart_write']);

        return $cartId;
    }

    /**
     * @depends testRemoveProductFromCart
     */
    public function testDeleteCart(int $cartId): void
    {
        $result = $this->deleteItem('/carts/' . $cartId, ['cart_write']);
        $this->assertNull($result);

        $this->getItem('/carts/' . $cartId, ['cart_read'], Response::HTTP_NOT_FOUND);
    }

    /**
     * @depends testDeleteCart
     */
    public function testBulkDeleteCarts(): void
    {
        $cart1 = $this->createItem('/carts', [
            'customerId' => self::$testCustomerId,
        ], ['cart_write']);

        $cart2 = $this->createItem('/carts', [
            'customerId' => self::$testCustomerId,
        ], ['cart_write']);

        $cartIds = [$cart1['cartId'], $cart2['cartId']];

        $this->bulkDeleteItems('/carts/bulk-delete', [
            'cartIds' => $cartIds,
        ], ['cart_write']);

        foreach ($cartIds as $cartId) {
            $this->getItem('/carts/' . $cartId, ['cart_read'], Response::HTTP_NOT_FOUND);
        }
    }

    /**
     * @depends testBulkDeleteCarts
     */
    public function testCartRulesOperations(): void
    {
        if (!self::$discountApiAvailable || null === self::$testCartRuleId) {
            $this->markTestSkipped('Discount API not available, skipping cart rules tests');
        }

        $cart = $this->createItem('/carts', [
            'customerId' => self::$testCustomerId,
        ], ['cart_write']);
        $cartId = $cart['cartId'];

        $this->createItem('/carts/' . $cartId . '/cart-rules', [
            'cartRuleId' => self::$testCartRuleId,
        ], ['cart_write'], Response::HTTP_NO_CONTENT);

        $this->bulkDeleteItems('/carts/' . $cartId . '/cart-rules', [
            'cartRuleId' => self::$testCartRuleId,
        ], ['cart_write']);

        $this->deleteItem('/carts/' . $cartId, ['cart_write']);
    }

    public function testCreateCartWithInvalidCustomer(): void
    {
        $this->createItem('/carts', [
            'customerId' => 999999,
        ], ['cart_write'], Response::HTTP_NOT_FOUND);
    }

    public function testGetNonExistentCart(): void
    {
        $this->getItem('/carts/999999', ['cart_read'], Response::HTTP_NOT_FOUND);
    }

    public function testDeleteNonExistentCart(): void
    {
        $this->deleteItem('/carts/999999', ['cart_write'], Response::HTTP_NOT_FOUND);
    }

    public function testAddProductToNonExistentCart(): void
    {
        $this->createItem('/carts/999999/products', [
            'productId' => 1,
            'quantity' => 1,
        ], ['cart_write'], Response::HTTP_NOT_FOUND);
    }

    public function testAddNonExistentProductToCart(): void
    {
        $cart = $this->createItem('/carts', [
            'customerId' => self::$testCustomerId,
        ], ['cart_write']);

        $this->createItem('/carts/' . $cart['cartId'] . '/products', [
            'productId' => 999999,
            'quantity' => 1,
        ], ['cart_write'], Response::HTTP_NOT_FOUND);

        $this->deleteItem('/carts/' . $cart['cartId'], ['cart_write']);
    }

    public function testGetCustomerLastEmptyCartForNonExistentCustomer(): void
    {
        $this->getItem('/carts/999999/last-empty-carts', ['cart_read'], Response::HTTP_NOT_FOUND);
    }

    public function testUpdateCartWithInvalidCarrier(): void
    {
        $cart = $this->createItem('/carts', [
            'customerId' => self::$testCustomerId,
        ], ['cart_write']);

        $this->updateItem('/carts/' . $cart['cartId'] . '/carriers', [
            'carrierId' => 999999,
        ], ['cart_write'], Response::HTTP_NOT_FOUND);

        $this->deleteItem('/carts/' . $cart['cartId'], ['cart_write']);
    }

    public function testUpdateCartWithInvalidCurrency(): void
    {
        $cart = $this->createItem('/carts', [
            'customerId' => self::$testCustomerId,
        ], ['cart_write']);

        $this->updateItem('/carts/' . $cart['cartId'] . '/currencies', [
            'currencyId' => 999999,
        ], ['cart_write'], Response::HTTP_NOT_FOUND);

        $this->deleteItem('/carts/' . $cart['cartId'], ['cart_write']);
    }

    public function testUpdateCartWithInvalidLanguage(): void
    {
        $cart = $this->createItem('/carts', [
            'customerId' => self::$testCustomerId,
        ], ['cart_write']);

        $this->updateItem('/carts/' . $cart['cartId'] . '/languages', [
            'languageId' => 999999,
        ], ['cart_write'], Response::HTTP_NOT_FOUND);

        $this->deleteItem('/carts/' . $cart['cartId'], ['cart_write']);
    }

    /**
     * Customization test requires a product with customization fields in fixtures.
     */
    public function testAddCustomizationToCart(): void
    {
        $this->markTestSkipped(
            'Customization test requires a product with customization fields. ' .
            'Enable this test when proper fixtures are available.'
        );
    }
}
