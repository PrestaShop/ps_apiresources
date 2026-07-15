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

class OrderProductCancellationEndpointTest extends ApiTestCase
{
    private static int $paidOrderId;
    private static int $paidOrderDetailId;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::createApiClient(['order_write']);

        // Pick any fixture order together with one of its order_detail rows.
        // Fashion demo orders are in paid states, so this is enough to exercise
        // the InvalidOrderStateException::ALREADY_PAID branch (422).
        $row = \Db::getInstance()->getRow(
            'SELECT o.`id_order`, od.`id_order_detail`
             FROM `' . _DB_PREFIX_ . 'orders` o
             INNER JOIN `' . _DB_PREFIX_ . 'order_detail` od ON od.`id_order` = o.`id_order`'
        );
        self::$paidOrderId = (int) $row['id_order'];
        self::$paidOrderDetailId = (int) $row['id_order_detail'];
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'cancel order product endpoint' => ['POST', '/orders/1/product-cancellations'];
    }

    public function testEmptyCancelledProductsReturns422(): void
    {
        // Assert\NotBlank on `cancelledProducts` rejects an empty map at
        // validation time — before the handler runs.
        $this->createItem(
            '/orders/' . self::$paidOrderId . '/product-cancellations',
            ['cancelledProducts' => []],
            ['order_write'],
            Response::HTTP_UNPROCESSABLE_ENTITY
        );
    }

    public function testInvalidQuantityReturns422(): void
    {
        // Quantity <= 0 triggers InvalidCancelProductException::INVALID_QUANTITY.
        // checkInput() runs BEFORE checkOrderState(), so this fires even on a
        // paid fixture order.
        $this->createItem(
            '/orders/' . self::$paidOrderId . '/product-cancellations',
            ['cancelledProducts' => [(string) self::$paidOrderDetailId => 0]],
            ['order_write'],
            Response::HTTP_UNPROCESSABLE_ENTITY
        );
    }

    public function testCancelOnAlreadyPaidOrderReturns422(): void
    {
        // Fixture orders are paid → checkOrderState() throws
        // InvalidOrderStateException::ALREADY_PAID, mapped to 422.
        $this->createItem(
            '/orders/' . self::$paidOrderId . '/product-cancellations',
            ['cancelledProducts' => [(string) self::$paidOrderDetailId => 1]],
            ['order_write'],
            Response::HTTP_UNPROCESSABLE_ENTITY
        );
    }

    public function testCancelProductHappyPath(): void
    {
        // A real happy-path cancel decreases the order line's quantity, which
        // eventually calls Core\Stock\StockManager::saveMovement(). In the API
        // context there is no logged-in employee, so the movement recording
        // hits StockMvt::setIdEmployee(null) → TypeError. Core PR
        // PrestaShop/PrestaShop#41803 guards that; until it ships we cannot
        // exercise the full success path in CI.
        //
        // Enable this test (and add unpaid-order + order_detail seeding) once
        // #41803 is merged and available in the tested core versions.
        self::markTestSkipped('Depends on core PR PrestaShop/PrestaShop#41803 (StockMvt null-employee guard).');
    }
}
