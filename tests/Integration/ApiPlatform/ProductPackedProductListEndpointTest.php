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

class ProductPackedProductListEndpointTest extends ApiTestCase
{
    private static int $packId;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::createApiClient(['product_read']);

        // A demo pack product already has packed products.
        self::$packId = (int) \Db::getInstance()->getValue(
            'SELECT `id_product_pack` FROM `' . _DB_PREFIX_ . 'pack` ORDER BY `id_product_pack` ASC'
        );
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'list packed products endpoint' => ['GET', '/products/1/pack-products'];
    }

    public function testListPackedProducts(): void
    {
        $result = $this->getItem('/products/' . self::$packId . '/pack-products', ['product_read']);

        $this->assertNotEmpty($result);
        $this->assertArrayHasKey('productId', $result[0]);
        $this->assertArrayHasKey('quantity', $result[0]);
    }
}
