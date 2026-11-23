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
use Symfony\Component\HttpFoundation\Response;
use Tests\Resources\DatabaseDump;
use Tests\Resources\Resetter\ProductResetter;

class CombinationFeatureValueListEndpointTest extends ApiTestCase
{
    public static function setUpBeforeClass(): void
    {
        // The combination feature value CQRS layer only exists from PrestaShop 9.3.0
        if (self::isVersionUnder('9.3.0')) {
            static::markTestSkipped('This endpoint requires PrestaShop 9.3.0');
        }

        // The fixture features of the catalog, restored before the parent installs fr-FR, which copies the values in it
        self::resetTables();
        parent::setUpBeforeClass();
        self::createApiClient(['product_read', 'product_write', 'attribute_group_write', 'attribute_write']);
    }

    public static function tearDownAfterClass(): void
    {
        if (self::isVersionUnder('9.3.0')) {
            return;
        }

        parent::tearDownAfterClass();
        self::resetTables();
    }

    protected static function resetTables(): void
    {
        ProductResetter::resetProducts();
        DatabaseDump::restoreTables([
            'attribute_group',
            'attribute_group_lang',
            'attribute_group_shop',
            'attribute',
            'attribute_lang',
            'attribute_shop',
            'feature',
            'feature_lang',
            'feature_product',
            'feature_product_attribute',
            'feature_shop',
            'feature_value',
            'feature_value_lang',
        ]);
    }

    public static function getProtectedEndpoints(): iterable
    {
        // Yielded on every core: the data provider is resolved before setUpBeforeClass() skips the class
        yield 'list combination feature values endpoint' => ['GET', '/products/combinations/1/feature-values'];
        yield 'set combination feature values endpoint' => ['PUT', '/products/combinations/1/feature-values'];
        yield 'remove all combination feature values endpoint' => ['DELETE', '/products/combinations/1/feature-values'];
    }

    public function testListFeatureValues(): int
    {
        $combinationId = $this->createCombination();

        $this->assertSame([], $this->getItem('/products/combinations/' . $combinationId . '/feature-values', ['product_read']));

        // The query doesn't check the combination
        $this->assertSame([], $this->getItem('/products/combinations/999999/feature-values', ['product_read']));
        $this->getItem('/products/combinations/0/feature-values', ['product_read'], Response::HTTP_UNPROCESSABLE_ENTITY);

        return $combinationId;
    }

    /**
     * @depends testListFeatureValues
     */
    public function testSetFeatureValues(int $combinationId): int
    {
        $uri = '/products/combinations/' . $combinationId . '/feature-values';

        // An existing value of the catalog (Composition: Ceramic), and a new custom value of the Property feature
        $featureValues = $this->updateItem($uri, [
            'featureValues' => [
                ['featureId' => 1, 'featureValueId' => 3],
                ['featureId' => 2, 'customValues' => ['en-US' => 'Long sleeves', 'fr-FR' => 'Manches longues']],
            ],
        ], ['product_write']);
        $this->assertCount(2, $featureValues);
        $featureValues = $this->sortByFeature($featureValues);
        $customValueId = $featureValues[1]['featureValueId'];
        $this->assertIsInt($customValueId);
        $expected = [
            ['combinationId' => $combinationId, 'featureId' => 1, 'featureValueId' => 3, 'custom' => false, 'values' => ['en-US' => 'Ceramic', 'fr-FR' => 'Ceramic']],
            ['combinationId' => $combinationId, 'featureId' => 2, 'featureValueId' => $customValueId, 'custom' => true, 'values' => ['en-US' => 'Long sleeves', 'fr-FR' => 'Manches longues']],
        ];
        $this->assertEquals($expected, $featureValues);
        $this->assertEquals($expected, $this->sortByFeature($this->getItem($uri, ['product_read'])));

        // The custom value is updated through its id, the value left out is removed
        $expected = [
            ['combinationId' => $combinationId, 'featureId' => 2, 'featureValueId' => $customValueId, 'custom' => true, 'values' => ['en-US' => 'Short', 'fr-FR' => 'Courtes']],
        ];
        $this->assertEquals($expected, $this->updateItem($uri, [
            'featureValues' => [
                ['featureId' => 2, 'featureValueId' => $customValueId, 'customValues' => ['en-US' => 'Short', 'fr-FR' => 'Courtes']],
            ],
        ], ['product_write']));
        $this->assertEquals($expected, $this->getItem($uri, ['product_read']));

        return $combinationId;
    }

