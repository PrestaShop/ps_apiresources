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

class CartProductEndpointTest extends ApiTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::createApiClient(['cart_write']);
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'add product to cart endpoint' => ['PUT', '/carts/1/products'];
    }

    public function testAddProductToCart(): void
    {
        $cartId = $this->createCart();
        $productId = $this->getSimpleInStockProductId();

        $this->updateItem(
            '/carts/' . $cartId . '/products',
            ['productId' => $productId, 'quantity' => 2],
            ['cart_write'],
            Response::HTTP_NO_CONTENT
        );

        $quantity = (int) \Db::getInstance()->getValue(
            'SELECT `quantity` FROM `' . _DB_PREFIX_ . 'cart_product`
             WHERE `id_cart` = ' . $cartId . ' AND `id_product` = ' . $productId
        );
        $this->assertSame(2, $quantity);
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
}
