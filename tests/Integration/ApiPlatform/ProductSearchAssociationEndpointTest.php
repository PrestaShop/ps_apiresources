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
use Tests\Resources\Resetter\ProductResetter;

class ProductSearchAssociationEndpointTest extends ApiTestCase
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
        yield 'search for association endpoint' => [
            'GET',
            '/products/search-for-associations?phrase=test',
        ];
    }

    public function testSearchProductsForAssociation(): void
    {
        $product = $this->createItem('/products', [
            'type' => ProductType::TYPE_STANDARD,
            'names' => ['en-US' => 'Zztopassociationproduct'],
        ], ['product_write']);
        $this->assertArrayHasKey('productId', $product);
        $productId = $product['productId'];

        $results = $this->getItem('/products/search-for-associations?phrase=Zztopassociation&limit=10', ['product_read']);
        $this->assertIsArray($results);
        $productIds = array_column($results, 'productId');
        $this->assertContains($productId, $productIds);
    }
}
