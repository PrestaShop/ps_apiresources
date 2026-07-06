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

class OrderDiscountEndpointTest extends ApiTestCase
{
    private const DISCOUNT_NAME = 'Test API discount';

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::createApiClient(['order_write']);
    }

    public static function tearDownAfterClass(): void
    {
        \Db::getInstance()->delete('order_cart_rule', "`name` = '" . self::DISCOUNT_NAME . "'");

        parent::tearDownAfterClass();
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'add discount to order endpoint' => ['PUT', '/orders/1/discounts'];
    }

    public function testAddCartRuleToOrder(): void
    {
        $orderId = (int) \Db::getInstance()->getValue(
            'SELECT `id_order` FROM `' . _DB_PREFIX_ . 'orders` ORDER BY `id_order` DESC'
        );

        $this->updateItem(
            '/orders/' . $orderId . '/discounts',
            [
                'cartRuleName' => self::DISCOUNT_NAME,
                'cartRuleType' => 'amount',
                'value' => '1',
                'orderInvoiceId' => null,
            ],
            ['order_write'],
            Response::HTTP_NO_CONTENT
        );

        $discountCount = (int) \Db::getInstance()->getValue(
            'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'order_cart_rule`
             WHERE `id_order` = ' . $orderId . " AND `name` = '" . self::DISCOUNT_NAME . "'"
        );
        $this->assertSame(1, $discountCount);
    }
}
