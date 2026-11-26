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

class ProductPackedProductListEndpointTest extends ApiTestCase
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
        yield 'list pack products endpoint' => ['GET', '/products/1/pack-products'];

        // The write operations use the CQRS*Collection operations of core 9.2.2, filtered out on older cores
        if (self::isVersionAtLeast('9.2.2')) {
            yield 'set pack products endpoint' => ['PUT', '/products/1/pack-products'];
            yield 'remove all pack products endpoint' => ['DELETE', '/products/1/pack-products'];
        }
    }

    public function testListPackProducts(): array
    {
        $productIds = [
            'pack' => $this->createProduct('pack of products', 'pack de produits', ProductType::TYPE_PACK),
            'first' => $this->createProduct('first packed product', 'premier produit du pack', ProductType::TYPE_STANDARD),
            'second' => $this->createProduct('second packed product', 'second produit du pack', ProductType::TYPE_STANDARD),
        ];

        // An empty pack, and a product that is not a pack
        $this->assertSame([], $this->getItem('/products/' . $productIds['pack'] . '/pack-products', ['product_read']));
        $this->assertSame([], $this->getItem('/products/' . $productIds['first'] . '/pack-products', ['product_read']));

        $this->getItem('/products/999999/pack-products', ['product_read'], Response::HTTP_NOT_FOUND);
        $this->getItem('/products/0/pack-products', ['product_read'], Response::HTTP_UNPROCESSABLE_ENTITY);

        return $productIds;
    }

    public function testWriteOperationsAreFilteredOutOnOlderCores(): void
    {
        if (self::isVersionAtLeast('9.2.2')) {
            $this->markTestSkipped('The write operations exist from PrestaShop 9.2.2');
        }

        // The resource still loads: only its write operations are missing
        $this->updateItem('/products/1/pack-products', ['products' => []], ['product_write'], Response::HTTP_METHOD_NOT_ALLOWED);
        $this->deleteItem('/products/1/pack-products', ['product_write'], Response::HTTP_METHOD_NOT_ALLOWED);
    }

    /**
     * @depends testListPackProducts
     */
    public function testSetPackProducts(array $productIds): array
    {
        $this->markTestSkippedByMinVersion('9.2.2');
        $uri = '/products/' . $productIds['pack'] . '/pack-products';

        $packProducts = $this->updateItem($uri, [
            'products' => [
                ['productId' => $productIds['first'], 'quantity' => 2],
                ['productId' => $productIds['second'], 'quantity' => 1, 'combinationId' => 0],
            ],
        ], ['product_write']);
        $expectedPackProducts = [
            $this->expectedPackProduct($productIds['first'], 2, 'first packed product', $packProducts),
            $this->expectedPackProduct($productIds['second'], 1, 'second packed product', $packProducts),
        ];
        $this->assertEquals($expectedPackProducts, $packProducts);
        $this->assertEquals($expectedPackProducts, $this->getItem($uri, ['product_read']));

        // The products are replaced
        $packProducts = $this->updateItem($uri, [
            'products' => [
                ['productId' => $productIds['second'], 'quantity' => 3],
            ],
        ], ['product_write']);
        $expectedPackProducts = [
            $this->expectedPackProduct($productIds['second'], 3, 'second packed product', $packProducts),
        ];
        $this->assertEquals($expectedPackProducts, $packProducts);
        $this->assertEquals($expectedPackProducts, $this->getItem($uri, ['product_read']));

        // A product contained in a pack cannot become a pack
        $this->partialUpdateItem('/products/' . $productIds['second'] . '/change-type', ['type' => ProductType::TYPE_PACK], ['product_write'], Response::HTTP_UNPROCESSABLE_ENTITY);

        return $productIds;
    }

    /**
     * @depends testSetPackProducts
     */
    public function testSetInvalidPackProducts(array $productIds): void
    {
        $uri = '/products/' . $productIds['pack'] . '/pack-products';
        $expectedPackProducts = $this->getItem($uri, ['product_read']);

        // A missing list, an explicit null and an empty list give the same violation
        foreach ([[], ['products' => null], ['products' => []]] as $body) {
            $response = $this->requestApi('PUT', $uri, null, ['product_write'], Response::HTTP_UNPROCESSABLE_ENTITY, [
                'body' => json_encode((object) $body),
                'headers' => ['Content-Type' => 'application/json'],
            ]);
            $this->assertValidationErrors([
                ['propertyPath' => 'products', 'message' => 'This value should not be blank.'],
            ], $response);
        }

        // The items are validated before the command is built
        $response = $this->updateItem($uri, [
            'products' => [
                ['quantity' => 0],
                ['productId' => -1, 'quantity' => 1, 'combinationId' => -2],
            ],
        ], ['product_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertValidationErrors([
            ['propertyPath' => 'products[0].productId', 'message' => 'This value should not be null.'],
            ['propertyPath' => 'products[0].quantity', 'message' => 'This value should be positive.'],
            ['propertyPath' => 'products[1].productId', 'message' => 'This value should be positive.'],
            ['propertyPath' => 'products[1].combinationId', 'message' => 'This value should be either positive or zero.'],
        ], $response);

        // A pack cannot be packed, and only a pack gets products
        $this->updateItem($uri, ['products' => [['productId' => $productIds['pack'], 'quantity' => 1]]], ['product_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->updateItem('/products/' . $productIds['first'] . '/pack-products', ['products' => [['productId' => $productIds['second'], 'quantity' => 1]]], ['product_write'], Response::HTTP_UNPROCESSABLE_ENTITY);

        // Unknown and invalid packs
        $this->updateItem('/products/999999/pack-products', ['products' => [['productId' => $productIds['first'], 'quantity' => 1]]], ['product_write'], Response::HTTP_NOT_FOUND);
        $this->updateItem('/products/0/pack-products', ['products' => [['productId' => $productIds['first'], 'quantity' => 1]]], ['product_write'], Response::HTTP_UNPROCESSABLE_ENTITY);

        // Nothing changed
        $this->assertEquals($expectedPackProducts, $this->getItem($uri, ['product_read']));
    }

    /**
     * @depends testSetPackProducts
     */
    public function testRemoveAllPackProducts(array $productIds): void
    {
        $uri = '/products/' . $productIds['pack'] . '/pack-products';
        $this->assertNotEmpty($this->getItem($uri, ['product_read']));

        $this->assertSame([], $this->deleteItem($uri, ['product_write'], Response::HTTP_OK));
        $this->assertSame([], $this->getItem($uri, ['product_read']));

        // Only a pack can be emptied
        $this->deleteItem('/products/' . $productIds['first'] . '/pack-products', ['product_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->deleteItem('/products/999999/pack-products', ['product_write'], Response::HTTP_NOT_FOUND);
        $this->deleteItem('/products/0/pack-products', ['product_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    private function expectedPackProduct(int $productId, int $quantity, string $name, array $packProducts): array
    {
        // The URL of the cover image (the "no image" picture here) depends on the shop domain, it is checked by format
        $imageUrl = null;
        foreach ($packProducts as $packProduct) {
            if ($packProduct['productId'] === $productId) {
                $imageUrl = $packProduct['imageUrl'];
            }
        }
        $this->assertIsString($imageUrl);
        $this->assertMatchesRegularExpression('#^https?://.+\.jpg$#', $imageUrl);

        return [
            'productId' => $productId,
            'combinationId' => 0,
            'quantity' => $quantity,
            'productName' => $name,
            // The products have no reference
            'reference' => '',
            'imageUrl' => $imageUrl,
        ];
    }

    private function createProduct(string $englishName, string $frenchName, string $type): int
    {
        $product = $this->createItem('/products', [
            'type' => $type,
            'names' => [
                'en-US' => $englishName,
                'fr-FR' => $frenchName,
            ],
        ], ['product_write']);
        $this->assertArrayHasKey('productId', $product);

        return $product['productId'];
    }
}
