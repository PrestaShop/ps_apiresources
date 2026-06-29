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

class CartDetailsEndpointTest extends ApiTestCase
{
    private static int $cartId;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::createApiClient(['cart_read']);

        // Reuse a demo cart that is linked to an order, so it is a complete cart
        // (products + addresses) exercising the whole cart view aggregate.
        self::$cartId = (int) \Db::getInstance()->getValue(
            'SELECT `id_cart` FROM `' . _DB_PREFIX_ . 'orders` ORDER BY `id_order` ASC'
        );
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'get cart details endpoint' => ['GET', '/carts/1/details'];
    }

    public function testGetCartDetails(): void
    {
        $cart = $this->getItem('/carts/' . self::$cartId . '/details', ['cart_read']);

        $this->assertArrayHasKey('cartId', $cart);
        $this->assertSame(self::$cartId, $cart['cartId']);
        $this->assertArrayHasKey('cartCurrencyId', $cart);
        $this->assertArrayHasKey('customerInformation', $cart);
        $this->assertArrayHasKey('orderInformation', $cart);
        $this->assertArrayHasKey('cartSummary', $cart);
    }

    public function testGetNonExistentCartDetails(): void
    {
        $this->requestApi('GET', '/carts/999999/details', null, ['cart_read'], Response::HTTP_NOT_FOUND);
    }
}
