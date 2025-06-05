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

use PrestaShop\PrestaShop\Core\Domain\Product\ValueObject\ProductType;
use PrestaShop\PrestaShop\Core\FeatureFlag\FeatureFlagSettings;
use PrestaShop\PrestaShop\Core\Multistore\MultistoreConfig;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Tests\Resources\Resetter\ConfigurationResetter;
use Tests\Resources\Resetter\FeatureFlagResetter;
use Tests\Resources\Resetter\LanguageResetter;
use Tests\Resources\Resetter\ProductResetter;
use Tests\Resources\Resetter\ShopResetter;
use Tests\Resources\ResourceResetter;

class ProductMultiShopEndpointTest extends ApiTestCase
{
    protected const DEFAULT_SHOP_GROUP_ID = 1;
    protected static int $secondShopGroupId;

    protected const DEFAULT_SHOP_ID = 1;
    protected static int $secondShopId;
    protected static int $thirdShopId;
    protected static int $fourthShopId;

    protected static array $defaultProductData = [
        'type' => ProductType::TYPE_STANDARD,
        'names' => [
            'en-US' => 'product name',
            'fr-FR' => 'nom produit',
        ],
        'descriptions' => [
            'en-US' => '',
            'fr-FR' => '',
        ],
        'shortDescriptions' => [
            'en-US' => '',
            'fr-FR' => '',
        ],
        'tags' => [],
        'priceTaxExcluded' => 0.0,
        'priceTaxIncluded' => 0.0,
        'ecotaxTaxExcluded' => 0.0,
        'ecotaxTaxIncluded' => 0.0,
        'taxRulesGroupId' => 9,
        'onSale' => false,
        'wholesalePrice' => 0.0,
        'unitPriceTaxExcluded' => 0.0,
        'unitPriceTaxIncluded' => 0.0,
        'unity' => '',
        'unitPriceRatio' => 0.0,
        'visibility' => 'both',
        'availableForOrder' => true,
        'onlineOnly' => false,
        'showPrice' => true,
        'condition' => 'new',
        'showCondition' => false,
        'manufacturerId' => 0,
        'isbn' => '',
        'upc' => '',
        'gtin' => '',
        'mpn' => '',
        'reference' => '',
        'width' => 0.0,
        'height' => 0.0,
        'depth' => 0.0,
        'weight' => 0.0,
        'additionalShippingCost' => 0.0,
        'carrierReferenceIds' => [],
        'deliveryTimeNoteType' => 1,
        'deliveryTimeInStockNotes' => [
            'en-US' => '',
            'fr-FR' => '',
        ],
        'deliveryTimeOutOfStockNotes' => [
            'en-US' => '',
            'fr-FR' => '',
        ],
        'metaTitles' => [
            'en-US' => '',
            'fr-FR' => '',
        ],
        'metaDescriptions' => [
            'en-US' => '',
            'fr-FR' => '',
        ],
        'linkRewrites' => [
            'en-US' => 'product-name',
            'fr-FR' => 'nom-produit',
        ],
        'redirectType' => 'default',
        'packStockType' => 3,
        'outOfStockType' => 2,
        'quantity' => 0,
        'minimalQuantity' => 1,
        'lowStockThreshold' => 0,
        'lowStockAlertEnabled' => false,
        'availableNowLabels' => [
            'en-US' => '',
            'fr-FR' => '',
        ],
        'location' => '',
        'availableLaterLabels' => [
            'en-US' => '',
            'fr-FR' => '',
        ],
        'coverThumbnailUrl' => 'http://myshop.com/img/p/en-default-cart_default.jpg',
        'active' => false,
        'shopIds' => [
            self::DEFAULT_SHOP_ID,
        ],
    ];

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        (new ResourceResetter())->backupTestModules();
        ProductResetter::resetProducts();
        LanguageResetter::resetLanguages();
        ShopResetter::resetShops();
        ConfigurationResetter::resetConfiguration();

        self::addLanguageByLocale('fr-FR');

        self::updateConfiguration(MultistoreConfig::FEATURE_STATUS, 1);
        // Disable secure protection for the tests (the configuration reset forced the default config back)
        self::updateConfiguration('PS_ADMIN_API_FORCE_DEBUG_SECURED', 0);
        self::$secondShopGroupId = self::addShopGroup('Second group');
        self::$secondShopId = self::addShop('Second shop', self::DEFAULT_SHOP_GROUP_ID);
        self::$thirdShopId = self::addShop('Third shop', self::$secondShopGroupId);
        self::$fourthShopId = self::addShop('Fourth shop', self::$secondShopGroupId);
        self::createApiClient(['product_write', 'product_read']);

