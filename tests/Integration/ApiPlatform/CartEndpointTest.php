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

class CartEndpointTest extends ApiTestCase
{
    private static int $originalMailMethod;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::createApiClient(['cart_read', 'cart_write', 'order_write']);

        // The process-order email is sent for real otherwise
        self::$originalMailMethod = (int) \Configuration::get('PS_MAIL_METHOD');
        \Configuration::updateValue('PS_MAIL_METHOD', \Mail::METHOD_DISABLE);
    }

    public static function tearDownAfterClass(): void
    {
        \Configuration::updateValue('PS_MAIL_METHOD', self::$originalMailMethod);

        parent::tearDownAfterClass();
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'get cart details endpoint' => ['GET', '/carts/1/details'];
        yield 'update cart currency endpoint' => ['PUT', '/carts/1/currencies'];
        yield 'update cart language endpoint' => ['PUT', '/carts/1/languages'];
        yield 'add product to cart endpoint' => ['PUT', '/carts/1/products'];
        yield 'update product quantity endpoint' => ['PUT', '/carts/1/products/1'];
        yield 'remove product from cart endpoint' => ['DELETE', '/carts/1/products/1'];
        yield 'send process order email endpoint' => ['PUT', '/carts/1/process-order-emails'];
    }

    /**
     * Carts are created by the front office and by the order-creation flow: the Cart domain
     * exposes no "add cart" command, so this is the one fixture these tests cannot get from
     * the API. It used to be copied in five test classes; everything the tests assert goes
     * back through GET /carts/{cartId}/details.
     */
    private function createCart(?int $customerId = null): int
    {
        $cart = new \Cart();
        $cart->id_customer = $customerId ?? 0;
        $cart->id_currency = (int) \Configuration::get('PS_CURRENCY_DEFAULT');
        $cart->id_lang = (int) \Configuration::get('PS_LANG_DEFAULT');
        $cart->id_shop = 1;
        $cart->add();

        return (int) $cart->id;
    }

    /**
     * A lookup, not a fixture: the cart needs a product that is active, in stock and has no
     * combinations, which the products listing cannot express as a filter. Nothing is created
     * here.
     */
    private function getSimpleInStockProductId(): int
    {
        return (int) \Db::getInstance()->getValue(
            'SELECT sa.`id_product` FROM `' . _DB_PREFIX_ . 'stock_available` sa
             INNER JOIN `' . _DB_PREFIX_ . 'product` p ON p.`id_product` = sa.`id_product`
             WHERE sa.`quantity` > 0 AND sa.`id_product_attribute` = 0 AND p.`active` = 1
             AND sa.`id_product` NOT IN (SELECT `id_product` FROM `' . _DB_PREFIX_ . 'product_attribute`)
             ORDER BY sa.`id_product` ASC'
        );
    }

    private function getCartDetails(int $cartId): array
    {
        return $this->getItem('/carts/' . $cartId . '/details', ['cart_read']);
    }

    /**
     * @return array<int, int> product id => quantity in the cart
     */
    private function getCartProductQuantities(int $cartId): array
    {
        $quantities = [];
        foreach ($this->getCartDetails($cartId)['cartSummary']['products'] as $product) {
            $quantities[(int) $product['id']] = (int) $product['cart_quantity'];
        }

        return $quantities;
    }

    public function testGetCartDetails(): void
    {
        $cartId = $this->createCart();

        $cart = $this->getCartDetails($cartId);

        $this->assertEquals(
            ['cartId', 'cartCurrencyId', 'customerInformation', 'orderInformation', 'cartSummary'],
            array_keys($cart)
        );
        $this->assertSame($cartId, $cart['cartId']);
        $this->assertSame([], $cart['cartSummary']['products']);
    }

    public function testGetNonExistentCartDetails(): void
    {
        $this->requestApi('GET', '/carts/999999/details', null, ['cart_read'], Response::HTTP_NOT_FOUND);
    }

    /**
     * Adding, re-quantifying and removing a product are chained through the API. The removal
     * test used to seed its cart line with an INSERT INTO ps_cart_product "so the test does
     * not depend on the add-product flow" — in a domain PR, depending on it is the point.
     */
    public function testCartProductLifecycle(): void
    {
        $cartId = $this->createCart();
        $productId = $this->getSimpleInStockProductId();

        $this->updateItem(
            '/carts/' . $cartId . '/products',
            ['productId' => $productId, 'quantity' => 2],
            ['cart_write'],
            Response::HTTP_NO_CONTENT
        );
        $this->assertSame([$productId => 2], $this->getCartProductQuantities($cartId));

        $this->updateItem(
            '/carts/' . $cartId . '/products/' . $productId,
            ['quantity' => 5],
            ['cart_write'],
            Response::HTTP_NO_CONTENT
        );
        $this->assertSame([$productId => 5], $this->getCartProductQuantities($cartId));

        $this->requestApi(
            'DELETE',
            '/carts/' . $cartId . '/products/' . $productId,
            null,
            ['cart_write'],
            Response::HTTP_NO_CONTENT
        );
        $this->assertSame([], $this->getCartProductQuantities($cartId));
    }

    public function testUpdateCartCurrency(): void
    {
        $cartId = $this->createCart();
        $currencyId = (int) $this->getCartDetails($cartId)['cartCurrencyId'];

        // A lookup, not a fixture: the Currency domain has no listing endpoint on dev, so
        // another installed currency is looked up directly. Nothing is created here.
        $otherCurrencyId = (int) \Db::getInstance()->getValue(
            'SELECT `id_currency` FROM `' . _DB_PREFIX_ . 'currency`
             WHERE `deleted` = 0 AND `id_currency` <> ' . $currencyId . ' ORDER BY `id_currency` ASC'
        );
        if (0 === $otherCurrencyId) {
            $this->markTestSkipped('The fixtures only install one currency.');
        }

        $this->updateItem(
            '/carts/' . $cartId . '/currencies',
            ['newCurrencyId' => $otherCurrencyId],
            ['cart_write'],
            Response::HTTP_NO_CONTENT
        );

        // Asserted through the API instead of (new \Cart($cartId))->id_currency
        $this->assertSame($otherCurrencyId, (int) $this->getCartDetails($cartId)['cartCurrencyId']);
    }

    public function testUpdateCartLanguage(): void
    {
        $cartId = $this->createCart();

        // The cart view does not expose the language, so this one only asserts the command
        // is accepted; the language round-trip has no read side in GetCartForViewing.
        $return = $this->updateItem(
            '/carts/' . $cartId . '/languages',
            ['newLanguageId' => (int) \Configuration::get('PS_LANG_DEFAULT')],
            ['cart_write'],
            Response::HTTP_NO_CONTENT
        );
        $this->assertNull($return);
    }

    public function testSendProcessOrderEmail(): void
    {
        $customerId = (int) \Db::getInstance()->getValue(
            'SELECT `id_customer` FROM `' . _DB_PREFIX_ . 'customer` WHERE `active` = 1 ORDER BY `id_customer` ASC'
        );
        $cartId = $this->createCart($customerId);

        $return = $this->requestApi(
            'PUT',
            '/carts/' . $cartId . '/process-order-emails',
            null,
            ['order_write'],
            Response::HTTP_NO_CONTENT
        );
        $this->assertNull($return);
    }

    public function testSendProcessOrderEmailWithInvalidCartIdReturns422(): void
    {
        // cartId=0 passes the URI \d+ requirement but new CartId(0) throws
        // CartConstraintException — must surface as 422, not 500.
        $this->requestApi(
            'PUT',
            '/carts/0/process-order-emails',
            null,
            ['order_write'],
            Response::HTTP_UNPROCESSABLE_ENTITY
        );
    }
}
