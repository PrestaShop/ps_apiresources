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

use Symfony\Component\HttpFoundation\Response;
use Tests\Resources\DatabaseDump;

class StoreListEndpointTest extends ApiTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::resetTables();
        self::createApiClient(['store_read', 'store_write']);
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        self::resetTables();
    }

    protected static function resetTables(): void
    {
        DatabaseDump::restoreTables(['store', 'store_lang', 'store_shop']);
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'list stores endpoint' => ['GET', '/stores'];
    }

    public function testListStores(): void
    {
        // The test shop is installed with five fixture stores in Miami
        $paginatedStores = $this->listItems('/stores?orderBy=storeId&sortOrder=asc&limit=2', ['store_read']);
        $this->assertSame(5, $paginatedStores['totalItems']);
        $this->assertSame('storeId', $paginatedStores['orderBy']);
        $this->assertCount(2, $paginatedStores['items']);
        $this->assertSame([
            'storeId' => 1,
            'name' => 'Dade County',
            'address' => '3030 SW 8th St Miami',
            'city' => 'Miami',
            'postcode' => '33135',
            'stateName' => 'Florida',
            'countryName' => 'United States',
            'phone' => '',
            'fax' => '',
            'enabled' => true,
        ], $paginatedStores['items'][0]);
        $this->assertSame(2, $paginatedStores['items'][1]['storeId']);
    }

    public function testFilterStores(): void
    {
        $this->updateItem('/stores/2/toggle-status', null, ['store_write'], Response::HTTP_NO_CONTENT);

        $listedIds = function (array $filters): array {
            $paginatedStores = $this->listItems('/stores?orderBy=storeId&sortOrder=asc', ['store_read'], $filters);

            return array_column($paginatedStores['items'], 'storeId');
        };

        $this->assertSame([2], $listedIds(['enabled' => false]));
        $this->assertSame([1, 3, 4, 5], $listedIds(['enabled' => true]));
        $this->assertSame([4], $listedIds(['name' => 'Coconut']));
        $this->assertSame([3], $listedIds(['postcode' => '33026']));
        $this->assertSame([1, 2, 3, 4, 5], $listedIds(['stateName' => 'Florida']));
        $this->assertSame([], $listedIds(['countryName' => 'France']));
    }
}
