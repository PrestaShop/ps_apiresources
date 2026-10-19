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

class ShopEndpointTest extends ApiTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::createApiClient(['shop_read']);
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'search endpoint' => [
            'GET',
            '/shops/search?searchTerm=shop',
        ];
    }

    /**
     * The endpoint returns two different row shapes and the distinction is the substance of the
     * contract: a shop row carries the id/color/name of the shop plus the groupId/groupName/
     * groupColor of the group it belongs to, while a shop-group row carries only id/color/name.
     * The two searches below pin one payload of each shape whole, which covers the row types
     * and the field set in one go.
     */
    public function testSearchShopsReturnsAShopRow(): void
    {
        // The shop name is install data ("PrestaShop" locally, "test_shop" on CI), and
        // PS_SHOP_NAME is empty in the test env, so read it from the shop row itself.
        $shopName = (new \Shop(1))->name;

        $this->assertEquals(
            [[
                'id' => 1,
                'color' => '',
                'name' => $shopName,
                'groupId' => 1,
                'groupName' => 'Default',
                'groupColor' => '',
            ]],
            $this->getItem('/shops/search?searchTerm=shop', ['shop_read'])
        );
    }

    public function testSearchShopsReturnsAShopGroupRow(): void
    {
        $this->assertEquals(
            [[
                'id' => 1,
                'color' => '',
                'name' => 'Default',
            ]],
            $this->getItem('/shops/search?searchTerm=Default', ['shop_read'])
        );
    }

    public function testSearchShopsWithNoResults(): void
    {
        $results = $this->getItem('/shops/search?searchTerm=nonexistentshop999', ['shop_read']);

        $this->assertIsArray($results);
        $this->assertEmpty($results);
    }

    /**
     * required: true on the QueryParameter only guards the absent parameter; an empty value
     * walks past it and SearchShops rejects it in its constructor. The exceptionToStatus on the
     * resource maps that to 422, which is what an unmapped 500 used to surface as before.
     */
    public function testSearchShopsWithEmptySearchTermReturnsUnprocessable(): void
    {
        $this->getItem('/shops/search?searchTerm=', ['shop_read'], Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->getItem('/shops/search?searchTerm=%20', ['shop_read'], Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}
