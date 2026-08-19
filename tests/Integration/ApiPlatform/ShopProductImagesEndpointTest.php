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
use Tests\Resources\Resetter\ConfigurationResetter;
use Tests\Resources\Resetter\FeatureFlagResetter;
use Tests\Resources\Resetter\ProductResetter;
use Tests\Resources\Resetter\ShopResetter;

class ShopProductImagesEndpointTest extends ApiTestCase
{
    protected const DEFAULT_SHOP_GROUP_ID = 1;

    protected static int $secondShopId;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        ProductResetter::resetProducts();
        ShopResetter::resetShops();
        ConfigurationResetter::resetConfiguration();

        self::updateConfiguration(MultistoreConfig::FEATURE_STATUS, 1);
        // Disable secure protection for the tests (the configuration reset forced the default config back)
        self::updateConfiguration('PS_ADMIN_API_FORCE_DEBUG_SECURED', 0);
        self::$secondShopId = self::addShop('Second shop', self::DEFAULT_SHOP_GROUP_ID);
        self::createApiClient(['product_write', 'product_read']);

        $featureFlagManager = self::getContainer()->get('PrestaShop\PrestaShop\Core\FeatureFlag\FeatureFlagManager');
        $featureFlagManager->enable(FeatureFlagSettings::FEATURE_FLAG_ADMIN_API_MULTISTORE);
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        ProductResetter::resetProducts();
        ShopResetter::resetShops();
        ConfigurationResetter::resetConfiguration();
        FeatureFlagResetter::resetFeatureFlags();
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'get shop product images endpoint' => [
            'GET',
            '/products/1/shop-images',
        ];

        yield 'update shop product images endpoint' => [
            'PUT',
            '/products/1/shop-images',
        ];
    }

    public function testGetAndUpdateShopImages(): void
    {
        $product = $this->createItem('/products', [
            'type' => ProductType::TYPE_STANDARD,
            'names' => [
                'en-US' => 'product with shop images',
                'fr-FR' => 'produit avec images',
            ],
        ], ['product_write'], Response::HTTP_CREATED, [
            'extra' => [
                'parameters' => [
                    'shopId' => 1,
                ],
            ],
        ]);
        $this->assertArrayHasKey('productId', $product);
        $productId = $product['productId'];

        // Associate the product with both shops
        $this->partialUpdateItem(sprintf('/products/%d/shops', $productId), [
            'sourceShopId' => 1,
            'associatedShopIds' => [1, self::$secondShopId],
        ], ['product_write'], Response::HTTP_OK, [
            'extra' => [
                'parameters' => [
                    'shopId' => 1,
                ],
            ],
        ]);

        // Upload two images on the first shop
        $imageIds = [];
        foreach (['Hummingbird_cushion.jpg', 'Brown_bear_cushion.jpg'] as $imageFile) {
            $uploadedImage = $this->prepareUploadedFile(__DIR__ . '/../../Resources/assets/image/' . $imageFile);
            $createdImage = $this->requestApi('POST', sprintf('/products/%d/images', $productId), null, ['product_write'], Response::HTTP_CREATED, [
                'headers' => [
                    'content-type' => 'multipart/form-data',
                ],
                'extra' => [
                    'parameters' => [
                        'shopId' => 1,
                    ],
                    'files' => [
                        'image' => $uploadedImage,
                    ],
                ],
            ]);
            $this->assertArrayHasKey('imageId', $createdImage);
            $imageIds[] = $createdImage['imageId'];
        }
        [$firstImageId, $secondImageId] = $imageIds;

        // The images were uploaded with a shop 1 context: the second shop is part of the
        // associations (the product is associated with it) but has no image yet
        $this->assertEquals(
            [
                'productId' => $productId,
                'shopImages' => [
                    [
                        'shopId' => 1,
                        'images' => [
                            ['imageId' => $firstImageId, 'cover' => true],
                            ['imageId' => $secondImageId, 'cover' => false],
                        ],
                    ],
                    [
                        'shopId' => self::$secondShopId,
                        'images' => [],
                    ],
                ],
            ],
            $this->getItem(sprintf('/products/%d/shop-images', $productId), ['product_read'], Response::HTTP_OK, self::shopContext())
        );

        // Associate the cover image with both shops, the second image stays on the first shop
        // only; the operation returns the updated associations
        $updatedShopImages = $this->updateItem(sprintf('/products/%d/shop-images', $productId), [
            'shopImages' => [
                ['imageId' => $firstImageId, 'shopIds' => [1, self::$secondShopId]],
                ['imageId' => $secondImageId, 'shopIds' => [1]],
            ],
        ], ['product_write'], Response::HTTP_OK, self::shopContext());

        $expectedShopImages = [
            'productId' => $productId,
            'shopImages' => [
                [
                    'shopId' => 1,
                    'images' => [
                        ['imageId' => $firstImageId, 'cover' => true],
                        ['imageId' => $secondImageId, 'cover' => false],
                    ],
                ],
                [
                    'shopId' => self::$secondShopId,
                    'images' => [
                        // The association does not promote the image as cover for the new shop
                        ['imageId' => $firstImageId, 'cover' => false],
                    ],
                ],
            ],
        ];
        $this->assertEquals($expectedShopImages, $updatedShopImages);
        // The GET endpoint returns the exact same content
        $this->assertEquals(
            $expectedShopImages,
            $this->getItem(sprintf('/products/%d/shop-images', $productId), ['product_read'], Response::HTTP_OK, self::shopContext())
        );
    }

    public function testGetShopImagesForUnknownProduct(): void
    {
        // The core query does not check the product existence, an unknown product simply
        // has no image association
        $this->assertEquals(
            [
                'productId' => 99999999,
                'shopImages' => [],
            ],
            $this->getItem('/products/99999999/shop-images', ['product_read'], Response::HTTP_OK, self::shopContext())
        );
    }

    public function testInvalidShopProductImages(): void
    {
        $product = $this->createItem('/products', [
            'type' => ProductType::TYPE_STANDARD,
            'names' => [
                'en-US' => 'product with invalid shop images',
                'fr-FR' => 'produit avec images invalides',
            ],
        ], ['product_write'], Response::HTTP_CREATED, self::shopContext());
        $productId = $product['productId'];

        // The image/shop associations cannot be empty
        $validationErrorsResponse = $this->updateItem(sprintf('/products/%d/shop-images', $productId), [
            'shopImages' => [],
        ], ['product_write'], Response::HTTP_UNPROCESSABLE_ENTITY, self::shopContext());
        $this->assertIsArray($validationErrorsResponse);
        $this->assertValidationErrors([
            [
                'propertyPath' => 'shopImages',
                'message' => 'This value should not be blank.',
            ],
        ], $validationErrorsResponse);

        // A zero image id fails the domain constraint
        $this->updateItem(sprintf('/products/%d/shop-images', $productId), [
            'shopImages' => [
                ['imageId' => 0, 'shopIds' => [1]],
            ],
        ], ['product_write'], Response::HTTP_UNPROCESSABLE_ENTITY, self::shopContext());

        // A zero shop id fails the domain constraint
        $this->updateItem(sprintf('/products/%d/shop-images', $productId), [
            'shopImages' => [
                ['imageId' => 1, 'shopIds' => [0]],
            ],
        ], ['product_write'], Response::HTTP_UNPROCESSABLE_ENTITY, self::shopContext());
    }

    /**
     * In multishop mode every request must carry an explicit shop context.
     */
    private static function shopContext(int $shopId = 1): array
    {
        return [
            'extra' => [
                'parameters' => [
                    'shopId' => $shopId,
                ],
            ],
        ];
    }
}
