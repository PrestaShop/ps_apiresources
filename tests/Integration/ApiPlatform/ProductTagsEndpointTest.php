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

class ProductTagsEndpointTest extends ApiTestCase
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
        yield 'set tags endpoint' => [
            'PUT',
            '/products/1/tags',
        ];

        yield 'delete tags endpoint' => [
            'DELETE',
            '/products/1/tags',
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

    public function testSetProductTags(): int
    {
        $productId = $this->createProduct('product with tags');

        $this->updateItem('/products/' . $productId . '/tags', [
            'tags' => [
                'en-US' => ['summer', 'sale'],
            ],
        ], ['product_write'], Response::HTTP_NO_CONTENT);

        $product = $this->getItem('/products/' . $productId, ['product_read']);
        $this->assertArrayHasKey('tags', $product);
        $this->assertArrayHasKey('en-US', $product['tags']);
        $this->assertEqualsCanonicalizing(['summer', 'sale'], $product['tags']['en-US']);

        return $productId;
    }

    /**
     * @depends testSetProductTags
     */
    public function testRemoveAllProductTags(int $productId): void
    {
        $this->deleteItem('/products/' . $productId . '/tags', ['product_write']);

        $product = $this->getItem('/products/' . $productId, ['product_read']);
        $this->assertArrayHasKey('tags', $product);
        $this->assertEmpty($product['tags']['en-US'] ?? []);
    }
}
