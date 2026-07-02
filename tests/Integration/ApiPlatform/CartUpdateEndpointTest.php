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

class CartUpdateEndpointTest extends ApiTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::createApiClient(['cart_write']);
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'update cart currency endpoint' => ['PUT', '/carts/1/currencies'];
        yield 'update cart language endpoint' => ['PUT', '/carts/1/languages'];
    }

    public function testUpdateCartCurrency(): void
    {
        $cartId = $this->createCart();
        $currencyId = (int) \Db::getInstance()->getValue(
            'SELECT `id_currency` FROM `' . _DB_PREFIX_ . 'currency` WHERE `deleted` = 0 ORDER BY `id_currency` ASC'
        );

        $this->updateItem(
            '/carts/' . $cartId . '/currencies',
            ['newCurrencyId' => $currencyId],
            ['cart_write'],
            Response::HTTP_NO_CONTENT
        );

        $this->assertSame($currencyId, (int) (new \Cart($cartId))->id_currency);
    }

    public function testUpdateCartLanguage(): void
    {
        $cartId = $this->createCart();
        $languageId = (int) \Db::getInstance()->getValue(
            'SELECT `id_lang` FROM `' . _DB_PREFIX_ . 'lang` WHERE `active` = 1 ORDER BY `id_lang` ASC'
        );

        $this->updateItem(
            '/carts/' . $cartId . '/languages',
            ['newLanguageId' => $languageId],
            ['cart_write'],
            Response::HTTP_NO_CONTENT
        );

        $this->assertSame($languageId, (int) (new \Cart($cartId))->id_lang);
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
