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

use PrestaShop\PrestaShop\Core\ExtraProperty\Definition\ExtraPropertyDefinitionRepositoryInterface;
use PrestaShop\PrestaShop\Core\ExtraProperty\Definition\ExtraPropertyRegistryInterface;
use PrestaShop\PrestaShop\Core\FeatureFlag\FeatureFlagManager;
use PrestaShop\PrestaShop\Core\FeatureFlag\FeatureFlagSettings;
use PrestaShop\PrestaShop\Core\Multistore\MultistoreConfig;
use Tests\Resources\DatabaseDump;
use Tests\Resources\Resetter\ProductResetter;
use Tests\Resources\Resetter\ShopResetter;

/**
 * Multishop contract of the definitions created through the API: a SHOP-scoped property follows the
 * request's shop context (single shop write stays on its shop, allShops fans out, per-shop reads), and
 * a definition restricted to some shops (shopIds) only exists on those shops — on the entity endpoints
 * and in the definition list.
 */
class ExtraPropertyMultiShopEndpointTest extends ApiTestCase
{
    private const DEFINITION_READ = 'extra_property_definition_read';
    private const DEFINITION_WRITE = 'extra_property_definition_write';
    private const PRODUCT_READ = 'product_read';
    private const PRODUCT_WRITE = 'product_write';
    private const DEFINITIONS_ENDPOINT = '/extra-property-definitions';

    private const PRODUCT_ID = 1;
    private const DEFAULT_SHOP_ID = 1;
    private const CORE_KEY = '_core';

    private const PROPERTY_PREFIX = 'apims_';
    private const SHOP_NOTE = self::PROPERTY_PREFIX . 'shop_note';
    private const RESTRICTED = self::PROPERTY_PREFIX . 'restricted';

    private static int $secondShopId;
    private static bool $definitionsCreated = false;
    private static ?int $restrictedDefinitionId = null;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        if (!self::isVersionAtLeast('9.2.0')) {
            return;
        }

        self::cleanDefinitions();

        // The Admin API refuses to run on a multistore installation unless the dedicated feature
        // flag is enabled (every route 404s otherwise).
        self::updateConfiguration(MultistoreConfig::FEATURE_STATUS, 1);
        self::getContainer()->get(FeatureFlagManager::class)->enable(FeatureFlagSettings::FEATURE_FLAG_ADMIN_API_MULTISTORE);

        $defaultGroupId = (int) \Shop::getGroupFromShop(self::DEFAULT_SHOP_ID, true);
        self::$secondShopId = self::addShop('Extra Property Definition API Shop 2', $defaultGroupId);

