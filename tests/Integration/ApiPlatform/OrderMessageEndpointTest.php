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
        DatabaseDump::restoreTables(['order_message', 'order_message_lang']);
        self::createApiClient(['order_message_write', 'order_message_read']);
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        DatabaseDump::restoreTables(['order_message', 'order_message_lang']);
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'create endpoint' => [
            'POST',
            '/order-messages',
        ];

        yield 'get endpoint' => [
            'GET',
            '/order-messages/1',
        ];

        yield 'update endpoint' => [
            'PATCH',
            '/order-messages/1',
        ];

        yield 'delete endpoint' => [
            'DELETE',
            '/order-messages/1',
        ];

        yield 'bulk delete endpoint' => [
            'DELETE',
            '/order-messages/bulk-delete',
        ];
    }

    public function testAddOrderMessage(): int
    {
        $orderMessage = $this->createItem('/order-messages', [
            'names' => [
                'en-US' => 'My Order Message EN',
                'fr-FR' => 'My Order Message FR',
            ],
            'messages' => [
                'en-US' => 'Message body EN',
                'fr-FR' => 'Message body FR',
            ],
        ], ['order_message_write']);

        $this->assertArrayHasKey('orderMessageId', $orderMessage);
        $orderMessageId = $orderMessage['orderMessageId'];
        $this->assertEquals(['orderMessageId' => $orderMessageId], $orderMessage);

        return $orderMessageId;
    }

    /**
     * @depends testAddOrderMessage
     */
    public function testGetOrderMessage(int $orderMessageId): int
    {
        $orderMessage = $this->getItem('/order-messages/' . $orderMessageId, ['order_message_read']);
        $this->assertEquals(
            [
                'orderMessageId' => $orderMessageId,
                'names' => [
                    'en-US' => 'My Order Message EN',
                    'fr-FR' => 'My Order Message FR',
                ],
                'messages' => [
                    'en-US' => 'Message body EN',
                    'fr-FR' => 'Message body FR',
                ],
            ],
            $orderMessage
        );

        return $orderMessageId;
    }

    /**
     * @depends testGetOrderMessage
     */
    public function testEditOrderMessage(int $orderMessageId): int
    {
        $updated = $this->partialUpdateItem('/order-messages/' . $orderMessageId, [
            'names' => [
                'en-US' => 'My Order Message EN Updated',
                'fr-FR' => 'My Order Message FR Updated',
            ],
        ], ['order_message_write']);
        $this->assertEquals(
            [
                'orderMessageId' => $orderMessageId,
                'names' => [
                    'en-US' => 'My Order Message EN Updated',
                    'fr-FR' => 'My Order Message FR Updated',
                ],
                'messages' => [
                    'en-US' => 'Message body EN',
                    'fr-FR' => 'Message body FR',
                ],
            ],
            $updated
        );

        return $orderMessageId;
    }

    /**
     * @depends testEditOrderMessage
     */
    public function testDeleteOrderMessage(int $orderMessageId): void
    {
        $return = $this->deleteItem('/order-messages/' . $orderMessageId, ['order_message_write']);
        // This endpoint returns an empty response and a 204 HTTP code
        $this->assertNull($return);

        // Getting the item should result in a 404 now
        $this->getItem('/order-messages/' . $orderMessageId, ['order_message_read'], Response::HTTP_NOT_FOUND);
    }

    public function testBulkDeleteOrderMessages(): void
    {
        $firstId = $this->createItem('/order-messages', [
            'names' => ['en-US' => 'Bulk OM 1 EN', 'fr-FR' => 'Bulk OM 1 FR'],
            'messages' => ['en-US' => 'Bulk body 1 EN', 'fr-FR' => 'Bulk body 1 FR'],
        ], ['order_message_write'])['orderMessageId'];
        $secondId = $this->createItem('/order-messages', [
            'names' => ['en-US' => 'Bulk OM 2 EN', 'fr-FR' => 'Bulk OM 2 FR'],
            'messages' => ['en-US' => 'Bulk body 2 EN', 'fr-FR' => 'Bulk body 2 FR'],
        ], ['order_message_write'])['orderMessageId'];

        $this->bulkDeleteItems('/order-messages/bulk-delete', [
            'orderMessageIds' => [$firstId, $secondId],
        ], ['order_message_write']);

        $this->getItem('/order-messages/' . $firstId, ['order_message_read'], Response::HTTP_NOT_FOUND);
        $this->getItem('/order-messages/' . $secondId, ['order_message_read'], Response::HTTP_NOT_FOUND);
    }
}
