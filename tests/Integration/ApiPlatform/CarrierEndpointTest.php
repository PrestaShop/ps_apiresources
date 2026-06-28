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
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        DatabaseDump::restoreTables(['carrier', 'carrier_lang', 'carrier_group', 'carrier_zone', 'carrier_shop']);
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        DatabaseDump::restoreTables(['carrier', 'carrier_lang', 'carrier_group', 'carrier_zone', 'carrier_shop']);
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'create endpoint' => ['POST', '/carriers', 'multipart/form-data'];
        yield 'get endpoint' => ['GET', '/carriers/1'];
        yield 'update endpoint' => ['PATCH', '/carriers/1', 'multipart/form-data'];
        yield 'delete endpoint' => ['DELETE', '/carriers/1'];
    }

    public function testGetCarrier(): void
    {
        $carrier = $this->getItem('/carriers/2', ['carrier_read']);

        $this->assertSame(2, $carrier['carrierId']);
        $this->assertSame('My carrier', $carrier['name']);
        $this->assertArrayHasKey('delay', $carrier);
        $this->assertArrayHasKey('grade', $carrier);
        $this->assertArrayHasKey('zones', $carrier);
        $this->assertArrayHasKey('taxRuleGroupId', $carrier);
    }

    public function testAddCarrier(): int
    {
        $logo = $this->prepareUploadedFile(__DIR__ . '/../../Resources/assets/image/Hummingbird_cushion.jpg');

        $carrier = $this->requestApi('POST', '/carriers', null, ['carrier_write'], Response::HTTP_CREATED, [
            'headers' => [
                'content-type' => 'multipart/form-data',
            ],
            'extra' => [
                'files' => [
                    'logoFile' => $logo,
                ],
                'parameters' => [
                    'name' => 'API carrier',
                    'delay' => ['en-US' => 'Delivered fast'],
                    'grade' => '5',
                    'trackingUrl' => 'https://tracking.example.com/@',
                    'active' => '1',
                    'associatedGroupIds' => ['3'],
                    'hasAdditionalHandlingFee' => '0',
                    'isFree' => '1',
                    'shippingMethod' => '2',
                    'rangeBehavior' => '0',
                    'zones' => ['1'],
                    'associatedShopIds' => ['1'],
                    'maxWidth' => '0',
                    'maxHeight' => '0',
                    'maxDepth' => '0',
                    'maxWeight' => '0',
                ],
            ],
        ]);

        $this->assertArrayHasKey('carrierId', $carrier);
        $this->assertIsInt($carrier['carrierId']);
        $this->assertGreaterThan(0, $carrier['carrierId']);
        $this->assertSame('API carrier', $carrier['name']);

        return $carrier['carrierId'];
    }

    /**
     * @depends testAddCarrier
     */
    public function testUpdateCarrier(int $carrierId): int
    {
        $carrier = $this->requestApi('PATCH', '/carriers/' . $carrierId, null, ['carrier_write'], Response::HTTP_OK, [
            'headers' => [
                'content-type' => 'multipart/form-data',
            ],
            'extra' => [
                'parameters' => [
                    'name' => 'API carrier updated',
                    'grade' => '7',
                ],
            ],
        ]);

        $this->assertSame('API carrier updated', $carrier['name']);
        $this->assertSame(7, $carrier['grade']);

        return $carrierId;
    }

    /**
     * @depends testUpdateCarrier
     */
    public function testDeleteCarrier(int $carrierId): void
    {
        $this->requestApi('DELETE', '/carriers/' . $carrierId, null, ['carrier_write'], Response::HTTP_NO_CONTENT);
        $this->getItem('/carriers/' . $carrierId, ['carrier_read'], Response::HTTP_NOT_FOUND);
    }
}
