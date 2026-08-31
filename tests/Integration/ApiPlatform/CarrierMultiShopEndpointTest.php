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
use PrestaShop\PrestaShop\Core\FeatureFlag\FeatureFlagManager;
use PrestaShop\PrestaShop\Core\FeatureFlag\FeatureFlagSettings;
use PrestaShop\PrestaShop\Core\Multistore\MultistoreConfig;
use Symfony\Component\HttpFoundation\Response;
use Tests\Resources\DatabaseDump;
use Tests\Resources\Resetter\ConfigurationResetter;
use Tests\Resources\Resetter\FeatureFlagResetter;
use Tests\Resources\Resetter\LanguageResetter;
use Tests\Resources\Resetter\ShopResetter;

class CarrierMultiShopEndpointTest extends ApiTestCase
{
    protected const DEFAULT_SHOP_ID = 1;
    protected static int $secondShopId;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        LanguageResetter::resetLanguages();
        self::addLanguageByLocale('fr-FR');
        ShopResetter::resetShops();
        ConfigurationResetter::resetConfiguration();

        self::updateConfiguration(MultistoreConfig::FEATURE_STATUS, 1);
        self::updateConfiguration('PS_ADMIN_API_FORCE_DEBUG_SECURED', 0);
        self::$secondShopId = self::addShop('Second shop for carrier multistore tests', self::DEFAULT_SHOP_ID);
        self::createApiClient(['carrier_read', 'carrier_write', 'tax_rules_group_write']);

