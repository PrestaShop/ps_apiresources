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

use PrestaShop\PrestaShop\Core\Domain\Address\Command\AddCustomerAddressCommand;
use PrestaShop\PrestaShop\Core\Domain\Customer\Command\AddCustomerCommand;
use PrestaShop\PrestaShop\Core\Domain\Product\Command\SetCarriersCommand;
use PrestaShop\PrestaShop\Core\Domain\Product\ValueObject\ProductType;
use PrestaShop\PrestaShop\Core\Domain\Shop\ValueObject\ShopConstraint;
use Symfony\Component\HttpFoundation\Response;
use Tests\Resources\DatabaseDump;
use Tests\Resources\Resetter\LanguageResetter;
use Tests\Resources\Resetter\ProductResetter;

class AvailableCarriersEndpointTest extends ApiTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        LanguageResetter::resetLanguages();
        self::addLanguageByLocale('fr-FR');
        self::createApiClient(['carrier_read', 'carrier_write', 'product_write', 'country_write']);
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
            'country',
            'country_lang',
            'country_shop',
            'address_format',
            'customer',
            'customer_group',
            'address',
        ]);
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'get available carriers endpoint' => ['GET', '/carriers/available'];
    }

    /**
     * @return array{carrierId: int, productId: int, addressId: int}
     */
    public function testAvailableCarriersFixtures(): array
    {
        $container = static::createClient()->getContainer();
        $commandBus = $container->get('prestashop.core.command_bus');

        $carrier = $this->createItem('/carriers', [
            'name' => 'Available carrier',
            'delays' => ['en-US' => '3-5 days', 'fr-FR' => '3-5 jours'],
            'grade' => 5,
            'trackingUrl' => 'http://example.com/@',
            'enabled' => true,
            'associatedGroupIds' => [1, 2, 3],
            'additionalHandlingFee' => false,
            'free' => false,
            'shippingMethod' => 2,
            'rangeBehavior' => 0,
            // Zone 1 is "Europe" in the default fixtures.
            'zones' => [1],
            'associatedShopIds' => [1],
        ], ['carrier_write']);
        $carrierId = $carrier['carrierId'];

        $product = $this->createItem('/products', [
            'type' => ProductType::TYPE_STANDARD,
            'names' => ['en-US' => 'Available carrier product', 'fr-FR' => 'Produit disponible'],
        ], ['product_write']);
        $productId = $product['productId'];

        // Carrier reference id equals carrier id right after creation (they only diverge on later edits).
        $commandBus->handle(new SetCarriersCommand($productId, [$carrierId], ShopConstraint::allShops()));

        $country = $this->createItem('/countries', [
            'names' => ['en-US' => 'Available Carrier Country', 'fr-FR' => 'Pays du transporteur'],
            'isoCode' => 'ZY',
            'callPrefix' => 998,
            'defaultCurrencyId' => 0,
            // Same zone as the carrier above, otherwise it is filtered out as ineligible.
            'zoneId' => 1,
            'needZipCode' => false,
            'zipCodeFormat' => null,
            'addressFormat' => "firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone",
            'enabled' => true,
            'containsStates' => false,
            'needIdNumber' => false,
            'displayTaxLabel' => true,
            'shopIds' => [1],
        ], ['country_write']);
        $countryId = $country['countryId'];

        $customerId = $commandBus->handle(new AddCustomerCommand(
            'John',
            'Doe',
            'available-carriers-test@example.com',
            'Password123!',
            3,
            [1, 2, 3],
            1
        ))->getValue();

        $addressId = $commandBus->handle(new AddCustomerAddressCommand(
            $customerId,
            'Home',
            'John',
            'Doe',
            '1 Infinite Loop',
            'Paris',
            $countryId,
            '75001'
        ))->getValue();

        return [
            'carrierId' => $carrierId,
            'productId' => $productId,
            'addressId' => $addressId,
        ];
    }

    /**
     * @depends testAvailableCarriersFixtures
     */
    public function testGetAvailableCarriers(array $fixtures): void
    {
        $query = http_build_query([
            'addressId' => $fixtures['addressId'],
            'productQuantities' => [
                ['productId' => $fixtures['productId'], 'quantity' => 1],
            ],
        ]);

        $availableCarriers = $this->getItem('/carriers/available?' . $query, ['carrier_read']);
        $this->assertArrayHasKey('availableCarriers', $availableCarriers);
        $carrierIds = array_column($availableCarriers['availableCarriers'], 'carrierId');
        $this->assertContains($fixtures['carrierId'], $carrierIds);
    }

    /**
     * @depends testAvailableCarriersFixtures
     */
    public function testGetAvailableCarriersForUnknownAddressIsRejected(array $fixtures): void
    {
        $query = http_build_query([
            'addressId' => 999999,
            'productQuantities' => [
                ['productId' => $fixtures['productId'], 'quantity' => 1],
            ],
        ]);

        $this->getItem('/carriers/available?' . $query, ['carrier_read'], Response::HTTP_NOT_FOUND);
    }

    /**
     * @depends testAvailableCarriersFixtures
     */
    public function testGetAvailableCarriersMissingAddressId(array $fixtures): void
    {
        $query = http_build_query([
            'productQuantities' => [
                ['productId' => $fixtures['productId'], 'quantity' => 1],
            ],
        ]);

        $this->getItem('/carriers/available?' . $query, ['carrier_read'], Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /**
     * @depends testAvailableCarriersFixtures
     */
    public function testGetAvailableCarriersMissingProductQuantities(array $fixtures): void
    {
        $query = http_build_query(['addressId' => $fixtures['addressId']]);

        $this->getItem('/carriers/available?' . $query, ['carrier_read'], Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}
