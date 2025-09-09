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

use PrestaShop\PrestaShop\Core\Domain\Product\Pack\ValueObject\PackStockType;
use PrestaShop\PrestaShop\Core\Domain\Product\Stock\ValueObject\OutOfStockType;
use PrestaShop\PrestaShop\Core\Domain\Product\ValueObject\DeliveryTimeNoteType;
use PrestaShop\PrestaShop\Core\Domain\Product\ValueObject\ProductCondition;
use PrestaShop\PrestaShop\Core\Domain\Product\ValueObject\ProductType;
use PrestaShop\PrestaShop\Core\Domain\Product\ValueObject\ProductVisibility;
use PrestaShop\PrestaShop\Core\Domain\Product\ValueObject\RedirectType;
use Symfony\Component\HttpFoundation\Response;
use Tests\Resources\Resetter\LanguageResetter;
use Tests\Resources\Resetter\ProductResetter;
use Tests\Resources\ResourceResetter;

class ProductEndpointTest extends ApiTestCase
{
    protected static array $defaultProductData = [
        'type' => ProductType::TYPE_STANDARD,
        'names' => [
            'en-US' => '',
            'fr-FR' => '',
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
        // US-FL Rate (6%)
        'taxRulesGroupId' => 9,
        'onSale' => false,
        'wholesalePrice' => 0.0,
        'unitPriceTaxExcluded' => 0.0,
        'unitPriceTaxIncluded' => 0.0,
        'unity' => '',
        'unitPriceRatio' => 0.0,
        'visibility' => ProductVisibility::VISIBLE_EVERYWHERE,
        'availableForOrder' => true,
        'onlineOnly' => false,
        'showPrice' => true,
        'condition' => ProductCondition::NEW,
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
        'deliveryTimeNoteType' => DeliveryTimeNoteType::TYPE_DEFAULT,
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
            'en-US' => '',
            'fr-FR' => '',
        ],
        'redirectType' => RedirectType::TYPE_DEFAULT,
        'packStockType' => PackStockType::STOCK_TYPE_DEFAULT,
        'outOfStockType' => OutOfStockType::OUT_OF_STOCK_DEFAULT,
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
            1,
        ],
    ];

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        (new ResourceResetter())->backupTestModules();
        ProductResetter::resetProducts();
        LanguageResetter::resetLanguages();
        self::addLanguageByLocale('fr-FR');
        // Pre-create the API Client with the needed scopes, this way we reduce the number of created API Clients
        self::createApiClient(['product_write', 'product_read']);
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        ProductResetter::resetProducts();
        LanguageResetter::resetLanguages();
        // Reset modules folder that are removed with the FR language
        (new ResourceResetter())->resetTestModules();
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'get endpoint' => [
            'GET',
            '/product/1',
        ];

        yield 'create endpoint' => [
            'POST',
            '/product',
        ];

        yield 'update endpoint' => [
            'PATCH',
            '/product/1',
        ];

        yield 'update endpoint with merge content type' => [
            'PATCH',
            '/product/1',
            'application/merge-patch+json',
        ];

        yield 'delete endpoint' => [
            'DELETE',
            '/product/1',
        ];

        yield 'upload image endpoint' => [
            'POST',
            '/product/1/image',
            'multipart/form-data',
        ];

        yield 'get image endpoint' => [
            'GET',
            '/product/image/1',
        ];

        yield 'update image endpoint' => [
            'POST',
            '/product/image/1',
            'multipart/form-data',
        ];

        yield 'list images endpoint' => [
            'GET',
            '/product/1/images',
        ];
    }

    public function testAddProduct(): int
    {
        $productsNumber = $this->countItems('/products', ['product_read']);
        $addedProduct = $this->createItem('/product', [
            'type' => ProductType::TYPE_STANDARD,
            'names' => [
                'en-US' => 'product name',
                'fr-FR' => 'nom produit',
            ],
        ], ['product_write']);
        $newProductsNumber = $this->countItems('/products', ['product_read']);
        self::assertEquals($productsNumber + 1, $newProductsNumber);
        $this->assertArrayHasKey('productId', $addedProduct);
        $productId = $addedProduct['productId'];
        $this->assertEquals(
            [
                'type' => ProductType::TYPE_STANDARD,
                'productId' => $productId,
                'names' => [
                    'en-US' => 'product name',
                    'fr-FR' => 'nom produit',
                ],
                'linkRewrites' => [
                    'en-US' => 'product-name',
                    'fr-FR' => 'nom-produit',
                ],
                'descriptions' => [
                    'en-US' => '',
                    'fr-FR' => '',
                ],
                'active' => false,
                'shopIds' => [
                    1,
                ],
            ] + self::$defaultProductData,
            $addedProduct
        );

        return $productId;
    }

    /**
     * @depends testAddProduct
     *
     * @param int $productId
     *
     * @return int
     */
    public function testPartialUpdateProduct(int $productId): int
    {
        $productsNumber = $this->countItems('/products', ['product_read']);
        $patchedProduct = $this->partialUpdateItem('/product/' . $productId, [
            'names' => [
                'fr-FR' => 'nouveau nom',
            ],
            'descriptions' => [
                'en-US' => 'new description',
            ],
            'active' => true,
        ], ['product_write']);

        // No new product, the number of products stays the same
        $this->assertEquals($productsNumber, $this->countItems('/products', ['product_read']));

        // Returned data has modified fields, the others haven't changed
        $this->assertEquals(
            [
                'type' => ProductType::TYPE_STANDARD,
                'productId' => $productId,
                'names' => [
                    'en-US' => 'product name',
                    'fr-FR' => 'nouveau nom',
                ],
                'linkRewrites' => [
                    'en-US' => 'product-name',
                    'fr-FR' => 'nom-produit',
                ],
                'descriptions' => [
                    'en-US' => 'new description',
                    'fr-FR' => '',
                ],
                'active' => true,
                'shopIds' => [
                    1,
                ],
            ] + self::$defaultProductData,
            $patchedProduct
        );

        // Update product with partial data, only name default language the other names are not impacted
        $patchedProduct2 = $this->partialUpdateItem('/product/' . $productId, [
            'names' => [
                'en-US' => 'new product name',
            ],
        ], ['product_write']);

        // Returned data has modified fields, the others haven't changed
        $this->assertEquals(
            [
                'type' => ProductType::TYPE_STANDARD,
                'productId' => $productId,
                'names' => [
                    'en-US' => 'new product name',
                    'fr-FR' => 'nouveau nom',
                ],
                'linkRewrites' => [
                    'en-US' => 'product-name',
                    'fr-FR' => 'nom-produit',
                ],
                'descriptions' => [
                    'en-US' => 'new description',
                    'fr-FR' => '',
                ],
                'active' => true,
                'shopIds' => [
                    1,
                ],
            ] + self::$defaultProductData,
            $patchedProduct2
        );

        return $productId;
    }

    /**
     * @depends testPartialUpdateProduct
     *
     * @param int $productId
     */
    public function testGetProduct(int $productId): int
    {
        // Returned data has modified fields, the others haven't changed
        $product = $this->getItem('/product/' . $productId, ['product_read']);
        $this->assertEquals(
            [
                'type' => ProductType::TYPE_STANDARD,
                'productId' => $productId,
                'names' => [
                    'en-US' => 'new product name',
                    'fr-FR' => 'nouveau nom',
                ],
                'linkRewrites' => [
                    'en-US' => 'product-name',
                    'fr-FR' => 'nom-produit',
                ],
                'descriptions' => [
                    'en-US' => 'new description',
                    'fr-FR' => '',
                ],
                'active' => true,
                'shopIds' => [
                    1,
                ],
            ] + self::$defaultProductData,
            $product
        );

        return $productId;
    }

    /**
     * @depends testPartialUpdateProduct
     *
     * @param int $productId
     */
    public function testUpdateAllProductFields(int $productId): int
    {
        $updateProductData = [
            'type' => ProductType::TYPE_STANDARD,
            'names' => [
                'en-US' => 'new name',
                'fr-FR' => 'nouveau nom',
            ],
            'descriptions' => [
                'en-US' => 'new description',
                'fr-FR' => 'nouvelle description',
            ],
            'shortDescriptions' => [
                'en-US' => 'new short description',
                'fr-FR' => 'nouvelle description courte',
            ],
            'priceTaxExcluded' => 10.0,
            'ecotaxTaxExcluded' => 2.0,
            // US-GA Rate (4%)
            'taxRulesGroupId' => 10,
            'onSale' => true,
            'wholesalePrice' => 3.45,
            'unitPriceTaxExcluded' => 5.0,
            'unity' => 'per kg',
            'visibility' => ProductVisibility::VISIBLE_IN_CATALOG,
            'availableForOrder' => false,
            'onlineOnly' => true,
            'showPrice' => false,
            'condition' => ProductCondition::USED,
            'showCondition' => false,
            'manufacturerId' => 1,
            'isbn' => '978-3-16-148410-0',
            'upc' => '72527273070',
            'gtin' => '978020137962',
            'mpn' => 'mpn1',
            'reference' => 'ref1',
            'width' => 10.20,
            'height' => 90.60,
            'depth' => 32.70,
            'weight' => 10.07,
            'additionalShippingCost' => 1.2,
            'deliveryTimeNoteType' => DeliveryTimeNoteType::TYPE_SPECIFIC,
            'deliveryTimeInStockNotes' => [
                'en-US' => 'under 2 days',
                'fr-FR' => 'moins de 2 jours',
            ],
            'deliveryTimeOutOfStockNotes' => [
                'en-US' => 'one month',
                'fr-FR' => 'un mois',
            ],
            'metaTitles' => [
                'en-US' => 'new meta title',
                'fr-FR' => 'nouveau titre meta',
            ],
            'metaDescriptions' => [
                'en-US' => 'new meta description',
                'fr-FR' => 'nouvelle description meta',
            ],
            'linkRewrites' => [
                'en-US' => 'new-link',
                'fr-FR' => 'nouveau-lien',
            ],
            'packStockType' => PackStockType::STOCK_TYPE_BOTH,
            'minimalQuantity' => 3,
            'lowStockThreshold' => 5,
            'lowStockAlertEnabled' => true,
            'availableNowLabels' => [
                'en-US' => 'available now',
                'fr-FR' => 'disponible maintenant',
            ],
            'availableLaterLabels' => [
                'en-US' => 'available later',
                'fr-FR' => 'disponible plus tard',
            ],
            'active' => false,
            // Multi-parameters setter
            'redirectOption' => [
                'redirectType' => RedirectType::TYPE_CATEGORY_PERMANENT,
                'redirectTarget' => 1,
            ],
        ];

        // Update the product
        $updatedProduct = $this->partialUpdateItem('/product/' . $productId, $updateProductData, ['product_write']);

        // Build expected data
        $expectedUpdateProduct = [
            'productId' => $productId,
            // These fields are not part of the posted data but are automatically updated after data is modified
            'priceTaxIncluded' => 10.4,
            'ecotaxTaxIncluded' => 2.0,
            'unitPriceTaxIncluded' => 5.2,
            'unitPriceRatio' => 2.0,
        ] + $updateProductData + self::$defaultProductData;

        // Redirect options are passed as a sub object but they are returned independently when product is read
        unset($expectedUpdateProduct['redirectOption']);
        $expectedUpdateProduct['redirectType'] = RedirectType::TYPE_CATEGORY_PERMANENT;
        $expectedUpdateProduct['redirectTarget'] = 1;

        $this->assertEquals($expectedUpdateProduct, $updatedProduct);
        // Now check the result when we GET the product
        $this->assertEquals($expectedUpdateProduct, $this->getItem('/product/' . $productId, ['product_read']));

        return $productId;
    }

    /**
     * @depends testUpdateAllProductFields
     *
     * @param int $productId
     */
    public function testAddImage(int $productId): int
    {
        $uploadedImage = $this->prepareUploadedFile(__DIR__ . '/../../Resources/assets/image/Hummingbird_cushion.jpg');

        // Special type of request, requires multipart/form-data content-type and upload a file via the request
        $createdImage = $this->requestApi('POST', '/product/' . $productId . '/image', null, ['product_write'], Response::HTTP_CREATED, [
            'headers' => [
                'content-type' => 'multipart/form-data',
            ],
            'extra' => [
                'files' => [
                    'image' => $uploadedImage,
                ],
            ],
        ]);

        $this->assertArrayHasKey('imageId', $createdImage);
        $this->assertIsInt($createdImage['imageId']);
        $this->assertGreaterThan(0, $createdImage['imageId']);
        $imageId = $createdImage['imageId'];

        // Check URLs format based on the newly created Image ID
        $expectedImage = [
            'imageId' => $imageId,
            'imageUrl' => 'http://myshop.com/img/p/' . $this->getImagePath($imageId, false),
            'thumbnailUrl' => 'http://myshop.com/img/p/' . $this->getImagePath($imageId, true),
            'legends' => [
                'en-US' => '',
                'fr-FR' => '',
            ],
            'cover' => true,
            'position' => 1,
            'shopIds' => [
                1,
            ],
        ];
        $this->assertEquals($expectedImage, $createdImage);

        return $imageId;
    }

    /**
     * @depends testAddImage
     *
     * @param int $imageId
     */
    public function testGetImage(int $imageId): string
    {
        $expectedImage = [
            'imageId' => $imageId,
            'imageUrl' => 'http://myshop.com/img/p/' . $this->getImagePath($imageId, false),
            'thumbnailUrl' => 'http://myshop.com/img/p/' . $this->getImagePath($imageId, true),
            'legends' => [
                'en-US' => '',
                'fr-FR' => '',
            ],
            'cover' => true,
            'position' => 1,
            'shopIds' => [
                1,
            ],
        ];
        $image = $this->getItem('/product/image/' . $imageId, ['product_read']);
        $this->assertEquals($expectedImage, $image);

        return $this->getImageMD5($image);
    }

    /**
     * @depends testAddImage
     * @depends testGetImage
     *
     * @param int $imageId
     */
    public function testUpdateImage(int $imageId, string $imageMD5): int
    {
        $uploadedImage = $this->prepareUploadedFile(__DIR__ . '/../../Resources/assets/image/Brown_bear_cushion.jpg');

        // We have to force POST request, because we cannot use PUT with files AND data
        $updatedImage = $this->requestApi('POST', '/product/image/' . $imageId, null, ['product_write'], Response::HTTP_OK, [
            'headers' => [
                'content-type' => 'multipart/form-data',
            ],
            'extra' => [
                'parameters' => [
                    // Parameters are sent via POST parameters not JSON
                    'legends' => [
                        'en-US' => 'legend en',
                        'fr-FR' => 'legend fr',
                    ],
                ],
                'files' => [
                    'image' => $uploadedImage,
                ],
            ],
        ]);
        $this->assertArrayHasKey('imageId', $updatedImage);
        $this->assertIsInt($updatedImage['imageId']);
        $imageId = $updatedImage['imageId'];
        $this->assertGreaterThan(0, $updatedImage['imageId']);

        // The output is almost identical (the image URLs are the same at least), only the legends is different in the JSON response
        $expectedImage = [
            'imageId' => $imageId,
            'imageUrl' => 'http://myshop.com/img/p/' . $this->getImagePath($imageId, false),
            'thumbnailUrl' => 'http://myshop.com/img/p/' . $this->getImagePath($imageId, true),
            'legends' => [
                'en-US' => 'legend en',
                'fr-FR' => 'legend fr',
            ],
            'cover' => true,
            'position' => 1,
            'shopIds' => [
                1,
            ],
        ];
        $this->assertEquals($expectedImage, $updatedImage);

        // But we must ensure that the image has been modified by checking its md5 checksum
        $newImageMD5 = $this->getImageMD5($updatedImage);
        $this->assertNotEquals($imageMD5, $newImageMD5);

        return $imageId;
    }

    /**
     * @depends testGetProduct
     * @depends testUpdateImage
     */
    public function testListImages(int $productId, int $imageId): void
    {
        // First add a new image so that we have at least to images
        $uploadedImage = $this->prepareUploadedFile(__DIR__ . '/../../Resources/assets/image/Hummingbird_cushion.jpg');
        $newImage = $this->requestApi('POST', '/product/' . $productId . '/image', null, ['product_write'], Response::HTTP_CREATED, [
            'headers' => [
                'content-type' => 'multipart/form-data',
            ],
            'extra' => [
                'files' => [
                    'image' => $uploadedImage,
                ],
            ],
        ]);
        $newImageId = $newImage['imageId'];

        // Get the whole list of images (we don't use the usual listItems helper because this is a custom endpoint based on a CQRS query
        // and a different response format)
        $productImages = $this->getItem('/product/' . $productId . '/images', ['product_read']);
        $this->assertEquals(2, count($productImages));
        $this->assertEquals([
            [
                'imageId' => $imageId,
                'imageUrl' => 'http://myshop.com/img/p/' . $this->getImagePath($imageId, false),
                'thumbnailUrl' => 'http://myshop.com/img/p/' . $this->getImagePath($imageId, true),
                'legends' => [
                    'en-US' => 'legend en',
                    'fr-FR' => 'legend fr',
                ],
                'cover' => true,
                'position' => 1,
                'shopIds' => [
                    1,
                ],
            ],
            [
                'imageId' => $newImageId,
                'imageUrl' => 'http://myshop.com/img/p/' . $this->getImagePath($newImageId, false),
                'thumbnailUrl' => 'http://myshop.com/img/p/' . $this->getImagePath($newImageId, true),
                'legends' => [
                    'en-US' => '',
                    'fr-FR' => '',
                ],
                'cover' => false,
                'position' => 2,
                'shopIds' => [
                    1,
                ],
            ],
        ], $productImages);

        // Now update the second image to be the cover and have position 1
        $this->requestApi('POST', '/product/image/' . $newImageId, null, ['product_write'], Response::HTTP_OK, [
            'headers' => [
                'content-type' => 'multipart/form-data',
            ],
            'extra' => [
                'parameters' => [
                    // We use string on purpose because form data are sent like string, thus we validate here that the denormalization still
                    // works with string value (actually we only ignore the wrong type, but it works nonetheless)
                    'cover' => '1',
                    'position' => '1',
                ],
            ],
        ]);

        // Now check the updated list, the content is changed but so is the order because images are sorted by position
        $productImages = $this->getItem('/product/' . $productId . '/images', ['product_read']);
        $this->assertEquals(2, count($productImages));

        // The images are sorted differently (since they are automatically order by position) and the cover has been updated
        $this->assertEquals([
            [
                'imageId' => $newImageId,
                'imageUrl' => 'http://myshop.com/img/p/' . $this->getImagePath($newImageId, false),
                'thumbnailUrl' => 'http://myshop.com/img/p/' . $this->getImagePath($newImageId, true),
                'legends' => [
                    'en-US' => '',
                    'fr-FR' => '',
                ],
                'cover' => true,
                'position' => 1,
                'shopIds' => [
                    1,
                ],
            ],
            [
                'imageId' => $imageId,
                'imageUrl' => 'http://myshop.com/img/p/' . $this->getImagePath($imageId, false),
                'thumbnailUrl' => 'http://myshop.com/img/p/' . $this->getImagePath($imageId, true),
                'legends' => [
                    'en-US' => 'legend en',
                    'fr-FR' => 'legend fr',
                ],
                'cover' => false,
                'position' => 2,
                'shopIds' => [
                    1,
                ],
            ],
        ], $productImages);
    }

    /**
     * @depends testUpdateImage
     * @depends testGetProduct
     * @depends testListImages
     */
    public function testDeleteImage(int $imageId, int $productId): void
    {
        // Image exists
        $this->assertIsArray($this->getItem('/product/image/' . $imageId, ['product_read']));
        // Now delete the image
        $this->deleteItem('/product/image/' . $imageId, ['product_write']);

        // The image single endpoint returns a 404, and the image is not in the list anymore
        $this->getItem('/product/image/' . $imageId, ['product_read'], Response::HTTP_NOT_FOUND);
        $productImages = $this->getItem('/product/' . $productId . '/images', ['product_read']);
        $this->assertEquals(1, count($productImages));
    }

    /**
     * @depends testGetProduct
     * @depends testDeleteImage
     *
     * @param int $productId
     */
    public function testDeleteProduct(int $productId): void
    {
        $productsNumber = $this->countItems('/products', ['product_read']);

        // Delete product with token without write permission
        $this->deleteItem('/product/' . $productId, ['product_read'], Response::HTTP_FORBIDDEN);
        // The product should still exist
        $this->assertIsArray($this->getItem('/product/' . $productId, ['product_read']));

        // Delete product with proper token
        $this->deleteItem('/product/' . $productId, ['product_write']);

        // One less products
        $this->assertEquals($productsNumber - 1, $this->countItems('/products', ['product_read']));
        // The product is not accessible anymore
        $this->getItem('/product/' . $productId, ['product_read'], Response::HTTP_NOT_FOUND);
    }

    /**
     * @depends testDeleteProduct
     */
    public function testListProducts(): void
    {
        $paginatedProducts = $this->listItems('/products', ['product_read']);
        $this->assertCount(19, $paginatedProducts['items']);
        $this->assertEquals(19, $paginatedProducts['totalItems']);

        $paginatedProducts = $this->listItems('/products?limit=10', ['product_read']);
        $this->assertCount(10, $paginatedProducts['items']);
        $this->assertEquals(19, $paginatedProducts['totalItems']);

        $testedFilters = [
            // No filters
            [],
            // Filter by name
            ['filters' => ['name' => 'Customizable mug']],
            // Filter by productId (mn/max filter)
            ['filters' => ['productId' => ['min_field' => 19, 'max_field' => 19]]],
        ];
        foreach ($testedFilters as $filters) {
            $listUrl = '/products?limit=1&orderBy=productId&sortOrder=desc&' . http_build_query($filters);
            $paginatedProducts = $this->listItems($listUrl, ['product_read']);
            $this->assertCount(1, $paginatedProducts['items']);
            $this->assertEquals(19, $paginatedProducts['totalItems']);

            $this->assertEquals('productId', $paginatedProducts['orderBy']);
            $this->assertEquals('desc', $paginatedProducts['sortOrder']);
            $this->assertEquals(1, $paginatedProducts['limit']);
            $this->assertEquals([], $paginatedProducts['filters']);

            $expectedProduct = [
                'productId' => 19,
                'name' => 'Customizable mug',
                'quantity' => 300,
                'priceTaxExcluded' => 13.90,
                'priceTaxIncluded' => 14.734,
                'category' => 'Home Accessories',
                'active' => true,
            ];
            $this->assertEquals($expectedProduct, $paginatedProducts['items'][0]);
        }
    }

    protected function getImagePath(int $imageId, bool $isThumbnail): string
    {
        return implode('/', str_split((string) $imageId)) . '/' . $imageId . ($isThumbnail ? '-small_default' : '') . '.jpg';
    }

    protected function getImageMD5(array $image): string
    {
        $matches = [];
        $imageId = $image['imageId'];
        preg_match('@/p/[/0-9]+' . $imageId . '\.jpg@', $image['imageUrl'], $matches);
        $imageFilePath = _PS_IMG_DIR_ . $matches[0];
        $this->assertTrue(file_exists($imageFilePath));

        return md5_file($imageFilePath);
    }
}
