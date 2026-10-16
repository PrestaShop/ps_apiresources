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

class OrderInternalNoteEndpointTest extends ApiTestCase
{
    private static int $orderId;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::createApiClient(['order_write']);
        self::$orderId = (int) \Db::getInstance()->getValue(
            'SELECT `id_order` FROM `' . _DB_PREFIX_ . 'orders` ORDER BY `id_order` ASC'
        );
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        DatabaseDump::restoreTables(['orders']);
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'set internal note endpoint' => ['PUT', '/orders/1/internal-notes'];
    }

    public function testSetInternalOrderNote(): void
    {
        $note = 'Internal note set through the Admin API';

        $this->updateItem(
            '/orders/' . self::$orderId . '/internal-notes',
            ['internalNote' => $note],
            ['order_write'],
            Response::HTTP_NO_CONTENT
        );

        $order = new \Order(self::$orderId);
        $this->assertSame($note, $order->note);
    }

    public function testSetInternalNoteOnMissingOrderReturnsNotFound(): void
    {
        $this->updateItem(
            '/orders/999999/internal-notes',
            ['internalNote' => 'whatever'],
            ['order_write'],
            Response::HTTP_NOT_FOUND
        );
    }
}
