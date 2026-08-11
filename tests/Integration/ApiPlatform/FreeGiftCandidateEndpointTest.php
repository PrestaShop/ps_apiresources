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
use PrestaShop\PrestaShop\Core\Version;
use Tests\Resources\Resetter\ConfigurationResetter;
use Tests\Resources\Resetter\ProductResetter;

class FreeGiftCandidateEndpointTest extends ApiTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        ProductResetter::resetProducts();
        self::createApiClient(['product_write', 'product_read']);
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        ProductResetter::resetProducts();
        ConfigurationResetter::resetConfiguration();
    }

    public static function getProtectedEndpoints(): iterable
    {
        // The endpoint is only exposed on cores that ship SearchProductsForFreeGift (9.2+)
        if (version_compare(Version::VERSION, '9.2.0', '>=')) {
            yield 'get free gift candidates endpoint' => [
                'GET',
                '/products/free-gift-candidates',
            ];
        }
    }

    public function testSearchFreeGiftCandidates(): void
    {
        $this->markTestSkippedByMinVersion('9.2.0');

        $product = $this->createItem('/products', [
            'type' => ProductType::TYPE_STANDARD,
            'names' => [
                'en-US' => 'free gift candidate',
                'fr-FR' => 'cadeau candidat',
            ],
        ], ['product_write']);
        $this->assertArrayHasKey('productId', $product);
        $productId = $product['productId'];

        // The new product has no stock and the default shop configuration denies ordering
        // out-of-stock products, so the candidate is disabled
        $this->assertEquals(
            [
                [
                    'productId' => $productId,
                    'name' => 'free gift candidate',
                    'reference' => '',
                    'imageUrl' => 'http://myshop.com/img/p/en-default-home_default.jpg',
                    'productType' => ProductType::TYPE_STANDARD,
                    'disabled' => true,
                    'disabledReason' => 'This product is out of stock.',
                ],
            ],
            $this->getItem('/products/free-gift-candidates?phrase=free gift candidate', ['product_read'])
        );

        // Once out-of-stock ordering is allowed the same product becomes an eligible candidate
        self::updateConfiguration('PS_ORDER_OUT_OF_STOCK', 1);
        $this->assertEquals(
            [
                [
                    'productId' => $productId,
                    'name' => 'free gift candidate',
                    'reference' => '',
                    'imageUrl' => 'http://myshop.com/img/p/en-default-home_default.jpg',
                    'productType' => ProductType::TYPE_STANDARD,
                    'disabled' => false,
                ],
            ],
            $this->getItem('/products/free-gift-candidates?phrase=free gift candidate', ['product_read'])
        );
    }

    public function testSearchWithoutMatch(): void
    {
        $this->markTestSkippedByMinVersion('9.2.0');

        $this->assertEquals(
            [],
            $this->getItem('/products/free-gift-candidates?phrase=no product matches this', ['product_read'])
        );
    }
}
