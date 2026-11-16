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

use PrestaShop\PrestaShop\Core\FeatureFlag\FeatureFlagManager;
use PrestaShop\PrestaShop\Core\FeatureFlag\FeatureFlagSettings;
use PrestaShop\PrestaShop\Core\Multistore\MultistoreConfig;
use Symfony\Component\HttpFoundation\Response;
use Tests\Resources\DatabaseDump;
use Tests\Resources\Resetter\ConfigurationResetter;
use Tests\Resources\Resetter\FeatureFlagResetter;
use Tests\Resources\Resetter\LanguageResetter;
use Tests\Resources\Resetter\ShopResetter;

/**
 * The shops of a store: the shops of the request context are only a default value for shopIds, a sent list replaces
 * the association.
 */
class StoreMultiShopEndpointTest extends ApiTestCase
{
    protected const DEFAULT_SHOP_ID = 1;
    protected static int $secondShopId;

    public static function setUpBeforeClass(): void
    {
        if (self::isVersionUnder('9.3.0')) {
            static::markTestSkipped('The store commands exist from PrestaShop 9.3.0 (PrestaShop#41414)');

            return;
        }

        parent::setUpBeforeClass();
        LanguageResetter::resetLanguages();
        self::addLanguageByLocale('fr-FR');
        ShopResetter::resetShops();
        ConfigurationResetter::resetConfiguration();
        self::restoreStoreTables();

        self::updateConfiguration(MultistoreConfig::FEATURE_STATUS, 1);
        self::updateConfiguration('PS_ADMIN_API_FORCE_DEBUG_SECURED', 0);
        self::$secondShopId = self::addShop('Second shop for store tests', self::DEFAULT_SHOP_ID);
        self::createApiClient(['store_write', 'store_read']);

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
        self::restoreStoreTables();
    }

    protected static function restoreStoreTables(): void
    {
        DatabaseDump::restoreTables(['store', 'store_lang', 'store_shop']);
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'create endpoint' => ['POST', '/stores'];
        yield 'update endpoint' => ['PATCH', '/stores/1'];
    }

    public function testShopIdsDefaultToTheContextShops(): void
    {
        $store = $this->createItem('/stores', $this->getPayload(), ['store_write'], Response::HTTP_CREATED, $this->inShop(self::$secondShopId));
        $this->assertSame([self::$secondShopId], $store['shopIds']);
        $this->assertEquals($store, $this->getItem('/stores/' . $store['storeId'], ['store_read'], Response::HTTP_OK, $this->inShop(self::$secondShopId)));
    }

    public function testShopIdsOfThePayloadAreKept(): void
    {
        // Created from the default shop for the second shop only
        $store = $this->createItem('/stores', $this->getPayload() + ['shopIds' => [self::$secondShopId]], ['store_write'], Response::HTTP_CREATED, $this->inShop(self::DEFAULT_SHOP_ID));
        $this->assertSame([self::$secondShopId], $store['shopIds']);
        $this->assertEquals($store, $this->getStore($store['storeId']));

        // A sent list replaces the association
        $updated = $this->partialUpdateItem('/stores/' . $store['storeId'], ['shopIds' => [self::DEFAULT_SHOP_ID, self::$secondShopId]], ['store_write'], Response::HTTP_OK, $this->inShop(self::DEFAULT_SHOP_ID));
        $this->assertEqualsCanonicalizing([self::DEFAULT_SHOP_ID, self::$secondShopId], $updated['shopIds']);
        $this->assertEquals($updated, $this->getStore($store['storeId']));

        $updated = $this->partialUpdateItem('/stores/' . $store['storeId'], ['shopIds' => [self::$secondShopId]], ['store_write'], Response::HTTP_OK, $this->inShop(self::DEFAULT_SHOP_ID));
        $this->assertSame([self::$secondShopId], $updated['shopIds']);
        $this->assertEquals($updated, $this->getStore($store['storeId']));

        // An update without shopIds keeps the association: the legacy model only adds the shop of the context back
        // in a single shop context, which a multistore request does not set
        $updated = $this->partialUpdateItem('/stores/' . $store['storeId'], ['city' => 'Lille'], ['store_write'], Response::HTTP_OK, $this->inShop(self::DEFAULT_SHOP_ID));
        $this->assertSame('Lille', $updated['city']);
        $this->assertSame([self::$secondShopId], $updated['shopIds']);
        $this->assertEquals($updated, $this->getStore($store['storeId']));
    }

    /**
     * @return array<string, mixed>
     */
    private function getStore(int $storeId): array
    {
        return $this->getItem('/stores/' . $storeId, ['store_read'], Response::HTTP_OK, $this->inShop(self::$secondShopId));
    }

    /**
     * @return array<string, mixed>
     */
    private function inShop(int $shopId): array
    {
        return [
            'extra' => [
                'parameters' => [
                    'shopId' => $shopId,
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function getPayload(): array
    {
        return [
            'names' => [
                'en-US' => 'Multishop store',
                'fr-FR' => 'Magasin multiboutique',
            ],
            'addresses' => [
                'en-US' => '1 store street',
                'fr-FR' => '1 rue du magasin',
            ],
            'countryId' => (int) \Db::getInstance()->getValue('SELECT id_country FROM `' . _DB_PREFIX_ . 'country` WHERE iso_code = "FR"'),
            'city' => 'Rinxent',
            'latitude' => 50.8046176,
            'longitude' => 1.73905362,
        ];
    }
}
