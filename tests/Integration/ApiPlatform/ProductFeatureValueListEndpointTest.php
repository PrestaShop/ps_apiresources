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

class ProductFeatureValueListEndpointTest extends ApiTestCase
{
    private static int $productId;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::createApiClient(['product_read']);

        // Demo products already have feature values.
        self::$productId = (int) \Db::getInstance()->getValue(
            'SELECT `id_product` FROM `' . _DB_PREFIX_ . 'feature_product` ORDER BY `id_product` ASC'
        );
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'list feature values endpoint' => ['GET', '/products/1/feature-values'];
    }

    public function testListProductFeatureValues(): void
    {
        $result = $this->getItem('/products/' . self::$productId . '/feature-values', ['product_read']);

        $this->assertNotEmpty($result);
        $this->assertArrayHasKey('featureId', $result[0]);
        $this->assertArrayHasKey('featureValueId', $result[0]);
    }
}
