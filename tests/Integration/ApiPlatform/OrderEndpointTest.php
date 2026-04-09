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

class OrderEndpointTest extends ApiTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::createApiClient(['order_read', 'order_write']);
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'get order endpoint' => ['GET', '/orders/1'];
        yield 'get order preview endpoint' => ['GET', '/orders/1/previews'];
        yield 'create order endpoint' => ['POST', '/orders'];
        // Order address endpoints return 404 when order doesn't exist (no order ID 1 in test fixtures)
        yield 'add order cart rule endpoint' => ['POST', '/orders/1/cart-rules'];
        yield 'delete order cart rule endpoint' => ['DELETE', '/orders/1/cart-rules/1'];
        yield 'update order currency endpoint' => ['PUT', '/orders/1/currencies'];
        yield 'duplicate order cart endpoint' => ['POST', '/orders/1/duplicate-carts'];
        yield 'update order note endpoint' => ['PUT', '/orders/1/notes'];
        yield 'update order status endpoint' => ['PUT', '/orders/1/status'];
    }

    public function testGetNonExistentOrder(): void
    {
        $this->getItem('/orders/999999', ['order_read'], Response::HTTP_NOT_FOUND);
    }
}
