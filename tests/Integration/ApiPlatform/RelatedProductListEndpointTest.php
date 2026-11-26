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
use Tests\Resources\Resetter\ProductResetter;

class RelatedProductListEndpointTest extends ApiTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        ProductResetter::resetProducts();
        self::createApiClient(['product_read', 'product_write']);
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        ProductResetter::resetProducts();
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'list related products endpoint' => ['GET', '/products/1/related-products'];

        // The write operations use the CQRS*Collection operations of core 9.2.2, filtered out on older cores
        if (self::isVersionAtLeast('9.2.2')) {
            yield 'set related products endpoint' => ['PUT', '/products/1/related-products'];
            yield 'remove all related products endpoint' => ['DELETE', '/products/1/related-products'];
        }
    }

    public function testListRelatedProducts(): array
    {
        $productIds = [
            'main' => $this->createProduct('main product', 'produit principal'),
            'first' => $this->createProduct('first related product', 'premier produit lie'),
            'second' => $this->createProduct('second related product', 'second produit lie'),
        ];

        // A product without related products
        $this->assertSame([], $this->getItem('/products/' . $productIds['main'] . '/related-products', ['product_read']));

        $this->getItem('/products/999999/related-products', ['product_read'], Response::HTTP_NOT_FOUND);
        $this->getItem('/products/0/related-products', ['product_read'], Response::HTTP_UNPROCESSABLE_ENTITY);

        return $productIds;
    }

    public function testWriteOperationsAreFilteredOutOnOlderCores(): void
    {
        if (self::isVersionAtLeast('9.2.2')) {
            $this->markTestSkipped('The write operations exist from PrestaShop 9.2.2');
        }

        // The resource still loads: only its write operations are missing
        $this->updateItem('/products/1/related-products', ['relatedProductIds' => [2]], ['product_write'], Response::HTTP_METHOD_NOT_ALLOWED);
        $this->deleteItem('/products/1/related-products', ['product_write'], Response::HTTP_METHOD_NOT_ALLOWED);
    }

    /**
     * @depends testListRelatedProducts
     */
    public function testSetRelatedProducts(array $productIds): array
    {
        $this->markTestSkippedByMinVersion('9.2.2');

        $relatedProducts = $this->updateItem('/products/' . $productIds['main'] . '/related-products', [
            'relatedProductIds' => [$productIds['first'], $productIds['second']],
        ], ['product_write']);
        $expectedRelatedProducts = [
            $this->expectedRelatedProduct($productIds['first'], 'first related product', $relatedProducts),
            $this->expectedRelatedProduct($productIds['second'], 'second related product', $relatedProducts),
        ];
        $this->assertEquals($expectedRelatedProducts, $this->sortByProductId($relatedProducts));
        $this->assertEquals($expectedRelatedProducts, $this->sortByProductId($this->getItem('/products/' . $productIds['main'] . '/related-products', ['product_read'])));

        // The list is replaced
        $relatedProducts = $this->updateItem('/products/' . $productIds['main'] . '/related-products', [
            'relatedProductIds' => [$productIds['second']],
        ], ['product_write']);
        $expectedRelatedProducts = [
            $this->expectedRelatedProduct($productIds['second'], 'second related product', $relatedProducts),
        ];
        $this->assertEquals($expectedRelatedProducts, $relatedProducts);
        $this->assertEquals($expectedRelatedProducts, $this->getItem('/products/' . $productIds['main'] . '/related-products', ['product_read']));

        return $productIds;
    }

    /**
     * @depends testSetRelatedProducts
     */
    public function testSetInvalidRelatedProducts(array $productIds): void
    {
        $uri = '/products/' . $productIds['main'] . '/related-products';
        $expectedRelatedProducts = $this->getItem($uri, ['product_read']);

        // A missing list, an explicit null and an empty list give the same violation
        foreach ([[], ['relatedProductIds' => null], ['relatedProductIds' => []]] as $body) {
            $response = $this->requestApi('PUT', $uri, null, ['product_write'], Response::HTTP_UNPROCESSABLE_ENTITY, [
                'body' => json_encode((object) $body),
                'headers' => ['Content-Type' => 'application/json'],
            ]);
            $this->assertValidationErrors([
                ['propertyPath' => 'relatedProductIds', 'message' => 'This value should not be blank.'],
            ], $response);
        }
        $response = $this->updateItem($uri, ['relatedProductIds' => ['3abc', 0]], ['product_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertValidationErrors([
            ['propertyPath' => 'relatedProductIds[0]', 'message' => 'This value should be of type integer.'],
            ['propertyPath' => 'relatedProductIds[1]', 'message' => 'This value should be positive.'],
        ], $response);

        // An unknown related product answers 404
        $this->updateItem($uri, ['relatedProductIds' => [$productIds['first'], 999999]], ['product_write'], Response::HTTP_NOT_FOUND);

        // Unknown and invalid products of the URI
        $this->updateItem('/products/999999/related-products', ['relatedProductIds' => [$productIds['first']]], ['product_write'], Response::HTTP_NOT_FOUND);
        $this->updateItem('/products/0/related-products', ['relatedProductIds' => [$productIds['first']]], ['product_write'], Response::HTTP_UNPROCESSABLE_ENTITY);

        // Nothing changed
        $this->assertEquals($expectedRelatedProducts, $this->getItem($uri, ['product_read']));
    }

    /**
     * @depends testSetRelatedProducts
     */
    public function testRemoveAllRelatedProducts(array $productIds): void
    {
        $uri = '/products/' . $productIds['main'] . '/related-products';
        $this->assertNotEmpty($this->getItem($uri, ['product_read']));

        $this->assertSame([], $this->deleteItem($uri, ['product_write'], Response::HTTP_OK));
        $this->assertSame([], $this->getItem($uri, ['product_read']));

        $this->deleteItem('/products/999999/related-products', ['product_write'], Response::HTTP_NOT_FOUND);
        $this->deleteItem('/products/0/related-products', ['product_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    private function expectedRelatedProduct(int $productId, string $name, array $relatedProducts): array
    {
        // The URL of the cover image (the "no image" picture here) depends on the shop domain, it is checked by format
        $imageUrl = null;
        foreach ($relatedProducts as $relatedProduct) {
            if ($relatedProduct['productId'] === $productId) {
                $imageUrl = $relatedProduct['imageUrl'];
            }
        }
        $this->assertIsString($imageUrl);
        $this->assertMatchesRegularExpression('#^https?://.+\.jpg$#', $imageUrl);

        return [
            'productId' => $productId,
            'name' => $name,
            'reference' => '',
            'imageUrl' => $imageUrl,
        ];
    }

    /**
     * The core reads the related products in no guaranteed order.
     */
    private function sortByProductId(array $relatedProducts): array
    {
        usort($relatedProducts, static fn (array $a, array $b): int => $a['productId'] <=> $b['productId']);

        return $relatedProducts;
    }

    private function createProduct(string $englishName, string $frenchName): int
    {
        $product = $this->createItem('/products', [
            'type' => ProductType::TYPE_STANDARD,
            'names' => [
                'en-US' => $englishName,
                'fr-FR' => $frenchName,
            ],
        ], ['product_write']);
        $this->assertArrayHasKey('productId', $product);

        return $product['productId'];
    }
}
