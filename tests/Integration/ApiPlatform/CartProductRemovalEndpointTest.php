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

class CartProductRemovalEndpointTest extends ApiTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::createApiClient(['cart_write']);
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'remove product from cart endpoint' => ['DELETE', '/carts/1/products/1'];
    }

    public function testRemoveProductFromCart(): void
    {
        $cartId = $this->createCart();
        $productId = (int) \Db::getInstance()->getValue(
            'SELECT `id_product` FROM `' . _DB_PREFIX_ . 'product` WHERE `active` = 1 ORDER BY `id_product` ASC'
        );

        // Seed the cart line directly so the test does not depend on the add-product flow.
        \Db::getInstance()->insert('cart_product', [
            'id_cart' => $cartId,
            'id_product' => $productId,
            'id_product_attribute' => 0,
            'id_customization' => 0,
            'id_address_delivery' => 0,
            'id_shop' => 1,
            'quantity' => 1,
            'date_add' => '2024-01-01 00:00:00',
        ]);

        $this->requestApi(
            'DELETE',
            '/carts/' . $cartId . '/products/' . $productId,
            null,
            ['cart_write'],
            Response::HTTP_NO_CONTENT
        );

        $remaining = (int) \Db::getInstance()->getValue(
            'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'cart_product`
             WHERE `id_cart` = ' . $cartId . ' AND `id_product` = ' . $productId
        );
        $this->assertSame(0, $remaining);
    }

    private function createCart(): int
    {
        $cart = new \Cart();
        $cart->id_currency = (int) \Configuration::get('PS_CURRENCY_DEFAULT');
        $cart->id_lang = (int) \Configuration::get('PS_LANG_DEFAULT');
        $cart->id_shop = 1;
        $cart->add();

        return (int) $cart->id;
    }
}