        $featureFlagManager = self::getContainer()->get('PrestaShop\PrestaShop\Core\FeatureFlag\FeatureFlagManager');
        $featureFlagManager->enable(FeatureFlagSettings::FEATURE_FLAG_ADMIN_API_MULTISTORE);
    }

    public function getProtectedEndpoints(): iterable
    {
        yield 'get endpoint' => [
            'GET',
            '/product/1',
        ];
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        ProductResetter::resetProducts();
        LanguageResetter::resetLanguages();
        ShopResetter::resetShops();
        ConfigurationResetter::resetConfiguration();
        // Reset modules folder that are removed with the FR language
        (new ResourceResetter())->resetTestModules();
        FeatureFlagResetter::resetFeatureFlags();
    }

    public function testShopContextIsRequired(): void
    {
        $bearerToken = $this->getBearerToken(['product_write']);
        $response = static::createClient()->request('POST', '/product', [
            'auth_bearer' => $bearerToken,
            'json' => [
                'type' => ProductType::TYPE_STANDARD,
                'names' => [
                    'en-US' => 'product name',
                    'fr-FR' => 'nom produit',
                ],
            ],
        ]);
        self::assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
        $content = $response->getContent(false);
        $this->assertStringContainsString('Multi shop is enabled, you must specify a shop context', $content);
    }

    public function testCreateProductForFirstShop(): int
    {
        $bearerToken = $this->getBearerToken(['product_write']);
        $response = static::createClient()->request('POST', '/product', [
            'auth_bearer' => $bearerToken,
            'json' => [
                'type' => ProductType::TYPE_STANDARD,
                'names' => [
                    'en-US' => 'product name',
                    'fr-FR' => 'nom produit',
                ],
            ],
            'extra' => [
                'parameters' => [
                    'shopId' => self::DEFAULT_SHOP_ID,
                ],
            ],
        ]);
        self::assertResponseStatusCodeSame(201);

        $decodedResponse = json_decode($response->getContent(), true);
        $this->assertNotFalse($decodedResponse);
        $this->assertArrayHasKey('productId', $decodedResponse);
        $productId = $decodedResponse['productId'];
        $this->assertProductData($productId, self::$defaultProductData, $response);

        return $productId;
    }

    /**
     * @depends testCreateProductForFirstShop
     *
     * @param int $productId
     *
     * @return int
     */
    public function testGetProductForFirstShopIsSuccessful(int $productId): int
    {
        $bearerToken = $this->getBearerToken(['product_read']);
        $response = static::createClient()->request('GET', '/product/' . $productId, [
            'auth_bearer' => $bearerToken,
            'extra' => [
                'parameters' => [
                    'shopId' => self::DEFAULT_SHOP_ID,
                ],
            ],
        ]);
        self::assertResponseStatusCodeSame(200);
        $this->assertProductData($productId, self::$defaultProductData, $response);

        return $productId;
    }

    /**
     * @depends testGetProductForFirstShopIsSuccessful
     *
     * @param int $productId
     *
     * @return int
     */
    public function testGetProductForSecondShopIsFailing(int $productId): int
    {
        $bearerToken = $this->getBearerToken(['product_read']);
        $response = static::createClient()->request('GET', '/product/' . $productId, [
            'auth_bearer' => $bearerToken,
            'extra' => [
                'parameters' => [
                    'shopId' => self::$secondShopId,
                ],
            ],
        ]);

        self::assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);
        $content = $response->getContent(false);
        $this->assertStringContainsString(sprintf(
            'Could not find association between Product %d and Shop %d',
            $productId,
            self::$secondShopId
        ), $content);

        return $productId;
    }

    /**
     * @depends testGetProductForSecondShopIsFailing
     *
     * @param int $productId
     *
     * @return int
     */
    public function testAssociateProductToShops(int $productId): int
    {
        $allShopIds = [
            self::DEFAULT_SHOP_ID,
            self::$secondShopId,
            self::$thirdShopId,
            self::$fourthShopId,
        ];
        $bearerToken = $this->getBearerToken(['product_write']);
        $response = static::createClient()->request('PATCH', '/product/' . $productId . '/shops', [
            'auth_bearer' => $bearerToken,
            'extra' => [
                'parameters' => [
                    'shopId' => self::DEFAULT_SHOP_ID,
                ],
            ],
            'json' => [
                'sourceShopId' => self::DEFAULT_SHOP_ID,
                'associatedShopIds' => $allShopIds,
            ],
        ]);
        $updatedProduct = json_decode($response->getContent(), true);
        $this->assertEquals($productId, $updatedProduct['productId']);
        $this->assertEquals($allShopIds, $updatedProduct['shopIds']);

        return $productId;
    }

    /**
     * @depends testAssociateProductToShops
     *
     * @param int $productId
     *
     * @return int
     */
    public function testUpdateProductForShops(int $productId): int
    {
        $bearerToken = $this->getBearerToken(['product_write']);
        // Modify name for all shops
        static::createClient()->request('PATCH', '/product/' . $productId, [
            'auth_bearer' => $bearerToken,
            'extra' => [
                'parameters' => [
                    'allShops' => true,
                ],
            ],
            'json' => [
                'names' => [
                    'en-US' => 'global product name',
                ],
            ],
        ]);
        self::assertResponseStatusCodeSame(200);

        // Check that all shops have been modified
        foreach ([self::DEFAULT_SHOP_ID, self::$secondShopId, self::$thirdShopId, self::$fourthShopId] as $shopId) {
            $product = $this->getProduct($productId, $shopId);
            $this->assertEquals('global product name', $product['names']['en-US']);
        }

        // Modify names for second group shop
        static::createClient()->request('PATCH', '/product/' . $productId, [
            'auth_bearer' => $bearerToken,
            'extra' => [
                'parameters' => [
                    'shopGroupId' => self::$secondShopGroupId,
                ],
            ],
            'json' => [
                'names' => [
                    'en-US' => 'second group product name',
                ],
            ],
        ]);
        self::assertResponseStatusCodeSame(200);

        // Modify names for first shop
        static::createClient()->request('PATCH', '/product/' . $productId, [
            'auth_bearer' => $bearerToken,
            'extra' => [
                'parameters' => [
                    'shopId' => self::DEFAULT_SHOP_ID,
                ],
            ],
            'json' => [
                'names' => [
                    'en-US' => 'first shop product name',
                ],
            ],
        ]);
        self::assertResponseStatusCodeSame(200);

        // Modify names for shop2 and shop4
        static::createClient()->request('PATCH', '/product/' . $productId, [
            'auth_bearer' => $bearerToken,
            'extra' => [
                'parameters' => [
                    'shopIds' => [self::$secondShopId, self::$fourthShopId],
                ],
            ],
            'json' => [
                'names' => [
                    'en-US' => 'even shops product name',
                ],
            ],
        ]);
        self::assertResponseStatusCodeSame(200);

        // Now check each shop modified content
        $product = $this->getProduct($productId, self::DEFAULT_SHOP_ID);
        $this->assertEquals('first shop product name', $product['names']['en-US']);
        $product = $this->getProduct($productId, self::$secondShopId);
        $this->assertEquals('even shops product name', $product['names']['en-US']);
        $product = $this->getProduct($productId, self::$thirdShopId);
        $this->assertEquals('second group product name', $product['names']['en-US']);
        $product = $this->getProduct($productId, self::$fourthShopId);
        $this->assertEquals('even shops product name', $product['names']['en-US']);

        return $productId;
    }

    protected function getProduct(int $productId, int $shopId): array
    {
        $bearerToken = $this->getBearerToken(['product_read']);
        $response = static::createClient()->request('GET', '/product/' . $productId, [
            'auth_bearer' => $bearerToken,
            'extra' => [
                'parameters' => [
                    'shopId' => $shopId,
                ],
            ],
        ]);

        return json_decode($response->getContent(), true);
    }

    protected function assertProductData(int $productId, array $expectedData, ResponseInterface $response): void
    {
        // Merge expected data with default one, this way no need to always specify all the fields
        $checkedData = $expectedData + ['productId' => $productId] + self::$defaultProductData;
        $decodedResponse = json_decode($response->getContent(), true);
        $this->assertNotFalse($decodedResponse);
        $this->assertNotFalse($decodedResponse);
        $this->assertArrayHasKey('productId', $decodedResponse);
        $this->assertEquals(
            $decodedResponse,
            $checkedData
        );
    }
}
