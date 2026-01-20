<?php

declare(strict_types=1);

namespace PsApiResourcesTest\Integration\ApiPlatform;

use PrestaShop\PrestaShop\Adapter\Attribute\Repository\AttributeRepository;
use PrestaShop\PrestaShop\Adapter\AttributeGroup\Repository\AttributeGroupRepository;
use PrestaShop\PrestaShop\Core\Domain\AttributeGroup\ValueObject\AttributeGroupId;
use PrestaShop\PrestaShop\Core\Domain\Product\ValueObject\ProductType;
use PrestaShop\PrestaShop\Core\Domain\Shop\ValueObject\ShopConstraint;
use Tests\Resources\Resetter\LanguageResetter;
use Tests\Resources\Resetter\ProductResetter;
use Tests\Resources\ResourceResetter;

class ProductCombinationEndpointTest extends ApiTestCase
{
    /**
     * @var array<string, int>
     */
    private static array $attributeGroupData = [];
    /**
     * @var array<string, int>
     */
    private static array $attributeData = [];

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        (new ResourceResetter())->backupTestModules();
        ProductResetter::resetProducts();
        LanguageResetter::resetLanguages();
        self::addLanguageByLocale('fr-FR');
        // Pre-create the API Client with the needed scopes, this way we reduce the number of created API Clients
        self::createApiClient(['product_write', 'product_read']);

        // Fetch data for attributes to use them more easily in the following tests
        /** @var AttributeGroupRepository $attributeGroupRepository */
        $attributeGroupRepository = self::getContainer()->get(AttributeGroupRepository::class);
        $attributeGroups = $attributeGroupRepository->getAttributeGroups(ShopConstraint::allShops());
        foreach ($attributeGroups as $attributeGroup) {
            // Store english name as the key
            self::$attributeGroupData[$attributeGroup->name[1]] = (int) $attributeGroup->id;
        }

        /** @var AttributeRepository $attributeRepository */
        $attributeRepository = self::getContainer()->get(AttributeRepository::class);
        $attributeGroupIds = array_map(static function (int $attributeGroupId) {
            return new AttributeGroupId($attributeGroupId);
        }, array_values(self::$attributeGroupData));
        $groupedAttributes = $attributeRepository->getGroupedAttributes(ShopConstraint::allShops(), $attributeGroupIds);
        foreach ($groupedAttributes as $attributeGroupId => $groupAttributes) {
            foreach ($groupAttributes as $attribute) {
                self::$attributeData[$attribute->name[1]] = (int) $attribute->id;
            }
        }
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
        yield 'generate combinations endpoint' => [
            'POST',
            '/products/1/generate-combinations',
        ];

        yield 'list combination IDs' => [
            'GET',
            '/products/1/combination-ids',
        ];

