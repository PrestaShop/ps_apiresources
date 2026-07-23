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

use PrestaShop\PrestaShop\Core\Domain\Product\Command\SetCarriersCommand;
use PrestaShop\PrestaShop\Core\Domain\Product\ValueObject\ProductType;
use PrestaShop\PrestaShop\Core\Domain\Shop\ValueObject\ShopConstraint;
use Tests\Resources\DatabaseDump;
use Tests\Resources\Resetter\LanguageResetter;
use Tests\Resources\Resetter\ProductResetter;

class CarrierForProductEndpointTest extends ApiTestCase
{
    public static function setUpBeforeClass(): void
    {
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
        yield 'get carriers for product endpoint' => ['GET', '/products/1/carriers'];
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

        $container = static::createClient()->getContainer();
        $commandBus = $container->get('prestashop.core.command_bus');
        // Carrier reference id equals carrier id right after creation (they only diverge on later edits).
        $commandBus->handle(new SetCarriersCommand($productId, [$carrierId], ShopConstraint::allShops()));

        return ['carrierId' => $carrierId, 'productId' => $productId];
    }

    /**
     * @depends testAssociateCarrierWithProduct
     */
    public function testGetCarriersForProduct(array $fixtures): void
    {
        $carriers = $this->getItem('/products/' . $fixtures['productId'] . '/carriers', ['carrier_read']);
        $this->assertIsArray($carriers);
        $carrierIds = array_column($carriers, 'carrierId');
        $this->assertContains($fixtures['carrierId'], $carrierIds);
    }
}
