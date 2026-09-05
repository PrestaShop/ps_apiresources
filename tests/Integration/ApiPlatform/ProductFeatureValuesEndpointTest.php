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

class ProductFeatureValuesEndpointTest extends ApiTestCase
{
    private static int $productId;
    private static int $featureId;
    private static int $featureValueId;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::createApiClient(['product_write']);

        self::$productId = (int) \Db::getInstance()->getValue(
            'SELECT `id_product` FROM `' . _DB_PREFIX_ . 'product` ORDER BY `id_product` ASC'
        );
        $row = \Db::getInstance()->getRow(
            'SELECT `id_feature`, `id_feature_value` FROM `' . _DB_PREFIX_ . 'feature_value`
             WHERE `custom` = 0 ORDER BY `id_feature_value` ASC'
        );
        self::$featureId = (int) $row['id_feature'];
        self::$featureValueId = (int) $row['id_feature_value'];
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        DatabaseDump::restoreTables(['feature_product']);
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'set feature values endpoint' => ['PUT', '/products/1/feature-values'];
        yield 'remove all feature values endpoint' => ['DELETE', '/products/1/feature-values'];
    }

    public function testSetProductFeatureValues(): int
    {
        $this->updateItem(
            '/products/' . self::$productId . '/feature-values',
            ['featureValues' => [
                ['feature_id' => self::$featureId, 'feature_value_id' => self::$featureValueId],
            ]],
            ['product_write'],
            Response::HTTP_NO_CONTENT
        );

        $count = (int) \Db::getInstance()->getValue(
            'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'feature_product`
             WHERE `id_product` = ' . self::$productId . '
               AND `id_feature` = ' . self::$featureId . '
               AND `id_feature_value` = ' . self::$featureValueId
        );
        $this->assertSame(1, $count);

        return self::$productId;
    }

    /**
     * @depends testSetProductFeatureValues
     */
    public function testRemoveAllProductFeatureValues(int $productId): void
    {
        $this->deleteItem('/products/' . $productId . '/feature-values', ['product_write']);

        $count = (int) \Db::getInstance()->getValue(
            'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'feature_product` WHERE `id_product` = ' . $productId
        );
        $this->assertSame(0, $count);
    }
}
