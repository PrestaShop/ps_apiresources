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

use PrestaShop\PrestaShop\Core\Domain\OrderReturn\Exception\OrderReturnNotFoundException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Tests\Resources\DatabaseDump;

class OrderReturnActionsEndpointTest extends ApiTestCase
{
    public static function setUpBeforeClass(): void
    {
        // Every command and query this class exercises landed in 9.2.0. On older cores the operations are filtered
        // out of the API (their CQRS class is missing), so the routes and the scopes do not exist at all.
        if (self::isVersionUnder('9.2.0')) {
            self::markTestSkipped('The OrderReturn command and query classes require PrestaShop >= 9.2.0');
        }

        parent::setUpBeforeClass();
        DatabaseDump::restoreTables(['order_return', 'order_return_detail', 'customized_data']);
        self::createApiClient(['order_return_read', 'order_return_write']);
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        DatabaseDump::restoreTables(['order_return', 'order_return_detail', 'customized_data']);
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'delete endpoint' => ['DELETE', '/order-returns/999999'];
        yield 'bulk delete endpoint' => ['DELETE', '/order-returns/bulk-delete'];
        yield 'list products endpoint' => ['GET', '/order-returns/1/products'];

        // The product deletions use the CQRSDeleteCollection of core 9.2.1, filtered out on older cores
        if (self::isVersionAtLeast('9.2.1')) {
            yield 'delete product endpoint' => ['DELETE', '/order-returns/1/products/1'];
            yield 'bulk delete products endpoint' => ['DELETE', '/order-returns/1/products/bulk-delete'];
        }
    }

    /**
     * Order returns are created by the customer from the front office: the OrderReturn domain
     * exposes only Delete, BulkDelete and UpdateState commands, so there is no Admin API way to
     * create one and this is the single place where a fixture cannot come from the API.
     * Everything the tests below assert goes back through the API.
     *
     * The first fixture order holds the order details 1 (demo_1) and 2 (demo_3): the return holds one unit of each.
     */
    private function seedOrderReturn(): int
    {
        \Db::getInstance()->insert('order_return', [
            'id_customer' => 2,
            'id_order' => 1,
            'state' => 1,
            'question' => 'Seed for the order return actions test',
        ]);
        $orderReturnId = (int) \Db::getInstance()->Insert_ID();

        foreach ([1, 2] as $orderDetailId) {
            \Db::getInstance()->insert('order_return_detail', [
                'id_order_return' => $orderReturnId,
                'id_order_detail' => $orderDetailId,
                'id_customization' => 0,
                'product_quantity' => 1,
            ]);
        }

        return $orderReturnId;
    }

    private static function productLine(int $orderDetailId): array
    {
        return [
            1 => [
                'orderDetailId' => 1,
                'customizationId' => 0,
                'reference' => 'demo_1',
                'productName' => 'Hummingbird printed t-shirt - Color : White, Size : S',
                'quantity' => 1,
                'customization' => false,
                'customizationFields' => [],
            ],
            2 => [
                'orderDetailId' => 2,
                'customizationId' => 0,
                'reference' => 'demo_3',
                'productName' => 'Hummingbird printed sweater - Color : White, Size : S',
                'quantity' => 1,
                'customization' => false,
                'customizationFields' => [],
            ],
        ][$orderDetailId];
    }

    private function listProducts(int $orderReturnId): array
    {
        return $this->getItem('/order-returns/' . $orderReturnId . '/products', ['order_return_read']);
    }

