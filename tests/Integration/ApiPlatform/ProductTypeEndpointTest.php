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

class ProductTypeEndpointTest extends ApiTestCase
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
        yield 'change product type endpoint' => ['PATCH', '/products/1/change-type'];
    }

    public function testChangeType(): int
    {
        $product = $this->createItem('/products', [
            'type' => ProductType::TYPE_STANDARD,
            'names' => [
                'en-US' => 'product changing type',
                'fr-FR' => 'produit changeant de type',
            ],
        ], ['product_write']);
        $this->assertArrayHasKey('productId', $product);
        $productId = $product['productId'];
        $this->assertSame(ProductType::TYPE_STANDARD, $product['type']);

        // Each change answers the product, only its type changed
        foreach ([ProductType::TYPE_VIRTUAL, ProductType::TYPE_PACK, ProductType::TYPE_STANDARD] as $type) {
            $expectedProduct = array_replace($product, ['type' => $type]);
            $this->assertEquals($expectedProduct, $this->partialUpdateItem('/products/' . $productId . '/change-type', ['type' => $type], ['product_write']));
            $this->assertEquals($expectedProduct, $this->getItem('/products/' . $productId, ['product_read']));
        }

        return $productId;
    }

    /**
     * @depends testChangeType
     */
    public function testChangeInvalidType(int $productId): void
    {
        $expectedProduct = $this->getItem('/products/' . $productId, ['product_read']);

        $response = $this->requestApi('PATCH', '/products/' . $productId . '/change-type', null, ['product_write'], Response::HTTP_UNPROCESSABLE_ENTITY, [
            'body' => '{}',
            'headers' => ['Content-Type' => 'application/json'],
        ]);
        $this->assertValidationErrors([
            ['propertyPath' => 'type', 'message' => 'This value should not be blank.'],
        ], $response);
        $response = $this->partialUpdateItem('/products/' . $productId . '/change-type', ['type' => 'unknown'], ['product_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertValidationErrors([
            ['propertyPath' => 'type', 'message' => 'The value you selected is not a valid choice.'],
        ], $response);

        $this->partialUpdateItem('/products/999999/change-type', ['type' => ProductType::TYPE_VIRTUAL], ['product_write'], Response::HTTP_NOT_FOUND);
        $this->partialUpdateItem('/products/0/change-type', ['type' => ProductType::TYPE_VIRTUAL], ['product_write'], Response::HTTP_UNPROCESSABLE_ENTITY);

        // Nothing changed
        $this->assertEquals($expectedProduct, $this->getItem('/products/' . $productId, ['product_read']));
    }
}
