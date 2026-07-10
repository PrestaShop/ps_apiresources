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
        yield 'set ranges endpoint' => ['PATCH', '/carriers/1/ranges'];
        yield 'set tax rule group endpoint' => ['PATCH', '/carriers/1/tax-rule-group'];
    }

    public function testAddCarrierForFirstShop(): int
    {
        $carrier = $this->createItem('/carriers', [
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
        ], ['carrier_write'], Response::HTTP_CREATED, [
            'extra' => [
                'parameters' => [
                    'shopId' => self::DEFAULT_SHOP_ID,
                ],
            ],
        ]);

        return $carrier['carrierId'];
    }

    /**
     * @depends testAddCarrierForFirstShop
     */
    public function testSetCarrierRanges(int $carrierId): int
    {
        $response = $this->partialUpdateItem(
            '/carriers/' . $carrierId . '/ranges',
            [
                'ranges' => [
                    ['zoneId' => 1, 'rangeFrom' => 0, 'rangeTo' => 10, 'rangePrice' => '5.00'],
                    ['zoneId' => 1, 'rangeFrom' => 10, 'rangeTo' => 20, 'rangePrice' => '8.00'],
                ],
            ],
            ['carrier_write'],
            Response::HTTP_NO_CONTENT,
            [
                'extra' => [
                    'parameters' => [
                        'allShops' => true,
                    ],
                ],
            ]
        );
        $this->assertNull($response);

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
                    ['zoneId' => 1, 'rangeFrom' => 0, 'rangeTo' => 10, 'rangePrice' => '5.00'],
                    ['zoneId' => 1, 'rangeFrom' => 5, 'rangeTo' => 15, 'rangePrice' => '8.00'],
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

        $response = $this->partialUpdateItem(
            '/carriers/' . $carrierId . '/tax-rule-group',
            ['taxRuleGroupId' => $taxRulesGroupId],
            ['carrier_write'],
            Response::HTTP_NO_CONTENT,
            [
                'extra' => [
                    'parameters' => [
                        'allShops' => true,
                    ],
                ],
            ]
        );
        $this->assertNull($response);

        $carrier = $this->getItem('/carriers/' . $carrierId, ['carrier_read'], Response::HTTP_OK, [
            'extra' => [
                'parameters' => [
                    'shopId' => self::DEFAULT_SHOP_ID,
                ],
            ],
        ]);
        $this->assertEquals($taxRulesGroupId, $carrier['taxRuleGroupId']);

        return $carrierId;
    }

    /**
     * @depends testSetCarrierTaxRuleGroup
     */
    public function testSetCarrierTaxRuleGroupInvalid(int $carrierId): void
    {
        $this->partialUpdateItem(
            '/carriers/' . $carrierId . '/tax-rule-group',
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
