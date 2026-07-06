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

class CartEmailEndpointTest extends ApiTestCase
{
    private static int $originalMailMethod;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::createApiClient(['cart_write']);

        // Disable real email sending so Mail::send() succeeds without an SMTP server.
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
        yield 'send cart to customer endpoint' => ['PUT', '/carts/1/emails'];
    }

    public function testSendCartToCustomer(): void
    {
        $cartId = $this->createCustomerCart();

        $this->requestApi(
            'PUT',
            '/carts/' . $cartId . '/emails',
            null,
            ['cart_write'],
            Response::HTTP_NO_CONTENT
        );
    }

    private function createCustomerCart(): int
    {
        $customerId = (int) \Db::getInstance()->getValue(
            'SELECT `id_customer` FROM `' . _DB_PREFIX_ . 'customer` WHERE `active` = 1 ORDER BY `id_customer` ASC'
        );

        $cart = new \Cart();
        $cart->id_customer = $customerId;
        $cart->id_currency = (int) \Configuration::get('PS_CURRENCY_DEFAULT');
        $cart->id_lang = (int) \Configuration::get('PS_LANG_DEFAULT');
        $cart->id_shop = 1;
        $cart->add();

        return (int) $cart->id;
    }
}