    public function testListOrderReturnProducts(): void
    {
        $orderReturnId = $this->seedOrderReturn();

        $this->assertSame([self::productLine(1), self::productLine(2)], $this->listProducts($orderReturnId));

        $this->getItem('/order-returns/999999/products', ['order_return_read'], Response::HTTP_NOT_FOUND);
        // OrderReturnId rejects 0
        $this->getItem('/order-returns/0/products', ['order_return_read'], Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testListOnlyTheLinesOfTheOrderReturn(): void
    {
        // Before 9.2.1, the list holds the customized lines of every return of the order, and lists them twice
        $this->markTestSkippedByMinVersion('9.2.1');
        $classicReturnId = $this->seedOrderReturn();

        // A second return of the same order holds a customized line of the order detail 1 (a text field)
        $customizationId = 9001;
        \Db::getInstance()->insert('customized_data', [
            'id_customization' => $customizationId,
            'type' => 1,
            'index' => 1,
            'value' => 'Hello',
        ]);
        \Db::getInstance()->insert('order_return', [
            'id_customer' => 2,
            'id_order' => 1,
            'state' => 1,
            'question' => 'Seed of a customized line',
        ]);
        $customizedReturnId = (int) \Db::getInstance()->Insert_ID();
        \Db::getInstance()->insert('order_return_detail', [
            'id_order_return' => $customizedReturnId,
            'id_order_detail' => 1,
            'id_customization' => $customizationId,
            'product_quantity' => 1,
        ]);

        $this->assertSame([self::productLine(1), self::productLine(2)], $this->listProducts($classicReturnId));
        $this->assertSame([
            [
                'orderDetailId' => 1,
                'customizationId' => $customizationId,
                'reference' => 'demo_1',
                'productName' => 'Hummingbird printed t-shirt - Color : White, Size : S',
                'quantity' => 1,
                'customization' => true,
                'customizationFields' => [
                    ['type' => 1, 'value' => 'Hello'],
                ],
            ],
        ], $this->listProducts($customizedReturnId));
    }

    public function testDeleteProductFromOrderReturn(): void
    {
        $this->markTestSkippedByMinVersion('9.2.1');
        $orderReturnId = $this->seedOrderReturn();

        // A line that is not in the return
        $this->deleteItem('/order-returns/' . $orderReturnId . '/products/3', ['order_return_write'], Response::HTTP_NOT_FOUND);

        // Answers the remaining lines
        $this->assertSame([self::productLine(2)], $this->deleteItem('/order-returns/' . $orderReturnId . '/products/1', ['order_return_write'], Response::HTTP_OK));
        $this->assertSame([self::productLine(2)], $this->listProducts($orderReturnId));

        // A merchandise return keeps at least one line: checked before the line itself
        $this->deleteItem('/order-returns/' . $orderReturnId . '/products/2', ['order_return_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->deleteItem('/order-returns/' . $orderReturnId . '/products/1', ['order_return_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->deleteItem('/order-returns/999999/products/1', ['order_return_write'], Response::HTTP_NOT_FOUND);
        $this->deleteItem('/order-returns/0/products/1', ['order_return_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->deleteItem('/order-returns/' . $orderReturnId . '/products/0', ['order_return_write'], Response::HTTP_UNPROCESSABLE_ENTITY);

        $this->assertSame([self::productLine(2)], $this->listProducts($orderReturnId));
    }

    public function testBulkDeleteProductsFromOrderReturn(): void
    {
        $this->markTestSkippedByMinVersion('9.2.1');
        $orderReturnId = $this->seedOrderReturn();

        // Answers the remaining lines
        $this->assertSame([self::productLine(2)], $this->bulkDeleteItems(
            '/order-returns/' . $orderReturnId . '/products/bulk-delete',
            ['stagedProductRows' => [['orderDetailId' => 1, 'customizationId' => 0]]],
            ['order_return_write'],
            Response::HTTP_OK
        ));
        $this->assertSame([self::productLine(2)], $this->listProducts($orderReturnId));
    }

    public function testBulkDeleteEveryProductFromOrderReturnIsRefused(): void
    {
        $this->markTestSkippedByMinVersion('9.2.1');
        $orderReturnId = $this->seedOrderReturn();

        // Emptying a merchandise return is refused by the core, which is a client error
        $this->bulkDeleteItems(
            '/order-returns/' . $orderReturnId . '/products/bulk-delete',
            ['stagedProductRows' => [['orderDetailId' => 1], ['orderDetailId' => 2]]],
            ['order_return_write'],
            Response::HTTP_UNPROCESSABLE_ENTITY
        );

        $this->assertSame([self::productLine(1), self::productLine(2)], $this->listProducts($orderReturnId));
    }

    public function testBulkDeleteProductsWithInvalidBodies(): void
    {
        $this->markTestSkippedByMinVersion('9.2.1');
        $orderReturnId = $this->seedOrderReturn();
        $uri = '/order-returns/' . $orderReturnId . '/products/bulk-delete';

        $response = $this->bulkDeleteItems(
            $uri,
            ['stagedProductRows' => [['customizationId' => 0], ['orderDetailId' => -1], ['orderDetailId' => null], ['orderDetailId' => 1, 'customizationId' => -1]]],
            ['order_return_write'],
            Response::HTTP_UNPROCESSABLE_ENTITY
        );
        $this->assertValidationErrors([
            ['propertyPath' => 'stagedProductRows[0].orderDetailId'],
            ['propertyPath' => 'stagedProductRows[1].orderDetailId'],
            ['propertyPath' => 'stagedProductRows[2].orderDetailId'],
            ['propertyPath' => 'stagedProductRows[3].customizationId'],
        ], $response);

        // A wrong type is refused by the serializer before the command could cast it: "3abc" must not act on line 3
        $this->bulkDeleteItems($uri, ['stagedProductRows' => [['orderDetailId' => '3abc']]], ['order_return_write'], Response::HTTP_BAD_REQUEST);

        // The rows are required
        $this->bulkDeleteItems($uri, ['stagedProductRows' => []], ['order_return_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->bulkDeleteItems($uri, null, ['order_return_write'], Response::HTTP_UNPROCESSABLE_ENTITY, ['json' => []]);

        $this->bulkDeleteItems('/order-returns/999999/products/bulk-delete', ['stagedProductRows' => [['orderDetailId' => 1]]], ['order_return_write'], Response::HTTP_NOT_FOUND);
        $this->bulkDeleteItems('/order-returns/0/products/bulk-delete', ['stagedProductRows' => [['orderDetailId' => 1]]], ['order_return_write'], Response::HTTP_UNPROCESSABLE_ENTITY);

        $this->assertSame([self::productLine(1), self::productLine(2)], $this->listProducts($orderReturnId));
    }

    public function testDeleteOrderReturn(): void
    {
        $orderReturnId = $this->seedOrderReturn();

        $this->assertNull($this->deleteItem('/order-returns/' . $orderReturnId, ['order_return_write'], Response::HTTP_NO_CONTENT));
        $this->getItem('/order-returns/' . $orderReturnId, ['order_return_read'], Response::HTTP_NOT_FOUND);

        $this->deleteItem('/order-returns/' . $orderReturnId, ['order_return_write'], Response::HTTP_NOT_FOUND);
        $this->deleteItem('/order-returns/0', ['order_return_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testUpdateOrderReturnStateIsRequired(): void
    {
        $orderReturnId = $this->seedOrderReturn();

        // A missing state and an explicit null are the same error
        // An empty array is sent as an empty JSON object through the json option (the data argument would send no body)
        foreach ([['json' => []], ['json' => ['orderReturnStateId' => null]]] as $requestOptions) {
            $response = $this->partialUpdateItem('/order-returns/' . $orderReturnId, null, ['order_return_write'], Response::HTTP_UNPROCESSABLE_ENTITY, $requestOptions);
            $this->assertValidationErrors([['propertyPath' => 'orderReturnStateId']], $response);
        }
    }

    public function testBulkDeleteOrderReturns(): void
    {
        $orderReturnIds = [$this->seedOrderReturn(), $this->seedOrderReturn()];

        $this->assertNull($this->bulkDeleteItems('/order-returns/bulk-delete', ['orderReturnIds' => $orderReturnIds], ['order_return_write'], Response::HTTP_NO_CONTENT));
        foreach ($orderReturnIds as $orderReturnId) {
            $this->getItem('/order-returns/' . $orderReturnId, ['order_return_read'], Response::HTTP_NOT_FOUND);
        }
    }

    public function testBulkDeleteOrderReturnsWithUnknownId(): void
    {
        // Before 9.2.1, an unknown id answers 404 and stops the deletion
        $this->markTestSkippedByMinVersion('9.2.1');
        $orderReturnIds = [$this->seedOrderReturn(), $this->seedOrderReturn()];

        $this->bulkCommandItemsWithExpectedErrors(
            Request::METHOD_DELETE,
            '/order-returns/bulk-delete',
            ['orderReturnIds' => [$orderReturnIds[0], 999999, $orderReturnIds[1]]],
            [
                [
                    'type' => OrderReturnNotFoundException::class,
                    'message' => 'OrderReturn #999999 was not found',
                    'status' => Response::HTTP_NOT_FOUND,
                ],
            ],
            ['order_return_write']
        );

        // The other order returns are deleted
        foreach ($orderReturnIds as $orderReturnId) {
            $this->getItem('/order-returns/' . $orderReturnId, ['order_return_read'], Response::HTTP_NOT_FOUND);
        }
    }

    public function testBulkDeleteOrderReturnsWithInvalidBodies(): void
    {
        // OrderReturnId rejects 0 in the command constructor
        $this->bulkDeleteItems('/order-returns/bulk-delete', ['orderReturnIds' => [0]], ['order_return_write'], Response::HTTP_UNPROCESSABLE_ENTITY);

        // The bulk command casts each id with (int): "3abc" must not reach it
        $response = $this->bulkDeleteItems('/order-returns/bulk-delete', ['orderReturnIds' => ['3abc']], ['order_return_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertValidationErrors([['propertyPath' => 'orderReturnIds[0]']], $response);

        // The ids are required
        $this->bulkDeleteItems('/order-returns/bulk-delete', ['orderReturnIds' => []], ['order_return_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->bulkDeleteItems('/order-returns/bulk-delete', null, ['order_return_write'], Response::HTTP_UNPROCESSABLE_ENTITY, ['json' => []]);
    }
}
