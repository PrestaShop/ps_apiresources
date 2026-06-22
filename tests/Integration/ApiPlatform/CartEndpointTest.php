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

class CartEndpointTest extends ApiTestCase
{
    // Fixture customer ID that always exists in the test DB
    private const FIXTURE_CUSTOMER_ID = 1;
    // Fixture product ID that always exists in the test DB
    private const FIXTURE_PRODUCT_ID = 1;
    // Fixture address ID that always exists in the test DB
    private const FIXTURE_ADDRESS_ID = 1;
    // Default currency ID (Euro) in the test DB
    private const FIXTURE_CURRENCY_ID = 1;
    // Default language ID in the test DB
    private const FIXTURE_LANGUAGE_ID = 1;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::resetTables();
        self::createApiClient(['cart_read', 'cart_write']);
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
            'customization',
            'customized_data',
        ]);
    }

    public static function getProtectedEndpoints(): iterable
    {
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

        yield 'get cart view endpoint' => [
            'GET',
            '/carts/1/view',
        ];

        yield 'add product to cart endpoint' => [
            'POST',
            '/carts/1/products',
        ];

        yield 'remove product from cart endpoint' => [
            'DELETE',
            '/carts/1/products/1',
        ];

        yield 'update product quantity endpoint' => [
            'PATCH',
            '/carts/1/products/quantity',
        ];

        yield 'update product price endpoint' => [
            'PATCH',
            '/carts/1/products/price',
        ];

        yield 'add cart rule endpoint' => [
            'POST',
            '/carts/1/cart-rules',
        ];

        yield 'remove cart rule endpoint' => [
            'DELETE',
            '/carts/1/cart-rules',
        ];

        yield 'add customization endpoint' => [
            'POST',
            '/carts/1/customizations',
        ];

        yield 'update cart addresses endpoint' => [
            'PATCH',
            '/carts/1/addresses',
        ];

        yield 'update cart carrier endpoint' => [
            'PATCH',
            '/carts/1/carrier',
        ];

        yield 'update cart currency endpoint' => [
            'PATCH',
            '/carts/1/currency',
        ];

        yield 'update cart delivery settings endpoint' => [
            'PATCH',
            '/carts/1/delivery-settings',
        ];

        yield 'update cart language endpoint' => [
            'PATCH',
            '/carts/1/language',
        ];

        yield 'bulk delete carts endpoint' => [
            'DELETE',
            '/carts/bulk-delete',
        ];

        yield 'get last empty customer cart endpoint' => [
            'GET',
            '/carts/last-empty/1',
        ];
    }

    public function testCreateCart(): int
    {
        $cart = $this->createItem('/carts', ['customerId' => self::FIXTURE_CUSTOMER_ID], ['cart_write']);

        $this->assertArrayHasKey('cartId', $cart);
        $cartId = $cart['cartId'];
        $this->assertIsInt($cartId);
        $this->assertGreaterThan(0, $cartId);

        $this->assertEquals([
            'cartId' => $cartId,
            'customerId' => self::FIXTURE_CUSTOMER_ID,
            'currencyId' => self::FIXTURE_CURRENCY_ID,
            'languageId' => self::FIXTURE_LANGUAGE_ID,
            'products' => [],
            'cartRules' => [],
            'addresses' => $cart['addresses'],
            'shipping' => $cart['shipping'],
            'summary' => $cart['summary'],
        ], $cart);

        return $cartId;
    }

    /**
     * @depends testCreateCart
     */
    public function testGetCart(int $cartId): int
    {
        $cart = $this->getItem('/carts/' . $cartId, ['cart_read']);

        $this->assertEquals([
            'cartId' => $cartId,
            'customerId' => self::FIXTURE_CUSTOMER_ID,
            'currencyId' => self::FIXTURE_CURRENCY_ID,
            'languageId' => self::FIXTURE_LANGUAGE_ID,
            'products' => [],
            'cartRules' => [],
            'addresses' => $cart['addresses'],
            'shipping' => $cart['shipping'],
            'summary' => $cart['summary'],
        ], $cart);

        return $cartId;
    }

    /**
     * @depends testGetCart
     */
    public function testAddProductToCart(int $cartId): int
    {
        $this->createItem('/carts/' . $cartId . '/products', [
            'productId' => self::FIXTURE_PRODUCT_ID,
            'quantity' => 2,
        ], ['cart_write'], Response::HTTP_CREATED);

        // Verify product was added by getting the cart
        $cart = $this->getItem('/carts/' . $cartId, ['cart_read']);
        $this->assertNotEmpty($cart['products']);
        $product = $cart['products'][0];

        $this->assertEquals([
            'productId' => self::FIXTURE_PRODUCT_ID,
            'attributeId' => $product['attributeId'],
            'name' => $product['name'],
            'attribute' => $product['attribute'],
            'reference' => $product['reference'],
            'unitPrice' => $product['unitPrice'],
            'quantity' => 2,
            'price' => $product['price'],
            'imageLink' => $product['imageLink'],
            'customization' => null,
            'availableStock' => $product['availableStock'],
            'availableOutOfStock' => $product['availableOutOfStock'],
            'gift' => false,
        ], $product);

        return $cartId;
    }

    /**
     * @depends testAddProductToCart
     */
    public function testUpdateProductQuantityInCart(int $cartId): int
    {
        $this->partialUpdateItem('/carts/' . $cartId . '/products/quantity', [
            'productId' => self::FIXTURE_PRODUCT_ID,
            'quantity' => 5,
        ], ['cart_write']);

        // Verify quantity was updated
        $cart = $this->getItem('/carts/' . $cartId, ['cart_read']);
        $this->assertNotEmpty($cart['products']);
        $this->assertEquals(5, $cart['products'][0]['quantity']);

        return $cartId;
    }

    /**
     * @depends testUpdateProductQuantityInCart
     */
    public function testRemoveProductFromCart(int $cartId): int
    {
        $result = $this->deleteItem(
            '/carts/' . $cartId . '/products/' . self::FIXTURE_PRODUCT_ID,
            ['cart_write']
        );
        $this->assertNull($result);

        // Verify product was removed
        $cart = $this->getItem('/carts/' . $cartId, ['cart_read']);
        $this->assertEmpty($cart['products']);

        return $cartId;
    }

    /**
     * @depends testRemoveProductFromCart
     */
    public function testUpdateCartAddresses(int $cartId): int
    {
        $this->partialUpdateItem('/carts/' . $cartId . '/addresses', [
            'deliveryAddressId' => self::FIXTURE_ADDRESS_ID,
            'invoiceAddressId' => self::FIXTURE_ADDRESS_ID,
        ], ['cart_write']);

        $cart = $this->getItem('/carts/' . $cartId, ['cart_read']);
        $selectedAddress = array_filter($cart['addresses'], fn($a) => $a['addressId'] === self::FIXTURE_ADDRESS_ID);
        $this->assertNotEmpty($selectedAddress);

        return $cartId;
    }

    /**
     * @depends testUpdateCartAddresses
     */
    public function testUpdateCartCurrency(int $cartId): int
    {
        $cart = $this->getItem('/carts/' . $cartId, ['cart_read']);
        $currencyId = $cart['currencyId'];

        $this->partialUpdateItem('/carts/' . $cartId . '/currency', [
            'currencyId' => $currencyId,
        ], ['cart_write']);

        $updatedCart = $this->getItem('/carts/' . $cartId, ['cart_read']);
        $this->assertEquals($currencyId, $updatedCart['currencyId']);

        return $cartId;
    }

    /**
     * @depends testUpdateCartCurrency
     */
    public function testUpdateCartLanguage(int $cartId): int
    {
        $cart = $this->getItem('/carts/' . $cartId, ['cart_read']);
        $languageId = $cart['languageId'];

        $this->partialUpdateItem('/carts/' . $cartId . '/language', [
            'languageId' => $languageId,
        ], ['cart_write']);

        $updatedCart = $this->getItem('/carts/' . $cartId, ['cart_read']);
        $this->assertEquals($languageId, $updatedCart['languageId']);

        return $cartId;
    }

    /**
     * @depends testUpdateCartLanguage
     */
    public function testUpdateCartDeliverySettings(int $cartId): int
    {
        $this->partialUpdateItem('/carts/' . $cartId . '/delivery-settings', [
            'allowFreeShipping' => false,
            'gift' => false,
            'recycledPackaging' => false,
            'giftMessage' => null,
        ], ['cart_write']);

        $cart = $this->getItem('/carts/' . $cartId, ['cart_read']);
        $this->assertArrayHasKey('shipping', $cart);
        if ($cart['shipping'] !== null) {
            $this->assertFalse($cart['shipping']['gift']);
            $this->assertFalse($cart['shipping']['recycledPackaging']);
        }

        return $cartId;
    }

    /**
     * @depends testUpdateCartDeliverySettings
     */
    public function testDeleteCart(int $cartId): void
    {
        $result = $this->deleteItem('/carts/' . $cartId, ['cart_write']);
        $this->assertNull($result);

        // Verify cart is gone
        $this->getItem('/carts/' . $cartId, ['cart_read'], Response::HTTP_NOT_FOUND);
    }

    public function testGetLastEmptyCustomerCart(): void
    {
        // Create a cart for the fixture customer
        $cart = $this->createItem('/carts', ['customerId' => self::FIXTURE_CUSTOMER_ID], ['cart_write']);
        $cartId = $cart['cartId'];

        // Get the last empty cart for this customer
        $result = $this->getItem('/carts/last-empty/' . self::FIXTURE_CUSTOMER_ID, ['cart_read']);

        $this->assertEquals([
            'customerId' => self::FIXTURE_CUSTOMER_ID,
            'cartId' => $cartId,
        ], $result);

        $this->deleteItem('/carts/' . $cartId, ['cart_write']);
    }

    public function testGetCartForViewing(): void
    {
        // Create a cart first
        $cart = $this->createItem('/carts', ['customerId' => self::FIXTURE_CUSTOMER_ID], ['cart_write']);
        $cartId = $cart['cartId'];

        $cartView = $this->getItem('/carts/' . $cartId . '/view', ['cart_read']);

        $this->assertEquals([
            'cartId' => $cartId,
            'currencyId' => self::FIXTURE_CURRENCY_ID,
            'customerInformation' => $cartView['customerInformation'],
            'orderInformation' => $cartView['orderInformation'],
            'cartSummary' => $cartView['cartSummary'],
        ], $cartView);

        $this->deleteItem('/carts/' . $cartId, ['cart_write']);
    }

    public function testBulkDeleteCarts(): void
    {
        $cart1 = $this->createItem('/carts', ['customerId' => self::FIXTURE_CUSTOMER_ID], ['cart_write']);
        $cart1Id = $cart1['cartId'];

        // Add a product to cart1 so it is no longer empty, forcing a new cart to be created for cart2
        $this->createItem('/carts/' . $cart1Id . '/products', [
            'productId' => self::FIXTURE_PRODUCT_ID,
            'quantity' => 1,
        ], ['cart_write'], Response::HTTP_CREATED);

        $cart2 = $this->createItem('/carts', ['customerId' => self::FIXTURE_CUSTOMER_ID], ['cart_write']);
        $cart2Id = $cart2['cartId'];

        $this->assertNotEquals($cart1Id, $cart2Id);

        $this->bulkDeleteItems('/carts/bulk-delete', ['cartIds' => [$cart1Id, $cart2Id]], ['cart_write']);

        $this->getItem('/carts/' . $cart1Id, ['cart_read'], Response::HTTP_NOT_FOUND);
        $this->getItem('/carts/' . $cart2Id, ['cart_read'], Response::HTTP_NOT_FOUND);
    }

    public function testCreateCartInvalidData(): void
    {
        $validationErrors = $this->createItem('/carts', [
            'customerId' => -1,
        ], ['cart_write'], Response::HTTP_UNPROCESSABLE_ENTITY);

        $this->assertIsArray($validationErrors);
        $this->assertValidationErrors([
            [
                'propertyPath' => 'customerId',
                'message' => 'This value should be positive.',
            ],
        ], $validationErrors);
    }

    public function testAddProductToCartInvalidData(): void
    {
        $validationErrors = $this->createItem('/carts/1/products', [
            'productId' => -1,
            'quantity' => -1,
        ], ['cart_write'], Response::HTTP_UNPROCESSABLE_ENTITY);

        $this->assertIsArray($validationErrors);
        $this->assertValidationErrors([
            [
                'propertyPath' => 'productId',
                'message' => 'This value should be positive.',
            ],
            [
                'propertyPath' => 'quantity',
                'message' => 'This value should be positive.',
            ],
        ], $validationErrors);
    }

    public function testUpdateProductQuantityInvalidData(): void
    {
        $validationErrors = $this->partialUpdateItem('/carts/1/products/quantity', [
            'productId' => -1,
            'quantity' => -1,
        ], ['cart_write'], Response::HTTP_UNPROCESSABLE_ENTITY);

        $this->assertIsArray($validationErrors);
        $this->assertValidationErrors([
            [
                'propertyPath' => 'productId',
                'message' => 'This value should be positive.',
            ],
            [
                'propertyPath' => 'quantity',
                'message' => 'This value should be positive.',
            ],
        ], $validationErrors);
    }

    public function testUpdateCartAddressesInvalidData(): void
    {
        $validationErrors = $this->partialUpdateItem('/carts/1/addresses', [
            'deliveryAddressId' => -1,
            'invoiceAddressId' => -1,
        ], ['cart_write'], Response::HTTP_UNPROCESSABLE_ENTITY);

        $this->assertIsArray($validationErrors);
        $this->assertValidationErrors([
            [
                'propertyPath' => 'deliveryAddressId',
                'message' => 'This value should be positive.',
            ],
            [
                'propertyPath' => 'invoiceAddressId',
                'message' => 'This value should be positive.',
            ],
        ], $validationErrors);
    }

    public function testUpdateCartCarrierInvalidData(): void
    {
        $validationErrors = $this->partialUpdateItem('/carts/1/carrier', [
            'carrierId' => -1,
        ], ['cart_write'], Response::HTTP_UNPROCESSABLE_ENTITY);

        $this->assertIsArray($validationErrors);
        $this->assertValidationErrors([
            [
                'propertyPath' => 'carrierId',
                'message' => 'This value should be positive.',
            ],
        ], $validationErrors);
    }

    public function testUpdateCartCurrencyInvalidData(): void
    {
        $validationErrors = $this->partialUpdateItem('/carts/1/currency', [
            'currencyId' => -1,
        ], ['cart_write'], Response::HTTP_UNPROCESSABLE_ENTITY);

        $this->assertIsArray($validationErrors);
        $this->assertValidationErrors([
            [
                'propertyPath' => 'currencyId',
                'message' => 'This value should be positive.',
            ],
        ], $validationErrors);
    }

    public function testUpdateCartLanguageInvalidData(): void
    {
        $validationErrors = $this->partialUpdateItem('/carts/1/language', [
            'languageId' => -1,
        ], ['cart_write'], Response::HTTP_UNPROCESSABLE_ENTITY);

        $this->assertIsArray($validationErrors);
        $this->assertValidationErrors([
            [
                'propertyPath' => 'languageId',
                'message' => 'This value should be positive.',
            ],
        ], $validationErrors);
    }
}
