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

use Tests\Resources\Resetter\CustomerResetter;

class CartEndpointTest extends ApiTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        CustomerResetter::resetCustomers();
        // Pre-create the API Client with the needed scopes
        self::createApiClient(['cart_write', 'cart_read']);
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        CustomerResetter::resetCustomers();
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'get cart list endpoint' => [
            'GET',
            '/cart',
        ];

        yield 'get cart endpoint' => [
            'GET',
            '/cart/1',
        ];

        yield 'create cart endpoint' => [
            'POST',
            '/cart',
        ];

        yield 'add product to cart endpoint' => [
            'PATCH',
            '/cart/1/products',
        ];
    }

    public function testCreateEmptyCart(): int
    {
        $createdCart = $this->createItem('/cart', [
            'customerId' => 1,
        ], ['cart_write']);

        $this->assertArrayHasKey('cartId', $createdCart);
        $this->assertArrayHasKey('customerId', $createdCart);
        $this->assertEquals(1, $createdCart['customerId']);

        $cartId = $createdCart['cartId'];
        $this->assertGreaterThan(0, $cartId);

        return $cartId;
    }

    /**
     * @depends testCreateEmptyCart
     */
    public function testGetCart(int $cartId): int
    {
        $cart = $this->getItem('/cart/' . $cartId, ['cart_read']);

        $this->assertArrayHasKey('cartId', $cart);
        $this->assertArrayHasKey('customerId', $cart);
        $this->assertArrayHasKey('products', $cart);
        $this->assertArrayHasKey('totals', $cart);

        $this->assertEquals($cartId, $cart['cartId']);
        $this->assertEquals(1, $cart['customerId']);
        $this->assertIsArray($cart['products']);

        return $cartId;
    }

    /**
     * @depends testGetCart
     */
    public function testAddProductToCart(int $cartId): int
    {
        $productData = [
            'productId' => 1,
            'quantity' => 2,
        ];

        $updatedCart = $this->partialUpdateItem('/cart/' . $cartId . '/products', $productData, ['cart_write']);

        $this->assertArrayHasKey('cartId', $updatedCart);
        $this->assertArrayHasKey('products', $updatedCart);
        $this->assertEquals($cartId, $updatedCart['cartId']);
        $this->assertIsArray($updatedCart['products']);

        // Verify the cart contains the added product
        $cart = $this->getItem('/cart/' . $cartId, ['cart_read']);
        $this->assertNotEmpty($cart['products']);

        return $cartId;
    }

    /**
     * @depends testAddProductToCart
     */
    public function testAddProductWithCombinationToCart(int $cartId): int
    {
        $productData = [
            'productId' => 2,
            'quantity' => 1,
            'combinationId' => 9,
        ];

        $updatedCart = $this->partialUpdateItem('/cart/' . $cartId . '/products', $productData, ['cart_write']);

        $this->assertArrayHasKey('cartId', $updatedCart);
        $this->assertEquals($cartId, $updatedCart['cartId']);

        return $cartId;
    }

    /**
     * @depends testAddProductWithCombinationToCart
     */
    public function testCartExistsAfterProductAddition(int $cartId): void
    {
        // Verify that the cart still exists and can be retrieved after adding products
        $cart = $this->getItem('/cart/' . $cartId, ['cart_read']);

        $this->assertArrayHasKey('cartId', $cart);
        $this->assertEquals($cartId, $cart['cartId']);
        $this->assertArrayHasKey('customerId', $cart);
        $this->assertArrayHasKey('dateAdd', $cart);
        $this->assertArrayHasKey('totalProducts', $cart);

        // Cart should have been updated with product addition
        $this->assertIsArray($cart);
        $this->assertArrayHasKey('totalProductsTaxIncl', $cart);
    }

    public function testCreateCartWithoutCustomer(): void
    {
        // Test creating cart without customerId should fail
        $response = $this->createItem('/cart', [], ['cart_write'], 422);

        $this->assertArrayHasKey('violations', $response);
        $this->assertNotEmpty($response['violations']);
    }

    public function testGetNonExistentCart(): void
    {
        $this->getItem('/cart/99999', ['cart_read'], 404);
    }

    public function testAddInvalidProductToCart(): void
    {
        // First create a cart
        $createdCart = $this->createItem('/cart', [
            'customerId' => 1,
        ], ['cart_write']);

        $cartId = $createdCart['cartId'];

        // Try to add non-existent product
        $productData = [
            'productId' => 99999,
            'quantity' => 1,
        ];

        $this->partialUpdateItem('/cart/' . $cartId . '/products', $productData, ['cart_write'], 422);
    }
}
