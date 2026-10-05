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
use Tests\Resources\DatabaseDump;

class OrderDiscountRemovalEndpointTest extends ApiTestCase
{
    private const DISCOUNT_NAME = 'Test API removable discount';

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        DatabaseDump::restoreTables(['order_cart_rule']);
        self::createApiClient(['order_write']);
    }

    public static function tearDownAfterClass(): void
    {
        DatabaseDump::restoreTables(['order_cart_rule']);
        parent::tearDownAfterClass();
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'remove discount from order endpoint' => ['DELETE', '/orders/1/discounts/1'];
    }

    public function testDeleteCartRuleFromOrder(): void
    {
        $cartRuleId = (int) \Db::getInstance()->getValue(
            'SELECT `id_cart_rule` FROM `' . _DB_PREFIX_ . 'cart_rule` ORDER BY `id_cart_rule` ASC'
        );
        if ($cartRuleId === 0) {
            $this->markTestSkipped('No cart rule available in the fixtures.');
        }

        $orderId = (int) \Db::getInstance()->getValue(
            'SELECT `id_order` FROM `' . _DB_PREFIX_ . 'orders` ORDER BY `id_order` DESC'
        );

        \Db::getInstance()->insert('order_cart_rule', [
            'id_order' => $orderId,
            'id_cart_rule' => $cartRuleId,
            'id_order_invoice' => 0,
            'name' => self::DISCOUNT_NAME,
            'value' => 1,
            'value_tax_excl' => 1,
            'free_shipping' => 0,
            'deleted' => 0,
        ]);
        $orderCartRuleId = (int) \Db::getInstance()->Insert_ID();

        $this->deleteItem(
            '/orders/' . $orderId . '/discounts/' . $orderCartRuleId,
            ['order_write']
        );

        $remaining = (int) \Db::getInstance()->getValue(
            'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'order_cart_rule`
             WHERE `id_order_cart_rule` = ' . $orderCartRuleId . ' AND `deleted` = 0'
        );
        $this->assertSame(0, $remaining);
    }

    public function testDeleteFromNonExistentOrderReturns404(): void
    {
        $this->deleteItem('/orders/999999/discounts/999999', ['order_write'], Response::HTTP_NOT_FOUND);
    }
}
