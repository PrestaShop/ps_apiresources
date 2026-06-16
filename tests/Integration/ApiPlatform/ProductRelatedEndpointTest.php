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

namespace PsApiResourcesTest\Integration\ApiPlatform;

use PrestaShop\PrestaShop\Core\Domain\Product\ValueObject\ProductType;
use Symfony\Component\HttpFoundation\Response;
use Tests\Resources\Resetter\ProductResetter;

class ProductRelatedEndpointTest extends ApiTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        ProductResetter::resetProducts();
        // Pre-create the API Client with the needed scopes, this way we reduce the number of created API Clients
        self::createApiClient(['product_write', 'product_read']);
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        ProductResetter::resetProducts();
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'get related-products endpoint' => [
            'GET',
            '/products/1/related-products',
        ];

        yield 'set related-products endpoint' => [
            'POST',
            '/products/1/related-products',
        ];

        yield 'delete related-products endpoint' => [
            'DELETE',
            '/products/1/related-products',
        ];
    }

    private function createProduct(string $name): int
    {
        $product = $this->createItem('/products', [
            'type' => ProductType::TYPE_STANDARD,
            'names' => [
                'en-US' => $name,
            ],
        ], ['product_write']);
        $this->assertArrayHasKey('productId', $product);

        return $product['productId'];
    }

    public function testSetAndGetRelatedProducts(): int
    {
        $productId = $this->createProduct('related main');
        $firstRelatedId = $this->createProduct('related one');
        $secondRelatedId = $this->createProduct('related two');

        $this->createItem(
            '/products/' . $productId . '/related-products',
            [
                'relatedProductIds' => [$firstRelatedId, $secondRelatedId],
            ],
            ['product_write'],
            Response::HTTP_NO_CONTENT
        );

        $relatedProducts = $this->getItem('/products/' . $productId . '/related-products', ['product_read']);
        $this->assertCount(2, $relatedProducts);
        $relatedIds = array_column($relatedProducts, 'productId');
        $this->assertContains($firstRelatedId, $relatedIds);
        $this->assertContains($secondRelatedId, $relatedIds);

        return $productId;
    }

    /**
     * @depends testSetAndGetRelatedProducts
     */
    public function testRemoveAllRelatedProducts(int $productId): void
    {
        $this->deleteItem('/products/' . $productId . '/related-products', ['product_write']);

        $relatedProducts = $this->getItem('/products/' . $productId . '/related-products', ['product_read']);
        $this->assertCount(0, $relatedProducts);
    }
}
