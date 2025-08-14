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
 * @author    PrestaShop SA and Contributors
 * @copyright Since 2007 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License version 3.0
 */

declare(strict_types=1);

namespace PsApiResourcesTest\Integration\ApiPlatform;

use Symfony\Component\HttpFoundation\Response;

class OrderEndpointTest extends ApiTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        // Pre-create an API Client with needed scopes to reduce token creations
        self::createApiClient(['order_read', 'order_write']);
    }

    public function getProtectedEndpoints(): iterable
    {
        yield 'list orders' => [
            'GET',
            '/orders',
        ];
    }

    public function testGetOrderNotFound(): void
    {
        $this->getItem('/order/999999', ['order_read'], Response::HTTP_NOT_FOUND);
    }

    public function testPatchStatusOrderNotFound(): void
    {
        $this->partialUpdateItem('/order/999999/status', [
            'statusId' => 1,
        ], ['order_write'], Response::HTTP_NOT_FOUND);
    }

    public function testPatchTrackingOrderNotFound(): void
    {
        $this->partialUpdateItem('/order/999999/tracking', [
            'number' => 'TRACK-001',
        ], ['order_write'], Response::HTTP_NOT_FOUND);
    }
}


