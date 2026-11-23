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

class BulkProductsEndpointTest extends ApiTestCase
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
        yield 'bulk update status endpoint' => ['PATCH', '/products/bulk-update-status'];
        yield 'bulk duplicate endpoint' => ['POST', '/products/bulk-duplicate'];
        yield 'bulk delete endpoint' => ['DELETE', '/products/bulk-delete'];
    }

    public function testBulkUpdateStatus(): array
    {
        $productIds = [
            $this->createProduct('bulk product one', 'produit groupe un'),
            $this->createProduct('bulk product two', 'produit groupe deux'),
        ];
        $products = [];
        foreach ($productIds as $productId) {
            $products[$productId] = $this->getItem('/products/' . $productId, ['product_read']);
            $this->assertFalse($products[$productId]['enabled']);
        }

        foreach ([true, false, true] as $enabled) {
            $this->assertNull($this->partialUpdateItem('/products/bulk-update-status', [
                'productIds' => $productIds,
                'enabled' => $enabled,
            ], ['product_write'], Response::HTTP_NO_CONTENT));
            foreach ($productIds as $productId) {
                $this->assertEquals(
                    array_replace($products[$productId], ['enabled' => $enabled]),
                    $this->getItem('/products/' . $productId, ['product_read'])
                );
            }
        }

        return $productIds;
    }

    /**
     * @depends testBulkUpdateStatus
     */
    public function testBulkUpdateStatusWithErrors(array $productIds): void
    {
        // An unknown product doesn't stop the others, the failure is reported once all the products are processed
        $this->partialUpdateItem('/products/bulk-update-status', [
            'productIds' => [$productIds[0], 999999],
            'enabled' => false,
        ], ['product_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertFalse($this->getItem('/products/' . $productIds[0], ['product_read'])['enabled']);
        $this->assertTrue($this->getItem('/products/' . $productIds[1], ['product_read'])['enabled']);

        $response = $this->partialUpdateItem('/products/bulk-update-status', ['productIds' => $productIds], ['product_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertValidationErrors([
            ['propertyPath' => 'enabled', 'message' => 'This value should not be null.'],
        ], $response);
        $this->assertBulkIdsValidation('PATCH', '/products/bulk-update-status', ['enabled' => true]);
    }

    /**
     * @depends testBulkUpdateStatus
     */
    public function testBulkDuplicate(array $productIds): void
    {
        $productsNumber = $this->countItems('/products', ['product_read']);

        $this->assertNull($this->createItem('/products/bulk-duplicate', [
            'productIds' => $productIds,
        ], ['product_write'], Response::HTTP_NO_CONTENT));
        $this->assertEquals($productsNumber + 2, $this->countItems('/products', ['product_read']));

        // Each copy is named after its source and disabled
        foreach (['bulk product one', 'bulk product two'] as $name) {
            $copies = $this->listItems('/products', ['product_read'], ['name' => 'copy of ' . $name]);
            $this->assertEquals(1, $copies['totalItems']);
            $this->assertSame('copy of ' . $name, $copies['items'][0]['name']);
            $this->assertFalse($copies['items'][0]['enabled']);
            $this->assertNotContains($copies['items'][0]['productId'], $productIds);
        }

        // An unknown product doesn't stop the others, the failure is reported once all the products are processed
        $this->createItem('/products/bulk-duplicate', [
            'productIds' => [$productIds[0], 999999],
        ], ['product_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertEquals($productsNumber + 3, $this->countItems('/products', ['product_read']));

        $this->assertBulkIdsValidation('POST', '/products/bulk-duplicate');
        $this->assertEquals($productsNumber + 3, $this->countItems('/products', ['product_read']));
    }

    /**
     * @depends testBulkUpdateStatus
     */
    public function testBulkDelete(array $productIds): void
    {
        $productsNumber = $this->countItems('/products', ['product_read']);
        $this->assertBulkIdsValidation('DELETE', '/products/bulk-delete');

        $this->assertNull($this->bulkDeleteItems('/products/bulk-delete', [
            'productIds' => $productIds,
        ], ['product_write']));
        $this->assertEquals($productsNumber - 2, $this->countItems('/products', ['product_read']));
        foreach ($productIds as $productId) {
            $this->getItem('/products/' . $productId, ['product_read'], Response::HTTP_NOT_FOUND);
        }

        // An unknown product doesn't stop the others, the failure is reported once all the products are processed
        $productId = $this->createProduct('bulk product three', 'produit groupe trois');
        $this->bulkDeleteItems('/products/bulk-delete', [
            'productIds' => [$productId, 999999],
        ], ['product_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->getItem('/products/' . $productId, ['product_read'], Response::HTTP_NOT_FOUND);
    }

    private function assertBulkIdsValidation(string $method, string $uri, array $extraData = []): void
    {
        $response = $this->requestApi($method, $uri, null, ['product_write'], Response::HTTP_UNPROCESSABLE_ENTITY, [
            'body' => json_encode((object) $extraData),
            'headers' => ['Content-Type' => 'application/json'],
        ]);
        $this->assertValidationErrors([
            ['propertyPath' => 'productIds', 'message' => 'This value should not be blank.'],
        ], $response);
        $response = $this->requestApi($method, $uri, ['productIds' => []] + $extraData, ['product_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertValidationErrors([
            ['propertyPath' => 'productIds', 'message' => 'This value should not be blank.'],
        ], $response);
        $response = $this->requestApi($method, $uri, ['productIds' => ['3abc', 0]] + $extraData, ['product_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertValidationErrors([
            ['propertyPath' => 'productIds[0]', 'message' => 'This value should be of type integer.'],
            ['propertyPath' => 'productIds[1]', 'message' => 'This value should be positive.'],
        ], $response);
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
