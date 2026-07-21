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

class OrderReturnDeleteEndpointTest extends ApiTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        DatabaseDump::restoreTables(['order_return', 'order_return_detail']);
        self::createApiClient(['order_return_write']);
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        DatabaseDump::restoreTables(['order_return', 'order_return_detail']);
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'delete endpoint' => ['DELETE', '/order-returns/999999'];
        yield 'bulk delete endpoint' => ['DELETE', '/order-returns/bulk-delete'];
    }

    public function testDeleteOrderReturn(): void
    {
        $id = $this->seedOrderReturn();

        $this->requestApi(
            'DELETE',
            '/order-returns/' . $id,
            null,
            ['order_return_write'],
            Response::HTTP_NO_CONTENT
        );

        $stillThere = (int) \Db::getInstance()->getValue(
            'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'order_return` WHERE `id_order_return` = ' . $id
        );
        $this->assertSame(0, $stillThere);
    }

    public function testBulkDeleteOrderReturns(): void
    {
        $ids = [$this->seedOrderReturn(), $this->seedOrderReturn()];

        $this->requestApi(
            'DELETE',
            '/order-returns/bulk-delete',
            ['orderReturnIds' => $ids],
            ['order_return_write'],
            Response::HTTP_NO_CONTENT
        );

        $stillThere = (int) \Db::getInstance()->getValue(
            'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'order_return` WHERE `id_order_return` IN (' . implode(', ', $ids) . ')'
        );
        $this->assertSame(0, $stillThere);
    }

    private function seedOrderReturn(): int
    {
        $orderId = (int) \Db::getInstance()->getValue(
            'SELECT `id_order` FROM `' . _DB_PREFIX_ . 'orders` ORDER BY `id_order` ASC'
        );
        $customerId = (int) \Db::getInstance()->getValue(
            'SELECT `id_customer` FROM `' . _DB_PREFIX_ . 'orders` WHERE `id_order` = ' . $orderId
        );
        \Db::getInstance()->insert('order_return', [
            'id_customer' => $customerId,
            'id_order' => $orderId,
            'state' => 1,
            'question' => 'Seed for delete test',
        ]);

        return (int) \Db::getInstance()->Insert_ID();
    }
}
