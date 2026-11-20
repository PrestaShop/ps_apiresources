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
 * The shops of a created CMS page category: without shopIds in the payload, the shops of the request context are used.
 */
class CmsPageCategoryMultiShopEndpointTest extends ApiTestCase
{
    protected const DEFAULT_SHOP_ID = 1;
    protected static int $secondShopId;

    public static function setUpBeforeClass(): void
    {
        if (self::isVersionUnder('9.2.0')) {
            static::markTestSkipped('The context default values require PrestaShop 9.2.0');

            return;
        }

        parent::setUpBeforeClass();
        LanguageResetter::resetLanguages();
        self::addLanguageByLocale('fr-FR');
        ShopResetter::resetShops();
        ConfigurationResetter::resetConfiguration();
        self::restoreCmsCategoryTables();

        self::updateConfiguration(MultistoreConfig::FEATURE_STATUS, 1);
        self::updateConfiguration('PS_ADMIN_API_FORCE_DEBUG_SECURED', 0);
        self::$secondShopId = self::addShop('Second shop for CMS page category tests', self::DEFAULT_SHOP_ID);
        self::createApiClient(['cms_page_category_write', 'cms_page_category_read']);

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
        self::restoreCmsCategoryTables();
    }

    protected static function restoreCmsCategoryTables(): void
    {
        DatabaseDump::restoreTables(['cms_category', 'cms_category_lang', 'cms_category_shop']);
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'create endpoint' => ['POST', '/cms-pages/categories'];
    }

    public function testShopIdsDefaultToTheContextShops(): void
    {
        $contextOptions = [
            'extra' => [
                'parameters' => [
                    'shopId' => self::$secondShopId,
                ],
            ],
        ];
        $cmsPageCategory = $this->createItem('/cms-pages/categories', $this->getPayload(), ['cms_page_category_write'], Response::HTTP_CREATED, $contextOptions);
        $this->assertSame([self::$secondShopId], $cmsPageCategory['shopIds']);

        $readBack = $this->getItem('/cms-pages/categories/' . $cmsPageCategory['cmsPageCategoryId'], ['cms_page_category_read'], Response::HTTP_OK, $contextOptions);
        $this->assertSame([self::$secondShopId], $readBack['shopIds']);
    }

    protected function getPayload(): array
    {
        return [
            'names' => [
                'en-US' => 'Multishop category',
                'fr-FR' => 'Catégorie multiboutique',
            ],
            'linkRewrites' => [
                'en-US' => 'multishop-category',
                'fr-FR' => 'categorie-multiboutique',
            ],
            'parentId' => 1,
        ];
    }
}