    /**
     * @depends testSetFeatureValues
     */
    public function testSetInvalidFeatureValues(int $combinationId): void
    {
        $uri = '/products/combinations/' . $combinationId . '/feature-values';
        $expected = $this->getItem($uri, ['product_read']);

        // An unknown id of the body answers 404
        $this->updateItem($uri, ['featureValues' => [['featureId' => 1, 'featureValueId' => 99999]]], ['product_write'], Response::HTTP_NOT_FOUND);
        $this->updateItem($uri, ['featureValues' => [['featureId' => 99999, 'featureValueId' => 3]]], ['product_write'], Response::HTTP_NOT_FOUND);
        // A value of another feature, the same value twice, or neither a value nor custom texts answer 422
        $this->updateItem($uri, ['featureValues' => [['featureId' => 2, 'featureValueId' => 3]]], ['product_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->updateItem($uri, ['featureValues' => [
            ['featureId' => 1, 'featureValueId' => 3],
            ['featureId' => 1, 'featureValueId' => 3],
        ]], ['product_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->updateItem($uri, ['featureValues' => [['featureId' => 1]]], ['product_write'], Response::HTTP_UNPROCESSABLE_ENTITY);

        // The list and its items are validated before the command is built
        foreach ([[], ['featureValues' => null], ['featureValues' => []]] as $body) {
            $response = $this->requestApi('PUT', $uri, null, ['product_write'], Response::HTTP_UNPROCESSABLE_ENTITY, [
                'body' => json_encode((object) $body),
                'headers' => ['Content-Type' => 'application/json'],
            ]);
            $this->assertValidationErrors([
                ['propertyPath' => 'featureValues', 'message' => 'This value should not be blank.'],
            ], $response);
        }
        $response = $this->updateItem($uri, ['featureValues' => [['featureValueId' => 3], ['featureId' => 1, 'featureValueId' => -3]]], ['product_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertValidationErrors([
            ['propertyPath' => 'featureValues[0].featureId', 'message' => 'This value should not be null.'],
            ['propertyPath' => 'featureValues[1].featureValueId', 'message' => 'This value should be positive.'],
        ], $response);

        // Unknown and invalid combinations
        $this->updateItem('/products/combinations/999999/feature-values', ['featureValues' => [['featureId' => 1, 'featureValueId' => 3]]], ['product_write'], Response::HTTP_NOT_FOUND);
        $this->updateItem('/products/combinations/0/feature-values', ['featureValues' => [['featureId' => 1, 'featureValueId' => 3]]], ['product_write'], Response::HTTP_UNPROCESSABLE_ENTITY);

        // Nothing changed
        $this->assertEquals($expected, $this->getItem($uri, ['product_read']));
    }

    /**
     * @depends testSetFeatureValues
     */
    public function testRemoveAllFeatureValues(int $combinationId): void
    {
        $uri = '/products/combinations/' . $combinationId . '/feature-values';
        $this->assertNotEmpty($this->getItem($uri, ['product_read']));

        $this->assertSame([], $this->deleteItem($uri, ['product_write'], Response::HTTP_OK));
        $this->assertSame([], $this->getItem($uri, ['product_read']));

        $this->deleteItem('/products/combinations/999999/feature-values', ['product_write'], Response::HTTP_NOT_FOUND);
        $this->deleteItem('/products/combinations/0/feature-values', ['product_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /**
     * Creates a product with one combination, all through the API.
     */
    private function createCombination(): int
    {
        $attributeGroup = $this->createItem('/attributes/groups', [
            'names' => [
                'en-US' => 'Finish',
                'fr-FR' => 'Finition',
            ],
            'publicNames' => [
                'en-US' => 'Finish',
                'fr-FR' => 'Finition',
            ],
            'type' => 'select',
            'shopIds' => [1],
        ], ['attribute_group_write']);
        $this->assertArrayHasKey('attributeGroupId', $attributeGroup);
        $attribute = $this->createItem('/attributes/attributes', [
            'names' => [
                'en-US' => 'Matte',
                'fr-FR' => 'Mat',
            ],
            'attributeGroupId' => $attributeGroup['attributeGroupId'],
            'color' => '',
            'shopIds' => [1],
        ], ['attribute_write']);
        $this->assertArrayHasKey('attributeId', $attribute);

        $product = $this->createItem('/products', [
            'type' => ProductType::TYPE_COMBINATIONS,
            'names' => [
                'en-US' => 'product with combination features',
                'fr-FR' => 'produit avec caracteristiques de declinaison',
            ],
        ], ['product_write']);
        $this->assertArrayHasKey('productId', $product);
        $combinations = $this->createItem(sprintf('/products/%d/generate-combinations', $product['productId']), [
            'groupedAttributes' => [
                [
                    'attributeGroupId' => $attributeGroup['attributeGroupId'],
                    'attributeIds' => [$attribute['attributeId']],
                ],
            ],
        ], ['product_write']);
        $this->assertCount(1, $combinations['newCombinationIds']);

        return $combinations['newCombinationIds'][0];
    }

    /**
     * The core reads the values in no guaranteed order.
     */
    private function sortByFeature(array $featureValues): array
    {
        usort($featureValues, static fn (array $a, array $b): int => $a['featureId'] <=> $b['featureId']);

        return $featureValues;
    }
}
