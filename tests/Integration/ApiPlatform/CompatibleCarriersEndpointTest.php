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

class CompatibleCarriersEndpointTest extends ApiTestCase
{
    public static function setUpBeforeClass(): void
    {
        if (self::isVersionUnder('9.2.0')) {
            static::markTestSkipped('The compatible carriers endpoint relies on the scalar constructor of GetAvailableCarriers, which only exists since PrestaShop 9.2.0');

            return;
        }

        parent::setUpBeforeClass();
        LanguageResetter::resetLanguages();
        self::addLanguageByLocale('fr-FR');
        self::createApiClient(['carrier_read', 'carrier_write', 'product_write', 'country_write', 'customer_write', 'address_write']);
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
        // Data providers are resolved when PHPUnit builds the test suite, before setUpBeforeClass
        // gets a chance to skip the class, and an empty provider is reported as an error. So the
        // endpoint is yielded unconditionally; on cores < 9.2.0 the whole class is skipped anyway
        // and this data set is never executed.
        yield 'search compatible carriers endpoint' => ['GET', '/carriers/search-compatible-carriers'];
    }

    /**
     * @return array{carrierId: int, productId: int, addressId: int}
     */
    public function testCompatibleCarriersFixtures(): array
    {
        $carrier = $this->createItem('/carriers', [
            'name' => 'Compatible carrier',
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
            'names' => ['en-US' => 'Compatible carrier product', 'fr-FR' => 'Produit disponible'],
        ], ['product_write']);
        $productId = $product['productId'];

        // Carrier reference id equals carrier id right after creation (they only diverge on later edits).
        $this->updateItem('/products/' . $productId . '/carriers', [
            'carrierReferenceIds' => [$carrierId],
        ], ['product_write']);

        $country = $this->createItem('/countries', [
            'names' => ['en-US' => 'Compatible Carrier Country', 'fr-FR' => 'Pays du transporteur'],
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

        $customer = $this->createItem('/customers', [
            'firstName' => 'John',
            'lastName' => 'Doe',
            'email' => 'compatible-carriers-test@example.com',
            'password' => 'Password123!',
            'defaultGroupId' => 3,
            'groupIds' => [1, 2, 3],
            'genderId' => 1,
            'enabled' => true,
        ], ['customer_write']);

        $address = $this->createItem('/addresses/customers', [
            'customerId' => $customer['customerId'],
            'addressAlias' => 'Home',
            'firstName' => 'John',
            'lastName' => 'Doe',
            'address' => '1 Infinite Loop',
            'city' => 'Paris',
            'countryId' => $countryId,
            'postCode' => '75001',
        ], ['address_write']);
        $addressId = $address['addressId'];

        return [
            'carrierId' => $carrierId,
            'productId' => $productId,
            'addressId' => $addressId,
        ];
    }

    /**
     * @depends testCompatibleCarriersFixtures
     */
    public function testGetCompatibleCarriers(array $fixtures): void
    {
        $query = http_build_query([
            'addressId' => $fixtures['addressId'],
            'productQuantities' => [
                ['productId' => $fixtures['productId'], 'quantity' => 1],
            ],
        ]);

        $compatibleCarriers = $this->getItem('/carriers/search-compatible-carriers?' . $query, ['carrier_read']);
        $this->assertArrayHasKey('compatibleCarriers', $compatibleCarriers);
        $this->assertContains(
            ['carrierId' => $fixtures['carrierId'], 'name' => 'Compatible carrier'],
            $compatibleCarriers['compatibleCarriers']
        );
    }

    /**
     * @depends testCompatibleCarriersFixtures
     */
    public function testGetCompatibleCarriersForUnknownAddressIsRejected(array $fixtures): void
    {
        $query = http_build_query([
            'addressId' => 999999,
            'productQuantities' => [
                ['productId' => $fixtures['productId'], 'quantity' => 1],
            ],
        ]);

        $this->getItem('/carriers/search-compatible-carriers?' . $query, ['carrier_read'], Response::HTTP_NOT_FOUND);
    }

    /**
     * The searched products are loaded by the core before the carriers are filtered, so an unknown
     * product identifier gets the same 404 as an unknown address.
     *
     * @depends testCompatibleCarriersFixtures
     */
    public function testGetCompatibleCarriersForUnknownProductIsRejected(array $fixtures): void
    {
        $query = http_build_query([
            'addressId' => $fixtures['addressId'],
            'productQuantities' => [
                ['productId' => 999999, 'quantity' => 1],
            ],
        ]);

        $this->getItem('/carriers/search-compatible-carriers?' . $query, ['carrier_read'], Response::HTTP_NOT_FOUND);
    }

    /**
     * @depends testCompatibleCarriersFixtures
     */
    public function testGetCompatibleCarriersMissingAddressId(array $fixtures): void
    {
        $query = http_build_query([
            'productQuantities' => [
                ['productId' => $fixtures['productId'], 'quantity' => 1],
            ],
        ]);

        $this->getItem('/carriers/search-compatible-carriers?' . $query, ['carrier_read'], Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /**
     * @depends testCompatibleCarriersFixtures
     */
    public function testGetCompatibleCarriersMissingProductQuantities(array $fixtures): void
    {
        $query = http_build_query(['addressId' => $fixtures['addressId']]);

        $this->getItem('/carriers/search-compatible-carriers?' . $query, ['carrier_read'], Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /**
     * An entry without a quantity used to be accepted with a quantity of 0, returning a plausible
     * but wrong carrier list instead of an error.
     *
     * @depends testCompatibleCarriersFixtures
     */
    public function testGetCompatibleCarriersWithoutQuantityIsRejected(array $fixtures): void
    {
        $query = http_build_query([
            'addressId' => $fixtures['addressId'],
            'productQuantities' => [
                ['productId' => $fixtures['productId']],
            ],
        ]);

        $this->getItem('/carriers/search-compatible-carriers?' . $query, ['carrier_read'], Response::HTTP_BAD_REQUEST);
    }

    /**
     * @depends testCompatibleCarriersFixtures
     */
    public function testGetCompatibleCarriersWithNonPositiveQuantityIsRejected(array $fixtures): void
    {
        foreach ([0, -2] as $quantity) {
            $query = http_build_query([
                'addressId' => $fixtures['addressId'],
                'productQuantities' => [
                    ['productId' => $fixtures['productId'], 'quantity' => $quantity],
                ],
            ]);

            $this->getItem('/carriers/search-compatible-carriers?' . $query, ['carrier_read'], Response::HTTP_BAD_REQUEST);
        }
    }
}