        $featureFlagManager = self::getContainer()->get(FeatureFlagManager::class);
        $featureFlagManager->enable(FeatureFlagSettings::FEATURE_FLAG_ADMIN_API_MULTISTORE);
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        LanguageResetter::resetLanguages();
        ShopResetter::resetShops();
        ConfigurationResetter::resetConfiguration();
        FeatureFlagResetter::resetFeatureFlags();
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
        yield 'get ranges endpoint' => ['GET', '/carriers/1/ranges'];
        yield 'set ranges endpoint' => ['PATCH', '/carriers/1/ranges'];
        yield 'set tax rule group endpoint' => ['PATCH', '/carriers/1/set-tax-rule-group'];
    }

    private function getCreatePayload(): array
    {
        return [
            'name' => 'Multi-shop carrier',
            'delays' => ['en-US' => '3-5 days', 'fr-FR' => '3-5 jours'],
            'grade' => 5,
            'trackingUrl' => 'http://example.com/@',
            'enabled' => true,
            'associatedGroupIds' => [1, 2, 3],
            'additionalHandlingFee' => false,
            'free' => false,
            'shippingMethod' => ShippingMethod::BY_PRICE,
            'rangeBehavior' => OutOfRangeBehavior::USE_HIGHEST_RANGE,
            'zones' => [1],
            'associatedShopIds' => [self::DEFAULT_SHOP_ID, self::$secondShopId],
        ];
    }

    public function testAddCarrierForFirstShop(): int
    {
        $carrier = $this->createItem('/carriers', $this->getCreatePayload(), ['carrier_write'], Response::HTTP_CREATED, [
            'extra' => [
                'parameters' => [
                    'shopId' => self::DEFAULT_SHOP_ID,
                ],
            ],
        ]);

        return $carrier['carrierId'];
    }

    /**
     * When the payload omits the shops, the carrier is associated with the shops of the request
     * context: the ones of the selected shop scope, here a single shop.
     */
    public function testAddCarrierWithoutShopsIsAssociatedWithTheContextShops(): void
    {
        // The default values are applied by the operation, a feature that only exists since PrestaShop 9.2.0
        $this->markTestSkippedByMinVersion('9.2.0');

        $payload = array_merge($this->getCreatePayload(), [
            'name' => 'Carrier without explicit shops',
        ]);
        unset($payload['associatedShopIds']);

        $carrier = $this->createItem('/carriers', $payload, ['carrier_write'], Response::HTTP_CREATED, [
            'extra' => [
                'parameters' => [
                    'shopId' => self::$secondShopId,
                ],
            ],
        ]);

        $this->assertEquals([self::$secondShopId], $carrier['associatedShopIds']);
    }

    /**
     * The context only fills the shops when the payload omits them: a provided list always wins,
     * even when the context covers more shops.
     */
    public function testAddCarrierWithExplicitShopsIgnoresTheContextShops(): int
    {
        $this->markTestSkippedByMinVersion('9.2.0');

        // The all shops context resolves to every shop, but the payload explicitly picks the first one
        $payload = array_merge($this->getCreatePayload(), [
            'name' => 'Carrier with explicit shops',
            'associatedShopIds' => [self::DEFAULT_SHOP_ID],
        ]);

        $carrier = $this->createItem('/carriers', $payload, ['carrier_write'], Response::HTTP_CREATED, [
            'extra' => [
                'parameters' => [
                    'allShops' => true,
                ],
            ],
        ]);

        $this->assertEquals([self::DEFAULT_SHOP_ID], $carrier['associatedShopIds']);

        return $carrier['carrierId'];
    }

    /**
     * The context default only exists on the create operation: an update that omits the shops keeps the
     * ones already associated with the carrier, even when the context resolves to a different shop list.
     *
     * @depends testAddCarrierWithExplicitShopsIgnoresTheContextShops
     */
    public function testUpdateCarrierWithoutShopsKeepsTheAssociatedShops(int $carrierId): void
    {
        $updatedCarrier = $this->createItem('/carriers/' . $carrierId, [
            'name' => 'Carrier with explicit shops updated',
        ], ['carrier_write'], Response::HTTP_OK, [
            'extra' => [
                'parameters' => [
                    'allShops' => true,
                ],
            ],
        ]);

        $this->assertEquals([self::DEFAULT_SHOP_ID], $updatedCarrier['associatedShopIds']);
    }

    /**
     * @depends testAddCarrierForFirstShop
     */
    public function testSetCarrierRanges(int $carrierId): int
    {
        $allShopsOptions = [
            'extra' => [
                'parameters' => [
                    'allShops' => true,
                ],
            ],
        ];

        $expectedRanges = [
            'carrierId' => $carrierId,
            'ranges' => [
                ['zoneId' => 1, 'rangeFrom' => 0.0, 'rangeTo' => 10.0, 'rangePrice' => 5.0],
                ['zoneId' => 1, 'rangeFrom' => 10.0, 'rangeTo' => 20.0, 'rangePrice' => 8.0],
            ],
        ];

        $updatedRanges = $this->partialUpdateItem(
            '/carriers/' . $carrierId . '/ranges',
            ['ranges' => $expectedRanges['ranges']],
            ['carrier_write'],
            Response::HTTP_OK,
            $allShopsOptions
        );

        // The update returns the resulting ranges for the requested shop context, in the same
        // format it accepts them, like the GET operation
        $this->assertEquals($expectedRanges, $updatedRanges);
        $this->assertEquals(
            $expectedRanges,
            $this->getItem('/carriers/' . $carrierId . '/ranges', ['carrier_read'], Response::HTTP_OK, $allShopsOptions)
        );

        return $carrierId;
    }

    /**
     * @depends testSetCarrierRanges
     */
    public function testSetCarrierRangesInvalid(int $carrierId): int
    {
        $this->partialUpdateItem(
            '/carriers/' . $carrierId . '/ranges',
            [
                'ranges' => [
                    ['zoneId' => 1, 'rangeFrom' => 0.0, 'rangeTo' => 10.0, 'rangePrice' => 5.0],
                    ['zoneId' => 1, 'rangeFrom' => 5.0, 'rangeTo' => 15.0, 'rangePrice' => 8.0],
                ],
            ],
            ['carrier_write'],
            Response::HTTP_UNPROCESSABLE_ENTITY,
            [
                'extra' => [
                    'parameters' => [
                        'allShops' => true,
                    ],
                ],
            ]
        );

        return $carrierId;
    }

    /**
     * @depends testSetCarrierRangesInvalid
     */
    public function testSetCarrierTaxRuleGroup(int $carrierId): int
    {
        $taxRulesGroup = $this->createItem('/tax-rules-groups', [
            'name' => 'Carrier Tax Rules Group',
            'enabled' => true,
            'shopIds' => [self::DEFAULT_SHOP_ID],
        ], ['tax_rules_group_write'], Response::HTTP_CREATED, [
            'extra' => [
                'parameters' => [
                    'shopId' => self::DEFAULT_SHOP_ID,
                ],
            ],
        ]);
        $taxRulesGroupId = $taxRulesGroup['taxRulesGroupId'];

        $updatedCarrier = $this->partialUpdateItem(
            '/carriers/' . $carrierId . '/set-tax-rule-group',
            ['taxRuleGroupId' => $taxRulesGroupId],
            ['carrier_write'],
            Response::HTTP_OK,
            [
                'extra' => [
                    'parameters' => [
                        'allShops' => true,
                    ],
                ],
            ]
        );

        // The operation returns the full carrier, not only the modified association
        $expectedCarrier = array_merge($this->getCreatePayload(), [
            'carrierId' => $carrierId,
            'taxRuleGroupId' => $taxRulesGroupId,
            'position' => $updatedCarrier['position'],
            'ordersCount' => 0,
            'maxWidth' => 0,
            'maxHeight' => 0,
            'maxDepth' => 0,
            'maxWeight' => 0,
        ]);
        $this->assertEquals($expectedCarrier, $updatedCarrier);

        $this->assertEquals($expectedCarrier, $this->getItem('/carriers/' . $carrierId, ['carrier_read'], Response::HTTP_OK, [
            'extra' => [
                'parameters' => [
                    'shopId' => self::DEFAULT_SHOP_ID,
                ],
            ],
        ]));

        return $carrierId;
    }

    /**
     * @depends testSetCarrierTaxRuleGroup
     */
    public function testSetCarrierTaxRuleGroupInvalid(int $carrierId): void
    {
        $this->partialUpdateItem(
            '/carriers/' . $carrierId . '/set-tax-rule-group',
            ['taxRuleGroupId' => 999999],
            ['carrier_write'],
            Response::HTTP_NOT_FOUND,
            [
                'extra' => [
                    'parameters' => [
                        'allShops' => true,
                    ],
                ],
            ]
        );
    }
}
