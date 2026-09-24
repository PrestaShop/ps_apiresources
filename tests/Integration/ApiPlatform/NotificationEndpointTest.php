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

class NotificationEndpointTest extends ApiTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::createApiClient(['notification_write', 'notification_read']);
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'get last elements endpoint' => [
            'GET',
            '/notifications/1/last-elements',
        ];

        yield 'update last element endpoint' => [
            'PUT',
            '/notifications/last-elements',
        ];
    }

    public function testGetNotificationLastElements(): void
    {
        $notifications = $this->getItem('/notifications/1/last-elements', ['notification_read']);

        $this->assertSame(1, $notifications['employeeId']);
        $this->assertArrayHasKey('notificationsResults', $notifications);
        $this->assertIsArray($notifications['notificationsResults']);
    }

    public function testUpdateEmployeeNotificationLastElement(): void
    {
        $return = $this->updateItem(
            '/notifications/last-elements',
            ['type' => 'order'],
            ['notification_write'],
            Response::HTTP_NO_CONTENT
        );

        // This endpoint returns an empty response and a 204 HTTP code
        $this->assertNull($return);
    }
}
