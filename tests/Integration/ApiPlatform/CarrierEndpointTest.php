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

use PrestaShop\PrestaShop\Core\Domain\Carrier\ValueObject\OutOfRangeBehavior;
use PrestaShop\PrestaShop\Core\Domain\Carrier\ValueObject\ShippingMethod;
use Symfony\Component\HttpFoundation\Response;
use Tests\Resources\DatabaseDump;
use Tests\Resources\Resetter\LanguageResetter;

class CarrierEndpointTest extends ApiTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        LanguageResetter::resetLanguages();
        self::addLanguageByLocale('fr-FR');
        self::createApiClient(['carrier_read', 'carrier_write', 'tax_rules_group_write']);
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        LanguageResetter::resetLanguages();
        DatabaseDump::restoreTables([
            'carrier',
            'carrier_group',
            'carrier_lang',
            'carrier_shop',
            'carrier_tax_rules_group_shop',
            'carrier_zone',
            'range_price',
            'range_weight',
            'delivery',
            'module_carrier',
            'tax_rules_group',
            'tax_rules_group_shop',
        ]);
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'create endpoint' => ['POST', '/carriers'];
        yield 'get endpoint' => ['GET', '/carriers/1'];
        yield 'patch endpoint' => ['PATCH', '/carriers/1'];
        yield 'get ranges endpoint' => ['GET', '/carriers/1/ranges'];
        yield 'set ranges endpoint' => ['PATCH', '/carriers/1/ranges'];
        yield 'set tax rule group endpoint' => ['PATCH', '/carriers/1/set-tax-rule-group'];
    }

    private function getCreatePayload(): array
    {
        return [
            'name' => 'My Carrier',
            'delays' => [
                'en-US' => '3-5 days',
                'fr-FR' => '3-5 jours',
            ],
            'grade' => 5,
            'trackingUrl' => 'http://example.com/@',
            'enabled' => true,
            'associatedGroupIds' => [1, 2, 3],
            'additionalHandlingFee' => false,
            'free' => false,
            'shippingMethod' => ShippingMethod::BY_PRICE,
            'rangeBehavior' => OutOfRangeBehavior::USE_HIGHEST_RANGE,
            'zones' => [1],
            'associatedShopIds' => [1],
            'maxWidth' => 0,
            'maxHeight' => 0,
            'maxDepth' => 0,
            'maxWeight' => 0,
        ];
    }

    public function testAddCarrier(): int
    {
        $carrier = $this->createItem('/carriers', $this->getCreatePayload(), ['carrier_write']);
        $this->assertArrayHasKey('carrierId', $carrier);
        $carrierId = $carrier['carrierId'];

        $this->assertEquals(
            [
                'carrierId' => $carrierId,
                'taxRuleGroupId' => 0,
                'position' => $carrier['position'],
                'ordersCount' => 0,
            ] + $this->getCreatePayload(),
            $carrier
        );

        return $carrierId;
    }

    /**
     * @depends testAddCarrier
     */
    public function testGetCarrier(int $carrierId): int
    {
        $carrier = $this->getItem('/carriers/' . $carrierId, ['carrier_read']);
        $this->assertEquals(
            [
                'carrierId' => $carrierId,
                'taxRuleGroupId' => 0,
                'position' => $carrier['position'],
                'ordersCount' => 0,
            ] + $this->getCreatePayload(),
            $carrier
        );

        return $carrierId;
    }

    /**
     * @depends testGetCarrier
     */
    public function testPartialUpdateCarrier(int $carrierId): int
    {
        $updatedCarrier = $this->partialUpdateItem('/carriers/' . $carrierId, [
            'name' => 'My Carrier updated',
            'enabled' => false,
            'free' => true,
        ], ['carrier_write']);

        $this->assertEquals('My Carrier updated', $updatedCarrier['name']);
        $this->assertFalse($updatedCarrier['enabled']);
        $this->assertTrue($updatedCarrier['free']);

        $fetchedCarrier = $this->getItem('/carriers/' . $carrierId, ['carrier_read']);
        $this->assertEquals($updatedCarrier, $fetchedCarrier);

        return $carrierId;
    }

    /**
     * @depends testPartialUpdateCarrier
     */
    public function testSetAndGetCarrierRanges(int $carrierId): int
    {
        $expectedRanges = [
            'carrierId' => $carrierId,
            'ranges' => [
                ['zoneId' => 1, 'rangeFrom' => 0.0, 'rangeTo' => 10.0, 'rangePrice' => 5.0],
                ['zoneId' => 1, 'rangeFrom' => 10.0, 'rangeTo' => 20.0, 'rangePrice' => 8.0],
            ],
        ];

        // The ranges are sent in the exact format the operations return, which is the point of
        // the shared format: a response can be copied as-is to build the next request
        $updatedRanges = $this->partialUpdateItem(
            '/carriers/' . $carrierId . '/ranges',
            ['ranges' => $expectedRanges['ranges']],
            ['carrier_write']
        );

        $this->assertEquals($expectedRanges, $updatedRanges);
        $this->assertEquals($expectedRanges, $this->getItem('/carriers/' . $carrierId . '/ranges', ['carrier_read']));

        return $carrierId;
    }

    /**
     * @depends testSetAndGetCarrierRanges
     */
    public function testSetCarrierRangesWithOverlappingRangesIsRejected(int $carrierId): void
    {
        $this->partialUpdateItem('/carriers/' . $carrierId . '/ranges', [
            'ranges' => [
                ['zoneId' => 1, 'rangeFrom' => 0.0, 'rangeTo' => 10.0, 'rangePrice' => 5.0],
                ['zoneId' => 1, 'rangeFrom' => 5.0, 'rangeTo' => 15.0, 'rangePrice' => 8.0],
            ],
        ], ['carrier_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /**
     * @depends testSetAndGetCarrierRanges
     */
    public function testSetCarrierTaxRuleGroup(int $carrierId): void
    {
        $taxRulesGroup = $this->createItem('/tax-rules-groups', [
            'name' => 'Carrier Tax Rules Group',
            'enabled' => true,
            'shopIds' => [1],
        ], ['tax_rules_group_write']);

        $updatedCarrier = $this->partialUpdateItem(
            '/carriers/' . $carrierId . '/set-tax-rule-group',
            ['taxRuleGroupId' => $taxRulesGroup['taxRulesGroupId']],
            ['carrier_write']
        );

        // The operation returns the full carrier, not only the modified association. The expected
        // payload is the created one plus the fields changed by testPartialUpdateCarrier.
        $expectedCarrier = array_merge($this->getCreatePayload(), [
            'carrierId' => $carrierId,
            'name' => 'My Carrier updated',
            'enabled' => false,
            'free' => true,
            'taxRuleGroupId' => $taxRulesGroup['taxRulesGroupId'],
            'position' => $updatedCarrier['position'],
            'ordersCount' => 0,
        ]);

        $this->assertEquals($expectedCarrier, $updatedCarrier);
        $this->assertEquals($expectedCarrier, $this->getItem('/carriers/' . $carrierId, ['carrier_read']));
    }

    /**
     * @depends testSetAndGetCarrierRanges
     */
    public function testSetUnknownCarrierTaxRuleGroupIsRejected(int $carrierId): void
    {
        $this->partialUpdateItem(
            '/carriers/' . $carrierId . '/set-tax-rule-group',
            ['taxRuleGroupId' => 999999],
            ['carrier_write'],
            Response::HTTP_NOT_FOUND
        );
    }

    public function testCreateInvalidCarrier(): void
    {
        $invalidPayload = array_merge($this->getCreatePayload(), [
            'name' => '',
            'zones' => [],
        ]);
        $validationErrorsResponse = $this->createItem(
            '/carriers',
            $invalidPayload,
            ['carrier_write'],
            Response::HTTP_UNPROCESSABLE_ENTITY
        );
        $this->assertIsArray($validationErrorsResponse);
        $this->assertValidationErrors([
            ['propertyPath' => 'name', 'message' => 'This value should not be blank.'],
            ['propertyPath' => 'name', 'message' => 'This value is too short. It should have 1 character or more.'],
            ['propertyPath' => 'zones', 'message' => 'This value should not be blank.'],
        ], $validationErrorsResponse);
    }
}
