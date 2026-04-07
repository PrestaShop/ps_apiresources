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
        // Cart.php endpoints
        yield 'get cart endpoint' => [
            'GET',
            '/carts/1',
        ];

        yield 'create cart endpoint' => [
            'POST',
            '/carts',
        ];

        yield 'delete cart endpoint' => [
            'DELETE',
            '/carts/1',
        ];

        // BulkCarts.php endpoints
        yield 'bulk delete carts endpoint' => [
            'DELETE',
            '/carts/bulk-delete',
        ];

        // CartCartRule.php endpoints
        yield 'add cart rule to cart endpoint' => [
            'POST',
            '/carts/1/cart-rules',
        ];

        yield 'remove cart rule from cart endpoint' => [
            'DELETE',
            '/carts/1/cart-rules',
        ];

        // CartCustomization.php endpoints
        yield 'add customization to cart endpoint' => [
            'POST',
            '/carts/1/customizations',
        ];

        // CartForOrderCreation.php endpoints
        yield 'get cart for order creation endpoint' => [
            'GET',
            '/carts/1/order-creations',
        ];

        // CartProduct.php endpoints
        yield 'add product to cart endpoint' => [
            'POST',
            '/carts/1/products',
        ];

        yield 'remove product from cart endpoint' => [
            'DELETE',
            '/carts/1/products',
        ];

        yield 'update product quantity in cart endpoint' => [
            'PUT',
            '/carts/1/products/quantities',
        ];

        // CartProductPrice.php endpoints
        yield 'update product price in cart endpoint' => [
            'PUT',
            '/carts/1/products/1/prices',
        ];

        // CartSettings.php endpoints
        yield 'update cart addresses endpoint' => [
            'PUT',
            '/carts/1/addresses',
        ];

        yield 'update cart carrier endpoint' => [
            'PUT',
            '/carts/1/carriers',
        ];

        yield 'update cart currency endpoint' => [
            'PUT',
            '/carts/1/currencies',
        ];

        yield 'update cart language endpoint' => [
            'PUT',
            '/carts/1/languages',
        ];

        yield 'update cart delivery settings endpoint' => [
            'PUT',
            '/carts/1/delivery-settings',
        ];

        // CustomerLastEmptyCart.php endpoints
        yield 'get customer last empty cart endpoint' => [
            'GET',
            '/carts/1/last-empty-carts',
        ];
    }

    // ========================================
    // SETUP: Create required test data
    // ========================================

    /**
     * First we need to create a customer to associate with carts.
     */
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
            'countryId' => 8, // France
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

        // Create a simple cart rule (discount) for testing cart rules endpoints
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

    // ========================================
    // CART CRUD TESTS
    // ========================================

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

        $this->assertArrayHasKey('cartId', $cart);
        $this->assertEquals($cartId, $cart['cartId']);
        $this->assertArrayHasKey('customerInformation', $cart);
        $this->assertArrayHasKey('cartSummary', $cart);

        return $cartId;
    }

    /**
     * @depends testGetCart
     */
    public function testGetCartForOrderCreation(int $cartId): int
    {
        $cart = $this->getItem('/carts/' . $cartId . '/order-creations', ['cart_read']);

        $this->assertArrayHasKey('cartId', $cart);
        $this->assertEquals($cartId, $cart['cartId']);
        $this->assertArrayHasKey('products', $cart);
        $this->assertArrayHasKey('summary', $cart);

        return $cartId;
    }

    /**
     * @depends testGetCartForOrderCreation
     */
    public function testGetCustomerLastEmptyCart(int $cartId): int
    {
        $result = $this->getItem('/carts/' . self::$testCustomerId . '/last-empty-carts', ['cart_read']);

        $this->assertArrayHasKey('cartId', $result);
        $this->assertEquals($cartId, $result['cartId']);

        return $cartId;
    }

    // ========================================
    // CART PRODUCT TESTS
    // ========================================

    /**
     * @depends testGetCustomerLastEmptyCart
     */
    public function testAddProductToCart(int $cartId): int
    {
        // Product ID 1 exists in default fixtures
        $this->createItem('/carts/' . $cartId . '/products', [
            'productId' => 1,
            'quantity' => 2,
        ], ['cart_write'], Response::HTTP_NO_CONTENT);

        // Verify product was added by getting the cart
        $cart = $this->getItem('/carts/' . $cartId, ['cart_read']);
        $this->assertArrayHasKey('cartSummary', $cart);

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

        return $cartId;
    }

    /**
     * @depends testUpdateProductQuantityInCart
     */
    public function testUpdateProductPriceInCart(int $cartId): int
    {
        // Update price for product 1, combination 0 (no combination)
        $this->updateItem('/carts/' . $cartId . '/products/1/prices', [
            'combinationId' => 0,
            'price' => '15.99',
        ], ['cart_write'], Response::HTTP_NO_CONTENT);

        return $cartId;
    }

    // ========================================
    // CART SETTINGS TESTS
    // ========================================

    /**
     * @depends testUpdateProductPriceInCart
     */
    public function testUpdateCartAddresses(int $cartId): int
    {
        $this->updateItem('/carts/' . $cartId . '/addresses', [
            'deliveryAddressId' => self::$testAddressId,
            'invoiceAddressId' => self::$testAddressId,
        ], ['cart_write'], Response::HTTP_NO_CONTENT);

        return $cartId;
    }

    /**
     * @depends testUpdateCartAddresses
     */
    public function testUpdateCartCarrier(int $cartId): int
    {
        // Carrier ID 1 exists in default fixtures
        $this->updateItem('/carts/' . $cartId . '/carriers', [
            'carrierId' => 1,
        ], ['cart_write'], Response::HTTP_NO_CONTENT);

        return $cartId;
    }

    /**
     * @depends testUpdateCartCarrier
     */
    public function testUpdateCartCurrency(int $cartId): int
    {
        // Currency ID 1 (EUR) exists in default fixtures
        $this->updateItem('/carts/' . $cartId . '/currencies', [
            'currencyId' => 1,
        ], ['cart_write'], Response::HTTP_NO_CONTENT);

        return $cartId;
    }

    /**
     * @depends testUpdateCartCurrency
     */
    public function testUpdateCartLanguage(int $cartId): int
    {
        // Language ID 1 (en-US) exists in default fixtures
        $this->updateItem('/carts/' . $cartId . '/languages', [
            'languageId' => 1,
        ], ['cart_write'], Response::HTTP_NO_CONTENT);

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

        return $cartId;
    }

    // ========================================
    // CLEANUP TESTS
    // ========================================

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

        // Verify cart was deleted
        $this->getItem('/carts/' . $cartId, ['cart_read'], Response::HTTP_NOT_FOUND);
    }

    // ========================================
    // BULK OPERATIONS TESTS
    // ========================================

    /**
     * @depends testDeleteCart
     */
    public function testBulkDeleteCarts(): void
    {
        // Create multiple carts for bulk delete test
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

        // Verify carts were deleted
        foreach ($cartIds as $cartId) {
            $this->getItem('/carts/' . $cartId, ['cart_read'], Response::HTTP_NOT_FOUND);
        }
    }

    // ========================================
    // CART RULES TESTS
    // ========================================

    /**
     * @depends testBulkDeleteCarts
     */
    public function testCartRulesOperations(): void
    {
        if (!self::$discountApiAvailable || self::$testCartRuleId === null) {
            $this->markTestSkipped('Discount API not available, skipping cart rules tests');
        }

        // Create a cart for cart rules testing
        $cart = $this->createItem('/carts', [
            'customerId' => self::$testCustomerId,
        ], ['cart_write']);
        $cartId = $cart['cartId'];

        // Add cart rule to cart
        $this->createItem('/carts/' . $cartId . '/cart-rules', [
            'cartRuleId' => self::$testCartRuleId,
        ], ['cart_write'], Response::HTTP_NO_CONTENT);

        // Remove cart rule from cart
        $this->bulkDeleteItems('/carts/' . $cartId . '/cart-rules', [
            'cartRuleId' => self::$testCartRuleId,
        ], ['cart_write']);

        // Clean up
        $this->deleteItem('/carts/' . $cartId, ['cart_write']);
    }

    // ========================================
    // ERROR HANDLING TESTS
    // ========================================

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
        // First create a valid cart
        $cart = $this->createItem('/carts', [
            'customerId' => self::$testCustomerId,
        ], ['cart_write']);

        // Try to add non-existent product
        $this->createItem('/carts/' . $cart['cartId'] . '/products', [
            'productId' => 999999,
            'quantity' => 1,
        ], ['cart_write'], Response::HTTP_NOT_FOUND);

        // Clean up
        $this->deleteItem('/carts/' . $cart['cartId'], ['cart_write']);
    }

    public function testGetCustomerLastEmptyCartForNonExistentCustomer(): void
    {
        $this->getItem('/carts/999999/last-empty-carts', ['cart_read'], Response::HTTP_NOT_FOUND);
    }

    public function testUpdateCartWithInvalidCarrier(): void
    {
        // First create a valid cart
        $cart = $this->createItem('/carts', [
            'customerId' => self::$testCustomerId,
        ], ['cart_write']);

        // Try to update with non-existent carrier
        $this->updateItem('/carts/' . $cart['cartId'] . '/carriers', [
            'carrierId' => 999999,
        ], ['cart_write'], Response::HTTP_NOT_FOUND);

        // Clean up
        $this->deleteItem('/carts/' . $cart['cartId'], ['cart_write']);
    }

    public function testUpdateCartWithInvalidCurrency(): void
    {
        // First create a valid cart
        $cart = $this->createItem('/carts', [
            'customerId' => self::$testCustomerId,
        ], ['cart_write']);

        // Try to update with non-existent currency
        $this->updateItem('/carts/' . $cart['cartId'] . '/currencies', [
            'currencyId' => 999999,
        ], ['cart_write'], Response::HTTP_NOT_FOUND);

        // Clean up
        $this->deleteItem('/carts/' . $cart['cartId'], ['cart_write']);
    }

    public function testUpdateCartWithInvalidLanguage(): void
    {
        // First create a valid cart
        $cart = $this->createItem('/carts', [
            'customerId' => self::$testCustomerId,
        ], ['cart_write']);

        // Try to update with non-existent language
        $this->updateItem('/carts/' . $cart['cartId'] . '/languages', [
            'languageId' => 999999,
        ], ['cart_write'], Response::HTTP_NOT_FOUND);

        // Clean up
        $this->deleteItem('/carts/' . $cart['cartId'], ['cart_write']);
    }

    // ========================================
    // CUSTOMIZATION TESTS
    // Note: These tests require products with customization fields
    // which may not be available in default fixtures.
    // ========================================

    /**
     * Test adding customization to cart.
     * This test is skipped by default as it requires a product with customization fields.
     * To enable this test, ensure you have a product with customization fields in fixtures.
     */
    public function testAddCustomizationToCart(): void
    {
        // Skip this test as it requires specific product setup with customization fields
        // To test this endpoint manually:
        // 1. Create/find a product with customization fields
        // 2. Create a cart
        // 3. POST to /carts/{cartId}/customizations with:
        //    - productId: the product ID
        //    - customizationValuesByFieldIds: array mapping field IDs to values
        $this->markTestSkipped(
            'Customization test requires a product with customization fields. ' .
            'Enable this test when proper fixtures are available.'
        );
    }
}