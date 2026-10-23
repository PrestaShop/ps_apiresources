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

class ManufacturerAddressListEndpointTest extends ApiTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::createApiClient(['address_read']);
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'list manufacturer addresses endpoint' => ['GET', '/addresses/manufacturers'];
    }

    public function testListManufacturerAddresses(): void
    {
        // The test shop is installed with a single manufacturer address, the customer ones are not listed
        $paginatedAddresses = $this->listItems('/addresses/manufacturers', ['address_read']);
        $this->assertSame(1, $paginatedAddresses['totalItems']);
        $this->assertSame([
            'addressId' => 3,
            'manufacturerName' => 'Studio Design',
            'firstName' => 'manufacturer',
            'lastName' => 'manufacturer',
            'postcode' => '10154',
            'city' => 'New York',
            'countryName' => 'United States',
        ], $paginatedAddresses['items'][0]);
    }

    public function testFilterManufacturerAddresses(): void
    {
        $listedIds = function (array $filters): array {
            return array_column($this->listItems('/addresses/manufacturers', ['address_read'], $filters)['items'], 'addressId');
        };

        $this->assertSame([3], $listedIds(['manufacturerName' => 'Studio']));
        $this->assertSame([3], $listedIds(['city' => 'York']));
        $this->assertSame([3], $listedIds(['countryId' => (int) \Country::getByIso('US')]));
        $this->assertSame([], $listedIds(['countryId' => (int) \Country::getByIso('FR')]));
        $this->assertSame([], $listedIds(['lastName' => 'unknown']));
    }
}