        // Associate the tested product to both shops so per-shop reads/writes are valid on each.
        $product = new \Product(self::PRODUCT_ID);
        $product->id_shop_list = [self::DEFAULT_SHOP_ID, self::$secondShopId];
        $product->save();
    }

    public static function tearDownAfterClass(): void
    {
        if (self::isVersionAtLeast('9.2.0')) {
            self::cleanDefinitions();
            self::getContainer()->get(FeatureFlagManager::class)->disable(FeatureFlagSettings::FEATURE_FLAG_ADMIN_API_MULTISTORE);
            ProductResetter::resetProducts();
            ShopResetter::resetShops();
        }
        parent::tearDownAfterClass();
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->markTestSkippedByMinVersion('9.2.0');
        $this->ensureDefinitions();

        foreach (['product_extra', 'product_extra_shop'] as $table) {
            if ($this->tableExists($table)) {
                \Db::getInstance()->execute('DELETE FROM `' . _DB_PREFIX_ . $table . '`');
            }
        }
    }

    /**
     * Under multistore every request must carry a shop context, the definition endpoints included.
     */
    private function ensureDefinitions(): void
    {
        if (self::$definitionsCreated) {
            return;
        }

        // SHOP-scoped: one value per shop, stored in product_extra_shop.
        $this->createItem(self::DEFINITIONS_ENDPOINT . '?shopId=' . self::DEFAULT_SHOP_ID, [
            'entityName' => 'product',
            'propertyName' => self::SHOP_NOTE,
            'type' => 'string',
            'scope' => 'shop',
            'associatedApis' => ['/products/{productId}'],
        ], [self::DEFINITION_WRITE]);

        // COMMON but restricted to the second shop: a single shared value, only visible there.
        $restricted = $this->createItem(self::DEFINITIONS_ENDPOINT . '?shopId=' . self::DEFAULT_SHOP_ID, [
            'entityName' => 'product',
            'propertyName' => self::RESTRICTED,
            'type' => 'string',
            'scope' => 'common',
            'defaultValue' => 'restricted-default',
            'associatedApis' => ['/products/{productId}'],
            'shopIds' => [self::$secondShopId],
        ], [self::DEFINITION_WRITE]);
        $this->assertSame([self::$secondShopId], $restricted['shopIds']);
        self::$restrictedDefinitionId = $restricted['extraPropertyDefinitionId'];

        self::$definitionsCreated = true;
    }

    private static function cleanDefinitions(): void
    {
        $registry = self::getContainer()->get(ExtraPropertyRegistryInterface::class);
        $repository = self::getContainer()->get(ExtraPropertyDefinitionRepositoryInterface::class);
        foreach ($repository->getAllDefinitions() as $definition) {
            if (str_starts_with($definition->getPropertyName(), self::PROPERTY_PREFIX)) {
                $registry->unregister($definition, true);
            }
        }
        self::$definitionsCreated = false;
        self::$restrictedDefinitionId = null;

        DatabaseDump::restoreTables(['extra_property_definition', 'extra_property_definition_shop']);
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'get product endpoint' => ['GET', '/products/' . self::PRODUCT_ID . '?shopId=' . self::DEFAULT_SHOP_ID];
        yield 'list definitions endpoint' => ['GET', self::DEFINITIONS_ENDPOINT . '?shopId=' . self::DEFAULT_SHOP_ID];
    }

    public function testShopScopedValuesFollowTheShopContext(): void
    {
        // A single-shop write stays on its shop.
        $patched = $this->partialUpdateItem(
            '/products/' . self::PRODUCT_ID . '?shopId=' . self::$secondShopId,
            ['extraProperties' => [self::CORE_KEY => [self::SHOP_NOTE => 'only-shop-2']]],
            [self::PRODUCT_WRITE]
        );
        $this->assertSame('only-shop-2', $patched['extraProperties'][self::CORE_KEY][self::SHOP_NOTE]);

        $secondShopProduct = $this->getItem('/products/' . self::PRODUCT_ID . '?shopId=' . self::$secondShopId, [self::PRODUCT_READ]);
        $this->assertSame('only-shop-2', $secondShopProduct['extraProperties'][self::CORE_KEY][self::SHOP_NOTE]);

        $defaultShopProduct = $this->getItem('/products/' . self::PRODUCT_ID . '?shopId=' . self::DEFAULT_SHOP_ID, [self::PRODUCT_READ]);
        $this->assertNull($defaultShopProduct['extraProperties'][self::CORE_KEY][self::SHOP_NOTE]);

        // An all-shops write fans out to every shop the product is associated with.
        $this->partialUpdateItem(
            '/products/' . self::PRODUCT_ID . '?allShops',
            ['extraProperties' => [self::CORE_KEY => [self::SHOP_NOTE => 'everywhere']]],
            [self::PRODUCT_WRITE]
        );
        foreach ([self::DEFAULT_SHOP_ID, self::$secondShopId] as $shopId) {
            $product = $this->getItem('/products/' . self::PRODUCT_ID . '?shopId=' . $shopId, [self::PRODUCT_READ]);
            $this->assertSame('everywhere', $product['extraProperties'][self::CORE_KEY][self::SHOP_NOTE], 'shop ' . $shopId);
        }
    }

    public function testRestrictedDefinitionOnlyExistsOnItsShops(): void
    {
        // Entity endpoint: the property is absent on the default shop, present (defaulted) on the second.
        $defaultShopProduct = $this->getItem('/products/' . self::PRODUCT_ID . '?shopId=' . self::DEFAULT_SHOP_ID, [self::PRODUCT_READ]);
        $this->assertArrayNotHasKey(self::RESTRICTED, $defaultShopProduct['extraProperties'][self::CORE_KEY]);

        $secondShopProduct = $this->getItem('/products/' . self::PRODUCT_ID . '?shopId=' . self::$secondShopId, [self::PRODUCT_READ]);
        $this->assertSame('restricted-default', $secondShopProduct['extraProperties'][self::CORE_KEY][self::RESTRICTED]);

        // Definition list: follows the shop context, everything under allShops.
        $defaultShopList = $this->listItems(self::DEFINITIONS_ENDPOINT . '?shopId=' . self::DEFAULT_SHOP_ID, [self::DEFINITION_READ], ['propertyName' => self::PROPERTY_PREFIX]);
        $this->assertSame([self::SHOP_NOTE], array_column($defaultShopList['items'], 'propertyName'));

        $secondShopList = $this->listItems(self::DEFINITIONS_ENDPOINT . '?shopId=' . self::$secondShopId, [self::DEFINITION_READ], ['propertyName' => self::PROPERTY_PREFIX]);
        $this->assertEqualsCanonicalizing([self::SHOP_NOTE, self::RESTRICTED], array_column($secondShopList['items'], 'propertyName'));

        $allShopsList = $this->listItems(self::DEFINITIONS_ENDPOINT . '?allShops', [self::DEFINITION_READ], ['propertyName' => self::PROPERTY_PREFIX]);
        $this->assertSame(2, $allShopsList['totalItems']);

        // An empty shopIds reverts to the fallback (no restriction): the property shows up everywhere.
        $reverted = $this->partialUpdateItem(
            self::DEFINITIONS_ENDPOINT . '/' . self::$restrictedDefinitionId . '?shopId=' . self::DEFAULT_SHOP_ID,
            ['shopIds' => []],
            [self::DEFINITION_WRITE]
        );
        $this->assertNull($reverted['shopIds']);

        $defaultShopProduct = $this->getItem('/products/' . self::PRODUCT_ID . '?shopId=' . self::DEFAULT_SHOP_ID, [self::PRODUCT_READ]);
        $this->assertSame('restricted-default', $defaultShopProduct['extraProperties'][self::CORE_KEY][self::RESTRICTED]);

        // Restore the restriction for the other tests / a re-run.
        $restored = $this->partialUpdateItem(
            self::DEFINITIONS_ENDPOINT . '/' . self::$restrictedDefinitionId . '?shopId=' . self::DEFAULT_SHOP_ID,
            ['shopIds' => [self::$secondShopId]],
            [self::DEFINITION_WRITE]
        );
        $this->assertSame([self::$secondShopId], $restored['shopIds']);
    }

    private function tableExists(string $table): bool
    {
        return (int) \Db::getInstance()->getValue(sprintf(
            'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = "%s"',
            pSQL(_DB_PREFIX_ . $table)
        )) > 0;
    }
}
