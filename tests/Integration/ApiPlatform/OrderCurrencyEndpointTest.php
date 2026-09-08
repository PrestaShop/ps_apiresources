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

class OrderCurrencyEndpointTest extends ApiTestCase
{
    private static int $orderId;

    private static int $originalCurrencyId;

    private static int $originalValid;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::createApiClient(['order_write']);

        self::$orderId = (int) \Db::getInstance()->getValue(
            'SELECT `id_order` FROM `' . _DB_PREFIX_ . 'orders` ORDER BY `id_order` DESC'
        );
        self::$originalCurrencyId = (int) \Db::getInstance()->getValue(
            'SELECT `id_currency` FROM `' . _DB_PREFIX_ . 'orders` WHERE `id_order` = ' . self::$orderId
        );
        self::$originalValid = (int) \Db::getInstance()->getValue(
            'SELECT `valid` FROM `' . _DB_PREFIX_ . 'orders` WHERE `id_order` = ' . self::$orderId
        );

        // The handler refuses to change currency on a "valid" (paid) order — force valid=0.
        \Db::getInstance()->update(
            'orders',
            ['valid' => 0],
            '`id_order` = ' . self::$orderId
        );
    }

    public static function tearDownAfterClass(): void
    {
        \Db::getInstance()->update(
            'orders',
            [
                'id_currency' => self::$originalCurrencyId,
                'valid' => self::$originalValid,
            ],
            '`id_order` = ' . self::$orderId
        );

        parent::tearDownAfterClass();
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'change order currency endpoint' => ['PUT', '/orders/1/currencies'];
    }

    public function testChangeOrderCurrency(): void
    {
        $newCurrencyId = (int) \Db::getInstance()->getValue(
            'SELECT `id_currency` FROM `' . _DB_PREFIX_ . 'currency`
             WHERE `deleted` = 0 AND `active` = 1 AND `id_currency` <> ' . self::$originalCurrencyId . '
             ORDER BY `id_currency` ASC'
        );
        if ($newCurrencyId === 0) {
            $this->markTestSkipped('No alternative active currency available in the fixtures.');
        }

        $this->updateItem(
            '/orders/' . self::$orderId . '/currencies',
            ['newCurrencyId' => $newCurrencyId],
            ['order_write'],
            Response::HTTP_NO_CONTENT
        );

        $currentCurrency = (int) \Db::getInstance()->getValue(
            'SELECT `id_currency` FROM `' . _DB_PREFIX_ . 'orders` WHERE `id_order` = ' . self::$orderId
        );
        $this->assertSame($newCurrencyId, $currentCurrency);
    }

    public function testChangeOrderCurrencyWithInvalidPayload(): void
    {
        $validationErrorsResponse = $this->updateItem(
            '/orders/' . self::$orderId . '/currencies',
            ['newCurrencyId' => 0],
            ['order_write'],
            Response::HTTP_UNPROCESSABLE_ENTITY
        );
        $this->assertIsArray($validationErrorsResponse);
        $this->assertValidationErrors([
            [
                'propertyPath' => 'newCurrencyId',
                'message' => 'This value should be positive.',
            ],
        ], $validationErrorsResponse);
    }

    public function testChangeOrderCurrencyOnValidOrderReturns422(): void
    {
        // Flip the order back to valid=1 so the handler refuses the change.
        \Db::getInstance()->update('orders', ['valid' => 1], '`id_order` = ' . self::$orderId);
        try {
            $newCurrencyId = (int) \Db::getInstance()->getValue(
                'SELECT `id_currency` FROM `' . _DB_PREFIX_ . 'currency`
                 WHERE `deleted` = 0 AND `active` = 1 AND `id_currency` <> ' . self::$originalCurrencyId . '
                 ORDER BY `id_currency` ASC'
            );
            if ($newCurrencyId === 0) {
                $this->markTestSkipped('No alternative active currency available in the fixtures.');
            }

            $this->updateItem(
                '/orders/' . self::$orderId . '/currencies',
                ['newCurrencyId' => $newCurrencyId],
                ['order_write'],
                Response::HTTP_UNPROCESSABLE_ENTITY
            );
        } finally {
            \Db::getInstance()->update('orders', ['valid' => 0], '`id_order` = ' . self::$orderId);
        }
    }
}
