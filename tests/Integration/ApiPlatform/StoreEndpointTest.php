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

use Store;
use Symfony\Component\HttpFoundation\Response;
use Tests\Resources\DatabaseDump;

class StoreEndpointTest extends ApiTestCase
{
    public static int $countryIdFR;

    public static \Store $store1;

    public static \Store $store2;

    public static \Store $store3;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::createApiClient(['store_read', 'store_write']);

        self::$countryIdFR = \Db::getInstance()->getValue('SELECT id_country FROM `' . _DB_PREFIX_ . 'country` WHERE iso_code="FR"');

        // @todo : Replace it when the endpoint POST /store will be available.
        self::$store1 = new \Store();
        self::$store1->name = [1 => 'Store 1'];
        self::$store1->address1 = [1 => 'Store Address 1'];
        self::$store1->address2 = [1 => 'Store Address 2'];
        self::$store1->postcode = '50320';
        self::$store1->city = 'La Haye-Pesnel';
        self::$store1->latitude = '48.79652506';
        self::$store1->longitude = '-1.39708137';
        self::$store1->phone = '0233000000';
        self::$store1->email = 'store1@domain.tld';
        self::$store1->active = true;
        self::$store1->id_country = self::$countryIdFR;
        self::$store1->save();

        self::$store2 = new \Store();
        self::$store2->name = [1 => 'Store 2'];
        self::$store2->address1 = [1 => 'Store Address 3'];
        self::$store2->address2 = [1 => 'Store Address 4'];
        self::$store2->postcode = '62720';
        self::$store2->city = 'Rinxent';
        self::$store2->latitude = '50.80461760';
        self::$store2->longitude = '1.73905362';
        self::$store2->phone = '0321000000';
        self::$store2->email = 'store2@domain.tld';
        self::$store2->active = true;
        self::$store2->id_country = self::$countryIdFR;
        self::$store2->save();

        self::$store3 = new \Store();
        self::$store3->name = [1 => 'Store 3'];
        self::$store3->address1 = [1 => 'Store Address 5'];
        self::$store3->address2 = [1 => 'Store Address 6'];
        self::$store3->postcode = '35340';
        self::$store3->city = 'Liffre';
        self::$store3->latitude = '48.21403115';
        self::$store3->longitude = '-1.50542581';
        self::$store3->phone = '0321000000';
        self::$store3->email = 'store3@domain.tld';
        self::$store3->active = true;
        self::$store3->id_country = self::$countryIdFR;
        self::$store3->save();
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        DatabaseDump::restoreTables(['store', 'store_lang', 'store_shop']);
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'get endpoint' => [
            'GET',
            '/store/1',
        ];

        yield 'toggle status endpoint' => [
            'PUT',
            '/store/1/toggle-status',
        ];

        yield 'delete endpoint' => [
            'DELETE',
            '/store/1',
        ];

        yield 'bulk set status endpoint' => [
            'PUT',
            '/stores/set-status',
        ];

        yield 'bulk delete endpoint' => [
            'PUT',
            '/stores/delete',
        ];
    }

    public function testGetStore(): int
    {
        $storeId = (int) self::$store1->id;

        $store = $this->getItem('/store/' . $storeId, ['store_read']);
        $this->assertEquals(
            [
                'storeId' => $storeId,
                'enabled' => true,
            ],
            $store
        );

        return $storeId;
    }

    /**
     * @depends testGetStore
     */
    public function testToggleStatusStore(int $storeId): int
    {
        $this->updateItem('/store/' . $storeId . '/toggle-status', [], ['store_write'], Response::HTTP_NO_CONTENT);
        $updatedStore = $this->getItem('/store/' . $storeId, ['store_read']);
        $this->assertEquals(
            [
                'storeId' => $storeId,
                'enabled' => false,
            ],
            $updatedStore
        );

        return $storeId;
    }

    /**
     * @depends testToggleStatusStore
     */
    public function testDeleteStore(int $storeId): void
    {
        $return = $this->deleteItem('/store/' . $storeId, ['store_write']);
        // This endpoint return empty response and 204 HTTP code
        $this->assertNull($return);

        // Getting the item should result in a 404 now
        $this->getItem('/store/' . $storeId, ['store_read'], Response::HTTP_NOT_FOUND);
    }

    /**
     * @depends testDeleteStore
     *
     * @return array<int>
     */
    public function testBulkStatusStore(): array
    {
        $bulkStoresId = [
            self::$store2->id,
            self::$store3->id,
        ];

        $this->updateItem('/stores/set-status', [
            'storeIds' => $bulkStoresId,
            'enabled' => false,
        ], ['store_write'], Response::HTTP_NO_CONTENT);

        foreach ($bulkStoresId as $storeId) {
            $store = $this->getItem('/store/' . $storeId, ['store_read']);
            $this->assertEquals(false, $store['enabled']);
        }

        return $bulkStoresId;
    }

    /**
     * @depends testBulkStatusStore
     *
     * @param array<int> $bulkStoresId
     */
    public function testBulkDeleteStore(array $bulkStoresId): void
    {
        $this->updateItem('/stores/delete', [
            'storeIds' => $bulkStoresId,
        ], ['store_write'], Response::HTTP_NO_CONTENT);

        // Assert the provided stores have been removed
        foreach ($bulkStoresId as $storeId) {
            $this->getItem('/stores/' . $storeId, ['store_read'], Response::HTTP_NOT_FOUND);
        }
    }
}
