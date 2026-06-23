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

class OrderMessageEndpointTest extends ApiTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::resetTables();
        self::createApiClient(['order_message_read', 'order_message_write']);
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        self::resetTables();
    }

    protected static function resetTables(): void
    {
        DatabaseDump::restoreTables([
            'order_message',
            'order_message_lang',
        ]);
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'get endpoint' => [
            'GET',
            '/order-messages/1',
        ];

        yield 'create endpoint' => [
            'POST',
            '/order-messages',
        ];

        yield 'patch endpoint' => [
            'PATCH',
            '/order-messages/1',
        ];

        yield 'list endpoint' => [
            'GET',
            '/order-messages',
        ];
    }

    public function testAddOrderMessage(): int
    {
        $postData = [
            'names' => [
                'en-US' => 'Order message EN',
                'fr-FR' => 'Order message FR',
            ],
            'messages' => [
                'en-US' => 'Message content EN',
                'fr-FR' => 'Message content FR',
            ],
        ];

        $orderMessage = $this->createItem('/order-messages', $postData, ['order_message_write']);
        $this->assertArrayHasKey('orderMessageId', $orderMessage);
        $orderMessageId = $orderMessage['orderMessageId'];

        $this->assertSame($postData['names'], $orderMessage['names']);
        $this->assertSame($postData['messages'], $orderMessage['messages']);

        return $orderMessageId;
    }

    /**
     * @depends testAddOrderMessage
     */
    public function testGetOrderMessage(int $orderMessageId): int
    {
        $orderMessage = $this->getItem('/order-messages/' . $orderMessageId, ['order_message_read']);
        $this->assertEquals($orderMessageId, $orderMessage['orderMessageId']);
        $this->assertArrayHasKey('names', $orderMessage);
        $this->assertArrayHasKey('messages', $orderMessage);

        return $orderMessageId;
    }

    /**
     * @depends testGetOrderMessage
     */
    public function testPartialUpdateOrderMessage(int $orderMessageId): int
    {
        $patchData = [
            'names' => [
                'en-US' => 'Updated message EN',
                'fr-FR' => 'Updated message FR',
            ],
            'messages' => [
                'en-US' => 'Updated content EN',
                'fr-FR' => 'Updated content FR',
            ],
        ];

        $updatedOrderMessage = $this->partialUpdateItem('/order-messages/' . $orderMessageId, $patchData, ['order_message_write']);
        $this->assertSame($patchData['names'], $updatedOrderMessage['names']);
        $this->assertSame($patchData['messages'], $updatedOrderMessage['messages']);

        // We check that when we GET the item it is updated as expected
        $orderMessage = $this->getItem('/order-messages/' . $orderMessageId, ['order_message_read']);
        $this->assertSame($patchData['names'], $orderMessage['names']);
        $this->assertSame($patchData['messages'], $orderMessage['messages']);

        return $orderMessageId;
    }

    /**
     * @depends testPartialUpdateOrderMessage
     */
    public function testListOrderMessages(int $orderMessageId): int
    {
        $paginatedOrderMessages = $this->listItems('/order-messages?orderBy=orderMessageId&sortOrder=desc', ['order_message_read']);
        $this->assertGreaterThanOrEqual(1, $paginatedOrderMessages['totalItems']);
        $this->assertEquals('orderMessageId', $paginatedOrderMessages['orderBy']);

        $firstOrderMessage = $paginatedOrderMessages['items'][0];
        $this->assertEquals($orderMessageId, $firstOrderMessage['orderMessageId']);

        return $orderMessageId;
    }

    /**
     * @depends testListOrderMessages
     */
    public function testDeleteOrderMessage(int $orderMessageId): void
    {
        $return = $this->deleteItem('/order-messages/' . $orderMessageId, ['order_message_write']);
        // This endpoint returns an empty response and a 204 HTTP code
        $this->assertNull($return);

        // Getting the item should result in a 404 now
        $this->getItem('/order-messages/' . $orderMessageId, ['order_message_read'], Response::HTTP_NOT_FOUND);
    }

    /**
     * @depends testDeleteOrderMessage
     */
    public function testBulkDeleteOrderMessages(): void
    {
        $bulkIds = [];
        foreach (['A', 'B'] as $suffix) {
            $created = $this->createItem('/order-messages', [
                'names' => ['en-US' => 'Bulk message ' . $suffix],
                'messages' => ['en-US' => 'Bulk content ' . $suffix],
            ], ['order_message_write']);
            $bulkIds[] = $created['orderMessageId'];
        }

        $this->bulkDeleteItems('/order-messages/bulk-delete', [
            'orderMessageIds' => $bulkIds,
        ], ['order_message_write']);

        // Assert the provided order messages have been removed
        foreach ($bulkIds as $orderMessageId) {
            $this->getItem('/order-messages/' . $orderMessageId, ['order_message_read'], Response::HTTP_NOT_FOUND);
        }
    }

    public function testInvalidOrderMessage(): void
    {
        $invalidData = [
            'names' => [
                'fr-FR' => 'Nom FR',
            ],
            'messages' => [
                'fr-FR' => 'Message FR',
            ],
        ];

        $validationErrorsResponse = $this->createItem('/order-messages', $invalidData, ['order_message_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertIsArray($validationErrorsResponse);

        $this->assertValidationErrors([
            [
                'propertyPath' => 'names',
                'message' => 'The field names is required at least in your default language.',
            ],
            [
                'propertyPath' => 'messages',
                'message' => 'The field messages is required at least in your default language.',
            ],
        ], $validationErrorsResponse);
    }
}
