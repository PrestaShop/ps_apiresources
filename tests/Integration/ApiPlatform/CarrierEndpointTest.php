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

class CarrierEndpointTest extends ApiTestCase
{
    public static \Carrier $carrier1;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::createApiClient(['carrier_read', 'carrier_write']);

        self::$carrier1 = new \Carrier();
        self::$carrier1->name = 'Test Carrier 1';
        self::$carrier1->delay = [1 => 'Delivery in 2-3 days'];
        self::$carrier1->active = true;
        self::$carrier1->is_free = false;
        self::$carrier1->shipping_handling = false;
        self::$carrier1->range_behavior = 0;
        self::$carrier1->is_module = false;
        self::$carrier1->save();
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        DatabaseDump::restoreTables(['carrier', 'carrier_lang', 'carrier_shop', 'carrier_group', 'carrier_zone', 'carrier_tax_rules_group_shop']);
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'get endpoint' => [
            'GET',
            '/carriers/1',
        ];

        yield 'toggle status endpoint' => [
            'PUT',
            '/carriers/1/toggle-status',
        ];

        yield 'toggle is free endpoint' => [
            'PUT',
            '/carriers/1/toggle-is-free',
        ];
    }

    public function testGetCarrier(): int
    {
        $carrierId = (int) self::$carrier1->id;

        $carrier = $this->getItem('/carriers/' . $carrierId, ['carrier_read']);
        $this->assertEquals($carrierId, $carrier['carrierId']);
        $this->assertTrue($carrier['active']);
        $this->assertFalse($carrier['isFree']);

        return $carrierId;
    }

    /**
     * @depends testGetCarrier
     */
    public function testToggleCarrierStatus(int $carrierId): int
    {
        $this->updateItem('/carriers/' . $carrierId . '/toggle-status', [], ['carrier_write'], Response::HTTP_NO_CONTENT);
        $carrier = $this->getItem('/carriers/' . $carrierId, ['carrier_read']);
        $this->assertFalse($carrier['active']);

        // Toggle back
        $this->updateItem('/carriers/' . $carrierId . '/toggle-status', [], ['carrier_write'], Response::HTTP_NO_CONTENT);
        $carrier = $this->getItem('/carriers/' . $carrierId, ['carrier_read']);
        $this->assertTrue($carrier['active']);

        return $carrierId;
    }

    /**
     * @depends testToggleCarrierStatus
     */
    public function testToggleCarrierIsFree(int $carrierId): int
    {
        $this->updateItem('/carriers/' . $carrierId . '/toggle-is-free', [], ['carrier_write'], Response::HTTP_NO_CONTENT);
        $carrier = $this->getItem('/carriers/' . $carrierId, ['carrier_read']);
        $this->assertTrue($carrier['isFree']);

        // Toggle back
        $this->updateItem('/carriers/' . $carrierId . '/toggle-is-free', [], ['carrier_write'], Response::HTTP_NO_CONTENT);
        $carrier = $this->getItem('/carriers/' . $carrierId, ['carrier_read']);
        $this->assertFalse($carrier['isFree']);

        return $carrierId;
    }

    /**
     * @depends testToggleCarrierIsFree
     */
    public function testToggleCarrierStatusNotFound(int $carrierId): void
    {
        $this->updateItem('/carriers/99999/toggle-status', [], ['carrier_write'], Response::HTTP_NOT_FOUND);
    }

    /**
     * @depends testToggleCarrierStatusNotFound
     */
    public function testToggleCarrierIsFreeNotFound(): void
    {
        $this->updateItem('/carriers/99999/toggle-is-free', [], ['carrier_write'], Response::HTTP_NOT_FOUND);
    }
}
