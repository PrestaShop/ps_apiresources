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

class OrderCartDuplicationEndpointTest extends ApiTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::createApiClient(['order_write']);
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'duplicate order cart endpoint' => ['PUT', '/orders/1/cart-duplications'];
    }

    public function testDuplicateOrderCart(): void
    {
        $orderId = (int) \Db::getInstance()->getValue(
            'SELECT `id_order` FROM `' . _DB_PREFIX_ . 'orders` ORDER BY `id_order` DESC'
        );

        $response = $this->updateItem(
            '/orders/' . $orderId . '/cart-duplications',
            [],
            ['order_write'],
            Response::HTTP_OK
        );

        $this->assertArrayHasKey('cartId', $response);
        $this->assertGreaterThan(0, $response['cartId']);
        $this->assertSame($orderId, $response['orderId']);
    }
}