        yield 'get endpoint' => [
            'GET',
            '/products/combinations/1',
        ];
    }

    public function testAddProductWithCombinations(): int
    {
        $addedProduct = $this->createItem('/products', [
            'type' => ProductType::TYPE_COMBINATIONS,
            'names' => [
                'en-US' => 'product with combinations',
                'fr-FR' => 'produit avec combinaisons',
            ],
        ], ['product_write']);

        return $addedProduct['productId'];
    }

    /**
     * @depends testAddProductWithCombinations
     */
    public function testCreateProductCombinations(int $productId): array
    {
        $postData = [
            'groupedAttributes' => [
                [
                    'attributeGroupId' => self::$attributeGroupData['Color'],
                    'attributeIds' => [
                        self::$attributeData['Red'],
                        self::$attributeData['Black'],
                        self::$attributeData['Yellow'],
                    ],
                ],
                [
                    'attributeGroupId' => self::$attributeGroupData['Size'],
                    'attributeIds' => [
                        self::$attributeData['S'],
                        self::$attributeData['M'],
                        self::$attributeData['L'],
                    ],
                ],
            ],
        ];
        $createdCombinations = $this->createItem(sprintf('/products/%d/generate-combinations', $productId), $postData, ['product_write']);
        // 9 combinations should have been created (three sizes for each three colors)
        $this->assertCount(9, $createdCombinations['newCombinationIds']);
        $newCombinationIds = $createdCombinations['newCombinationIds'];
        foreach ($newCombinationIds as $combinationId) {
            $this->assertIsInt($combinationId);
        }
        $expectedResult = [
            'productId' => $productId,
            // We fill this value dynamically because we can't guess their values
            'newCombinationIds' => $newCombinationIds,
        ];
        $this->assertEquals($expectedResult, $createdCombinations);

        // Now we call the same endpoint with the same attributes
        $createdCombinations = $this->createItem(sprintf('/products/%d/generate-combinations', $productId), $postData, ['product_write']);
        $this->assertEquals([
            'productId' => $productId,
            // Since the combinations already exist no new combination is created
            'newCombinationIds' => [],
        ], $createdCombinations);

        // Now we call with extra attributes, only the missing combinations are created
        $postData['groupedAttributes'][0]['attributeIds'][] = self::$attributeData['White'];
        $postData['groupedAttributes'][1]['attributeIds'][] = self::$attributeData['XL'];

        // In total 16 combinations should be created, 9 have already been so 7 new combinations should be returned
        $createdCombinations = $this->createItem(sprintf('/products/%d/generate-combinations', $productId), $postData, ['product_write']);
        $this->assertCount(7, $createdCombinations['newCombinationIds']);
        $newCombinationIds = array_merge($newCombinationIds, $createdCombinations['newCombinationIds']);
        $this->assertCount(16, $newCombinationIds);

        // Now create new combinations from other attributes (we don't merge with previous postData)
        $postData = [
            'groupedAttributes' => [
                [
                    'attributeGroupId' => self::$attributeGroupData['Color'],
                    'attributeIds' => [
                        self::$attributeData['Blue'],
                    ],
                ],
                [
                    'attributeGroupId' => self::$attributeGroupData['Size'],
                    'attributeIds' => [
                        self::$attributeData['M'],
                        self::$attributeData['L'],
                    ],
                ],
            ],
        ];
        $createdCombinations = $this->createItem(sprintf('/products/%d/generate-combinations', $productId), $postData, ['product_write']);
        // Only two new combinations should have been created
        $this->assertCount(2, $createdCombinations['newCombinationIds']);
        $newCombinationIds = array_merge($newCombinationIds, $createdCombinations['newCombinationIds']);
        $this->assertCount(18, $newCombinationIds);

        return $newCombinationIds;
    }

    /**
     * @depends testAddProductWithCombinations
     * @depends testCreateProductCombinations
     */
    public function testListCombinationsIds(int $productId, array $newCombinationIds): array
    {
        $combinations = $this->getItem(sprintf('/products/%d/combination-ids', $productId), ['product_read']);
        $this->assertEquals([
            'productId' => $productId,
            'combinationIds' => $newCombinationIds,
        ], $combinations);

        // Now test pagination
        $resultsPerPage = 5;
        $pagesNumber = ceil(count($newCombinationIds) / $resultsPerPage);
        for ($page = 1; $page <= $pagesNumber; ++$page) {
            $offset = ($page - 1) * $resultsPerPage;
            $paginatedCombinations = $this->getItem(sprintf(
                '/products/%d/combination-ids?offset=%d&limit=%d',
                $productId,
                $offset,
                $resultsPerPage),
                ['product_read']
            );
            $expectedCombinationIds = array_slice($newCombinationIds, $offset, $resultsPerPage);
            $this->assertEquals([
                'productId' => $productId,
                'combinationIds' => $expectedCombinationIds,
            ], $paginatedCombinations);
        }

        return $newCombinationIds;
    }

    /**
     * @depends testAddProductWithCombinations
     * @depends testCreateProductCombinations
     */
    public function testCombinationList(int $productId, array $newCombinationIds): array
    {
        $paginatedCombinations = $this->listItems(sprintf('/products/%d/combinations', $productId), ['product_read']);
        $this->assertEquals(count($newCombinationIds), $paginatedCombinations['totalItems']);

        // Now check the expected format at least for the first two
        $this->assertEquals([
            'productId' => $productId,
            'combinationId' => $newCombinationIds[0],
            'name' => 'Size - S, Color - Red',
            'default' => true,
            'reference' => '',
            'impactOnPriceTaxExcluded' => 0.0,
            'ecoTax' => 0.0,
            'quantity' => 0,
            'imageUrl' => 'http://myshop.com/img/p/en-default-small_default.jpg',
            'attributes' => [
                [
                    'attributeGroupId' => self::$attributeGroupData['Size'],
                    'attributeGroupName' => 'Size',
                    'attributeId' => self::$attributeData['S'],
                    'attributeName' => 'S',
                ],
                [
                    'attributeGroupId' => self::$attributeGroupData['Color'],
                    'attributeGroupName' => 'Color',
                    'attributeId' => self::$attributeData['Red'],
                    'attributeName' => 'Red',
                ],
            ],
        ], $paginatedCombinations['items'][0]);
        $this->assertEquals([
            'productId' => $productId,
            'combinationId' => $newCombinationIds[1],
            'name' => 'Size - M, Color - Red',
            'default' => false,
            'reference' => '',
            'impactOnPriceTaxExcluded' => 0.0,
            'ecoTax' => 0.0,
            'quantity' => 0,
            'imageUrl' => 'http://myshop.com/img/p/en-default-small_default.jpg',
            'attributes' => [
                [
                    'attributeGroupId' => self::$attributeGroupData['Size'],
                    'attributeGroupName' => 'Size',
                    'attributeId' => self::$attributeData['M'],
                    'attributeName' => 'M',
                ],
                [
                    'attributeGroupId' => self::$attributeGroupData['Color'],
                    'attributeGroupName' => 'Color',
                    'attributeId' => self::$attributeData['Red'],
                    'attributeName' => 'Red',
                ],
            ],
        ], $paginatedCombinations['items'][1]);

        // Now test pagination
        $resultsPerPage = 5;
        $pagesNumber = ceil(count($newCombinationIds) / $resultsPerPage);
        for ($page = 1; $page <= $pagesNumber; ++$page) {
            $offset = ($page - 1) * $resultsPerPage;
            $paginatedCombinations = $this->getItem(sprintf(
                '/products/%d/combinations?offset=%d&limit=%d',
                $productId,
                $offset,
                $resultsPerPage),
                ['product_read']
            );
            $expectedCombinationIds = array_slice($newCombinationIds, $offset, $resultsPerPage);
            $paginatedCombinationIds = array_map(static function (array $combination): int {
                return $combination['combinationId'];
            }, $paginatedCombinations['items']);
            $this->assertEquals($expectedCombinationIds, $paginatedCombinationIds);
        }

        return $newCombinationIds;
    }

    /**
     * @depends testAddProductWithCombinations
     * @depends testListCombinationsIds
     *
     * @return int
     */
    public function testGetProductCombination(int $productId, array $newCombinationIds): int
    {
        $combinationId = $newCombinationIds[0];
        $combination = $this->getItem('/products/combinations/' . $combinationId, ['product_read']);
        $this->assertEquals([
            'productId' => $productId,
            'combinationId' => $combinationId,
            'name' => 'Size - S, Color - Red',
            'default' => true,
            'gtin' => '',
            'isbn' => '',
            'mpn' => '',
            'reference' => '',
            'upc' => '',
            'coverThumbnailUrl' => 'http://myshop.com/img/p/en-default-cart_default.jpg',
            'imageIds' => [],
            'impactOnPriceTaxExcluded' => 0.0,
            'impactOnPriceTaxIncluded' => 0.0,
            'impactOnUnitPriceTaxIncluded' => 0.0,
            'ecotaxTaxExcluded' => 0.0,
            'ecotaxTaxIncluded' => 0.0,
            'impactOnWeight' => 0.0,
            'wholesalePrice' => 0.0,
            'productTaxRate' => 6.0,
            'productPriceTaxExcluded' => 0.0,
            'productEcotaxTaxExcluded' => 0.0,
            'quantity' => 0,
        ], $combination);

        return $combinationId;
    }
}
