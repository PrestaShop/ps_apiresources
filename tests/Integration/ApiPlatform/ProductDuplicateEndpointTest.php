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

class ProductDuplicateEndpointTest extends ApiTestCase
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
        yield 'duplicate product endpoint' => ['POST', '/products/1/duplicate'];
    }

    public function testDuplicateProduct(): void
    {
        $product = $this->createItem('/products', [
            'type' => ProductType::TYPE_STANDARD,
            'names' => [
                'en-US' => 'product to duplicate',
                'fr-FR' => 'produit a dupliquer',
            ],
        ], ['product_write']);
        $this->assertArrayHasKey('productId', $product);
        $productId = $product['productId'];
        // The copy is disabled whatever the status of its source
        $product = $this->partialUpdateItem('/products/' . $productId, [
            'enabled' => true,
            'descriptions' => [
                'en-US' => 'description to copy',
                'fr-FR' => 'description a copier',
            ],
        ], ['product_write']);
        $productsNumber = $this->countItems('/products', ['product_read']);

        $duplicatedProduct = $this->createItem('/products/' . $productId . '/duplicate', null, ['product_write']);
        $this->assertArrayHasKey('productId', $duplicatedProduct);
        $duplicatedProductId = $duplicatedProduct['productId'];
        $this->assertNotEquals($productId, $duplicatedProductId);
        $this->assertEquals($productsNumber + 1, $this->countItems('/products', ['product_read']));

        // The names and link rewrites are prefixed with "copy of", translated in each language: the translation of
        // the other languages depends on the installed language packs
        $this->assertSame('copy of product to duplicate', $duplicatedProduct['names']['en-US']);
        $this->assertSame('copy-of-product-to-duplicate', $duplicatedProduct['linkRewrites']['en-US']);
        $this->assertStringContainsString('produit a dupliquer', $duplicatedProduct['names']['fr-FR']);
        $this->assertStringContainsString('produit-a-dupliquer', $duplicatedProduct['linkRewrites']['fr-FR']);

        $expectedProduct = array_replace($product, [
            'productId' => $duplicatedProductId,
            'enabled' => false,
            'names' => $duplicatedProduct['names'],
            'linkRewrites' => $duplicatedProduct['linkRewrites'],
        ]);
        $this->assertEquals($expectedProduct, $duplicatedProduct);
        $this->assertEquals($expectedProduct, $this->getItem('/products/' . $duplicatedProductId, ['product_read']));

        // The source is unchanged
        $this->assertEquals($product, $this->getItem('/products/' . $productId, ['product_read']));
    }

    public function testDuplicateUnknownProduct(): void
    {
        $productsNumber = $this->countItems('/products', ['product_read']);
        $this->createItem('/products/999999/duplicate', null, ['product_write'], Response::HTTP_NOT_FOUND);
        $this->createItem('/products/0/duplicate', null, ['product_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertEquals($productsNumber, $this->countItems('/products', ['product_read']));
    }
}
