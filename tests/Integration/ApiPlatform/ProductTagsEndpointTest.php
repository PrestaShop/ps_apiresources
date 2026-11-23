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

class ProductTagsEndpointTest extends ApiTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::resetTables();
        self::createApiClient(['product_read', 'product_write']);
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        self::resetTables();
    }

    protected static function resetTables(): void
    {
        ProductResetter::resetProducts();
        DatabaseDump::restoreTables([
            'tag',
            'tag_count',
        ]);
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'set product tags endpoint' => ['PATCH', '/products/1/tags'];

        // A CQRSDelete answers the product read back from core 9.2.2, the operation is filtered out on older cores
        if (self::isVersionAtLeast('9.2.2')) {
            yield 'remove all product tags endpoint' => ['DELETE', '/products/1/tags'];
        }
    }

    public function testSetTags(): int
    {
        $productId = $this->createProduct();
        $product = $this->getItem('/products/' . $productId, ['product_read']);
        $this->assertSame([], $product['tags']);

        $updatedProduct = $this->partialUpdateItem('/products/' . $productId . '/tags', [
            'tags' => [
                'en-US' => ['apitag-red', 'apitag-blue'],
                'fr-FR' => ['apitag-rouge'],
            ],
        ], ['product_write']);
        $expectedProduct = array_replace($product, [
            'tags' => [
                'en-US' => ['apitag-blue', 'apitag-red'],
                'fr-FR' => ['apitag-rouge'],
            ],
        ]);
        $this->assertEquals($expectedProduct, $this->sortTags($updatedProduct));
        $this->assertEquals($expectedProduct, $this->sortTags($this->getItem('/products/' . $productId, ['product_read'])));

        // Only the languages sent are replaced
        $updatedProduct = $this->partialUpdateItem('/products/' . $productId . '/tags', [
            'tags' => [
                'fr-FR' => ['apitag-bleu'],
            ],
        ], ['product_write']);
        $expectedProduct = array_replace($product, [
            'tags' => [
                'en-US' => ['apitag-blue', 'apitag-red'],
                'fr-FR' => ['apitag-bleu'],
            ],
        ]);
        $this->assertEquals($expectedProduct, $this->sortTags($updatedProduct));
        $this->assertEquals($expectedProduct, $this->sortTags($this->getItem('/products/' . $productId, ['product_read'])));

        // An empty list removes the tags of its language, a language without tags is not listed
        $updatedProduct = $this->partialUpdateItem('/products/' . $productId . '/tags', [
            'tags' => [
                'fr-FR' => [],
            ],
        ], ['product_write']);
        $expectedProduct = array_replace($product, [
            'tags' => [
                'en-US' => ['apitag-blue', 'apitag-red'],
            ],
        ]);
        $this->assertEquals($expectedProduct, $this->sortTags($updatedProduct));
        $this->assertEquals($expectedProduct, $this->sortTags($this->getItem('/products/' . $productId, ['product_read'])));

        return $productId;
    }

    /**
     * @depends testSetTags
     */
    public function testSetInvalidTags(int $productId): void
    {
        $expectedProduct = $this->getItem('/products/' . $productId, ['product_read']);

        // A missing list and an empty list give the same violation
        foreach ([[], ['tags' => []]] as $body) {
            $response = $this->requestApi('PATCH', '/products/' . $productId . '/tags', null, ['product_write'], Response::HTTP_UNPROCESSABLE_ENTITY, [
                'body' => json_encode((object) $body),
                'headers' => ['Content-Type' => 'application/json'],
            ]);
            $this->assertValidationErrors([
                ['propertyPath' => 'tags', 'message' => 'This value should not be blank.'],
            ], $response);
        }

        // The tags of a language are a list of strings
        $response = $this->partialUpdateItem('/products/' . $productId . '/tags', ['tags' => ['en-US' => 'apitag-red']], ['product_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertValidationErrors([
            ['message' => 'This value should be of type array.'],
        ], $response);
        $response = $this->partialUpdateItem('/products/' . $productId . '/tags', ['tags' => ['en-US' => [12]]], ['product_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertValidationErrors([
            ['message' => 'This value should be of type string.'],
        ], $response);

        // A tag refused by the core (no <>{} characters)
        $this->partialUpdateItem('/products/' . $productId . '/tags', ['tags' => ['en-US' => ['<b>bold</b>']]], ['product_write'], Response::HTTP_UNPROCESSABLE_ENTITY);

        // Unknown and invalid products
        $this->partialUpdateItem('/products/999999/tags', ['tags' => ['en-US' => ['apitag-red']]], ['product_write'], Response::HTTP_NOT_FOUND);
        $this->partialUpdateItem('/products/0/tags', ['tags' => ['en-US' => ['apitag-red']]], ['product_write'], Response::HTTP_UNPROCESSABLE_ENTITY);

        // Nothing changed
        $this->assertEquals($this->sortTags($expectedProduct), $this->sortTags($this->getItem('/products/' . $productId, ['product_read'])));
    }

    public function testRemoveAllTagsIsFilteredOutOnOlderCores(): void
    {
        if (self::isVersionAtLeast('9.2.2')) {
            $this->markTestSkipped('The DELETE operation exists from PrestaShop 9.2.2');
        }

        // The resource still loads: only the DELETE operation is missing on the URI
        $this->deleteItem('/products/1/tags', ['product_write'], Response::HTTP_METHOD_NOT_ALLOWED);
    }

    /**
     * @depends testSetTags
     */
    public function testRemoveAllTags(int $productId): void
    {
        $this->markTestSkippedByMinVersion('9.2.2');

        $product = $this->getItem('/products/' . $productId, ['product_read']);
        $this->assertNotEmpty($product['tags']);

        $expectedProduct = array_replace($product, ['tags' => []]);
        $this->assertEquals($expectedProduct, $this->deleteItem('/products/' . $productId . '/tags', ['product_write'], Response::HTTP_OK));
        $this->assertEquals($expectedProduct, $this->getItem('/products/' . $productId, ['product_read']));

        $this->deleteItem('/products/999999/tags', ['product_write'], Response::HTTP_NOT_FOUND);
        $this->deleteItem('/products/0/tags', ['product_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    private function createProduct(): int
    {
        $product = $this->createItem('/products', [
            'type' => ProductType::TYPE_STANDARD,
            'names' => [
                'en-US' => 'product with tags',
                'fr-FR' => 'produit avec tags',
            ],
        ], ['product_write']);
        $this->assertArrayHasKey('productId', $product);

        return $product['productId'];
    }

    /**
     * The core reads the tags of a language in no guaranteed order.
     */
    private function sortTags(array $product): array
    {
        foreach ($product['tags'] as $locale => $tags) {
            sort($tags);
            $product['tags'][$locale] = $tags;
        }

        return $product;
    }
}
