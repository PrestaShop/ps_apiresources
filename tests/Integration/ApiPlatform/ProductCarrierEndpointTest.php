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
use Tests\Resources\Resetter\LanguageResetter;
use Tests\Resources\Resetter\ProductResetter;

class ProductCarrierEndpointTest extends ApiTestCase
{
    public static function setUpBeforeClass(): void
    {
        if (self::isVersionUnder('9.2.0')) {
            static::markTestSkipped('The product carriers endpoints rely on GetCarriersForProduct, which only exists since PrestaShop 9.2.0');

            return;
        }

        parent::setUpBeforeClass();
        LanguageResetter::resetLanguages();
        self::addLanguageByLocale('fr-FR');
        self::createApiClient(['carrier_read', 'carrier_write', 'product_write']);
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        LanguageResetter::resetLanguages();
        ProductResetter::resetProducts();
        DatabaseDump::restoreTables([
            'carrier',
            'carrier_group',
            'carrier_lang',
            'carrier_shop',
            'carrier_zone',
            'module_carrier',
            'product_carrier',
        ]);
    }

    public static function getProtectedEndpoints(): iterable
    {
        // Data providers are resolved when PHPUnit builds the test suite, before setUpBeforeClass
        // gets a chance to skip the class, and an empty provider is reported as an error. So the
        // endpoints are yielded unconditionally; on cores < 9.2.0 the whole class is skipped anyway
        // and these data sets are never executed.
        yield 'get carriers for product endpoint' => ['GET', '/products/1/carriers'];
        yield 'set carriers for product endpoint' => ['PUT', '/products/1/carriers'];
    }

    /**
     * @return array{carrierId: int, productId: int}
     */
    public function testAssociateCarrierWithProduct(): array
    {
        $carrier = $this->createItem('/carriers', [
            'name' => 'Carrier for product',
            'delays' => ['en-US' => '3-5 days', 'fr-FR' => '3-5 jours'],
            'grade' => 5,
            'trackingUrl' => 'http://example.com/@',
            'enabled' => true,
            'associatedGroupIds' => [1, 2, 3],
            'additionalHandlingFee' => false,
            'free' => false,
            'shippingMethod' => 2,
            'rangeBehavior' => 0,
            'zones' => [1],
            'associatedShopIds' => [1],
        ], ['carrier_write']);
        $carrierId = $carrier['carrierId'];

        $product = $this->createItem('/products', [
            'type' => ProductType::TYPE_STANDARD,
            'names' => ['en-US' => 'Carrier product', 'fr-FR' => 'Produit transporteur'],
        ], ['product_write']);
        $productId = $product['productId'];

        // Carrier reference id equals carrier id right after creation (they only diverge on later edits).
        $updatedCarriers = $this->updateItem('/products/' . $productId . '/carriers', [
            'carrierReferenceIds' => [$carrierId],
        ], ['product_write']);

        // The association returns the resulting carriers, like the GET operation
        $this->assertEquals(
            ['productId' => $productId, 'carriers' => [['carrierId' => $carrierId, 'name' => 'Carrier for product']]],
            $updatedCarriers
        );

        return ['carrierId' => $carrierId, 'productId' => $productId];
    }

    /**
     * @depends testAssociateCarrierWithProduct
     */
    public function testGetCarriersForProduct(array $fixtures): void
    {
        $this->assertEquals(
            [
                'productId' => $fixtures['productId'],
                'carriers' => [['carrierId' => $fixtures['carrierId'], 'name' => 'Carrier for product']],
            ],
            $this->getItem('/products/' . $fixtures['productId'] . '/carriers', ['carrier_read'])
        );
    }

    /**
     * @depends testAssociateCarrierWithProduct
     */
    public function testSetCarriersForUnknownProduct(array $fixtures): void
    {
        $this->updateItem('/products/99999999/carriers', [
            'carrierReferenceIds' => [$fixtures['carrierId']],
        ], ['product_write'], Response::HTTP_NOT_FOUND);
    }
}
