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

class CombinationStockEndpointTest extends ApiTestCase
{
    public static function setUpBeforeClass(): void
    {
        if (self::isVersionUnder('9.2.0')) {
            static::markTestSkipped('The combination stock endpoint requires PrestaShop 9.2.0 (older cores cannot create stock movements for API clients)');

            return;
        }

        parent::setUpBeforeClass();
        ProductResetter::resetProducts();
        self::createApiClient(['product_write', 'product_read', 'attribute_group_write', 'attribute_write']);
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        ProductResetter::resetProducts();
        DatabaseDump::restoreTables([
            'attribute_group',
            'attribute_group_lang',
            'attribute_group_shop',
            'attribute',
            'attribute_lang',
            'attribute_shop',
        ]);
    }

    public static function getProtectedEndpoints(): iterable
    {
        // Data providers are resolved when PHPUnit builds the test suite, before setUpBeforeClass
        // gets a chance to skip the class, and an empty provider is reported as an error. So the
        // endpoint is yielded unconditionally; on cores < 9.2.0 the whole class is skipped anyway
        // and this data set is never executed.
        yield 'update combination stock endpoint' => [
            'PUT',
            '/products/combinations/1/stock',
        ];
    }

    public function testUpdateCombinationStock(): int
    {
        // Create an attribute group with one attribute, then a product with one combination
        $attributeGroup = $this->createItem('/attributes/groups', [
            'names' => [
                'en-US' => 'Flavor',
                'fr-FR' => 'Parfum',
            ],
            'publicNames' => [
                'en-US' => 'Flavor',
                'fr-FR' => 'Parfum',
            ],
            'type' => 'select',
            'shopIds' => [1],
        ], ['attribute_group_write']);
        $attribute = $this->createItem('/attributes/attributes', [
            'names' => [
                'en-US' => 'Vanilla',
                'fr-FR' => 'Vanille',
            ],
            'attributeGroupId' => $attributeGroup['attributeGroupId'],
            'color' => '',
            'shopIds' => [1],
        ], ['attribute_write']);

        $product = $this->createItem('/products', [
            'type' => ProductType::TYPE_COMBINATIONS,
            'names' => [
                'en-US' => 'product with combination stock',
                'fr-FR' => 'produit avec stock de declinaison',
            ],
        ], ['product_write']);

        $generatedCombinations = $this->createItem(sprintf('/products/%d/generate-combinations', $product['productId']), [
            'groupedAttributes' => [
                [
                    'attributeGroupId' => $attributeGroup['attributeGroupId'],
                    'attributeIds' => [$attribute['attributeId']],
                ],
            ],
        ], ['product_write']);
        $combinationId = $generatedCombinations['newCombinationIds'][0];

        $updatedStock = $this->updateItem(sprintf('/products/combinations/%d/stock', $combinationId), [
            'deltaQuantity' => 8,
            'location' => 'combination shelf',
        ], ['product_write']);

        $this->assertEquals(
            [
                'combinationId' => $combinationId,
                'quantity' => 8,
                'location' => 'combination shelf',
            ],
            $updatedStock
        );

        return $combinationId;
    }

    /**
     * @depends testUpdateCombinationStock
     */
    public function testDecreaseCombinationStock(int $combinationId): void
    {
        $updatedStock = $this->updateItem(sprintf('/products/combinations/%d/stock', $combinationId), [
            'deltaQuantity' => -3,
        ], ['product_write']);

        $this->assertEquals(
            [
                'combinationId' => $combinationId,
                'quantity' => 5,
                'location' => 'combination shelf',
            ],
            $updatedStock
        );
    }

    public function testUpdateStockForUnknownCombination(): void
    {
        $this->updateItem('/products/combinations/99999999/stock', [
            'deltaQuantity' => 5,
        ], ['product_write'], Response::HTTP_NOT_FOUND);
    }

    public function testInvalidCombinationStock(): void
    {
        // A zero combination id is invalid (it passes the URI requirements but fails the domain constraint)
        $this->updateItem('/products/combinations/0/stock', [
            'deltaQuantity' => 5,
        ], ['product_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}
