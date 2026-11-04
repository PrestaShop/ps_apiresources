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

class ProductCarriersEndpointTest extends ApiTestCase
{
    private static int $productId;
    private static int $carrierReferenceId;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::createApiClient(['product_write']);

        self::$productId = (int) \Db::getInstance()->getValue(
            'SELECT `id_product` FROM `' . _DB_PREFIX_ . 'product` ORDER BY `id_product` ASC'
        );
        self::$carrierReferenceId = (int) \Db::getInstance()->getValue(
            'SELECT DISTINCT `id_reference` FROM `' . _DB_PREFIX_ . 'carrier`
             WHERE `deleted` = 0 AND `id_reference` > 0 ORDER BY `id_reference` ASC'
        );
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        DatabaseDump::restoreTables(['product_carrier']);
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'set carriers endpoint' => ['PUT', '/products/1/carriers'];
    }

    public function testSetProductCarriers(): void
    {
        $this->updateItem(
            '/products/' . self::$productId . '/carriers',
            ['carrierReferenceIds' => [self::$carrierReferenceId]],
            ['product_write'],
            Response::HTTP_NO_CONTENT
        );

        $references = \Db::getInstance()->executeS(
            'SELECT `id_carrier_reference` FROM `' . _DB_PREFIX_ . 'product_carrier`
             WHERE `id_product` = ' . self::$productId
        );
        $storedReferences = array_map('intval', array_column($references, 'id_carrier_reference'));
        $this->assertContains(self::$carrierReferenceId, $storedReferences);
    }
}
