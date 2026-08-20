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

class OrderReturnActionsEndpointTest extends ApiTestCase
{
    private const MIN_VERSION = '9.2.0';

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        DatabaseDump::restoreTables(['order_return', 'order_return_detail']);
        // Every command and query this class exercises landed in 9.2. On older cores the
        // operations are filtered out of the API (ApiResourceScopesExtractor drops operations
        // whose CQRS class is missing), so the routes and the scopes do not exist at all.
        if (!self::isVersionAtLeast(self::MIN_VERSION)) {
            self::markTestSkipped(sprintf(
                'The OrderReturn command and query classes require PrestaShop >= %s',
                self::MIN_VERSION
            ));
        }

        self::createApiClient(['order_return_read', 'order_return_write']);
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
        yield 'list products endpoint' => ['GET', '/order-returns/1/products'];
        yield 'delete product endpoint' => ['DELETE', '/order-returns/1/products/1'];
        yield 'bulk delete products endpoint' => ['DELETE', '/order-returns/1/products/bulk-delete'];
    }

    /**
     * Order returns are created by the customer from the front office: the OrderReturn domain
     * exposes only Delete, BulkDelete and UpdateState commands, so there is no Admin API way to
     * create one and this is the single place where a fixture cannot come from the API.
     * Everything the tests below assert goes back through the API.
     *
     * @return array{0: int, 1: int[]} the order return id and the order detail ids it holds
     */
    private function seedOrderReturn(): array
    {
        $order = \Db::getInstance()->getRow(
            'SELECT `id_order`, `id_customer` FROM `' . _DB_PREFIX_ . 'orders` ORDER BY `id_order` ASC'
        );

        \Db::getInstance()->insert('order_return', [
            'id_customer' => (int) $order['id_customer'],
            'id_order' => (int) $order['id_order'],
            'state' => 1,
            'question' => 'Seed for the order return actions test',
        ]);
        $orderReturnId = (int) \Db::getInstance()->Insert_ID();

        $orderDetailIds = [];
        $rows = \Db::getInstance()->executeS(
            'SELECT `id_order_detail` FROM `' . _DB_PREFIX_ . 'order_detail`
             WHERE `id_order` = ' . (int) $order['id_order'] . ' ORDER BY `id_order_detail` ASC'
        );
        foreach ($rows ?: [] as $row) {
            \Db::getInstance()->insert('order_return_detail', [
                'id_order_return' => $orderReturnId,
                'id_order_detail' => (int) $row['id_order_detail'],
                'id_customization' => 0,
                'product_quantity' => 1,
            ]);
            $orderDetailIds[] = (int) $row['id_order_detail'];
        }

        return [$orderReturnId, $orderDetailIds];
    }

    /**
     * @return int[]
     */
    private function listProductIds(int $orderReturnId): array
    {
        $products = $this->getItem('/order-returns/' . $orderReturnId . '/products', ['order_return_read']);

        return array_map('intval', array_column($products, 'orderDetailId'));
    }

    public function testListOrderReturnProducts(): void
    {
        [$orderReturnId, $orderDetailIds] = $this->seedOrderReturn();
        if ([] === $orderDetailIds) {
            $this->markTestSkipped('The fixture order has no order detail to return.');
        }

        $products = $this->getItem('/order-returns/' . $orderReturnId . '/products', ['order_return_read']);

        $this->assertNotEmpty($products);
        $this->assertEquals(
            ['orderDetailId', 'customizationId', 'reference', 'productName', 'quantity', 'customization'],
            array_keys($products[0])
        );
        $this->assertSame($orderDetailIds, $this->listProductIds($orderReturnId));
    }

    public function testDeleteProductFromOrderReturn(): void
    {
        [$orderReturnId, $orderDetailIds] = $this->seedOrderReturn();
        if ([] === $orderDetailIds) {
            $this->markTestSkipped('The fixture order has no order detail to return.');
        }

        $removed = array_shift($orderDetailIds);

        $this->requestApi(
            'DELETE',
            '/order-returns/' . $orderReturnId . '/products/' . $removed,
            null,
            ['order_return_write'],
            Response::HTTP_NO_CONTENT
        );

        // The removal is observed through the products listing, which #372 adds
        $this->assertSame($orderDetailIds, $this->listProductIds($orderReturnId));
    }

    public function testBulkDeleteProductsFromOrderReturn(): void
    {
        [$orderReturnId, $orderDetailIds] = $this->seedOrderReturn();
        if ([] === $orderDetailIds) {
            $this->markTestSkipped('The fixture order has no order detail to return.');
        }

        $this->requestApi(
            'DELETE',
            '/order-returns/' . $orderReturnId . '/products/bulk-delete',
            [
                'stagedProductRows' => array_map(
                    static fn (int $orderDetailId): array => [
                        'order_detail_id' => $orderDetailId,
                        'customization_id' => 0,
                    ],
                    $orderDetailIds
                ),
            ],
            ['order_return_write'],
            Response::HTTP_NO_CONTENT
        );

        $this->assertSame([], $this->listProductIds($orderReturnId));
    }

    public function testDeleteOrderReturn(): void
    {
        [$orderReturnId] = $this->seedOrderReturn();

        $this->requestApi(
            'DELETE',
            '/order-returns/' . $orderReturnId,
            null,
            ['order_return_write'],
            Response::HTTP_NO_CONTENT
        );

        // Asserted through the API instead of SELECT COUNT(*) FROM ps_order_return
        $this->getItem('/order-returns/' . $orderReturnId, ['order_return_read'], Response::HTTP_NOT_FOUND);
    }

    public function testBulkDeleteOrderReturns(): void
    {
        $orderReturnIds = [$this->seedOrderReturn()[0], $this->seedOrderReturn()[0]];

        $this->requestApi(
            'DELETE',
            '/order-returns/bulk-delete',
            ['orderReturnIds' => $orderReturnIds],
            ['order_return_write'],
            Response::HTTP_NO_CONTENT
        );

        foreach ($orderReturnIds as $orderReturnId) {
            $this->getItem('/order-returns/' . $orderReturnId, ['order_return_read'], Response::HTTP_NOT_FOUND);
        }
    }
}
