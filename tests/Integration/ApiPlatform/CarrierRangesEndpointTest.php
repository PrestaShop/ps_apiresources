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

class CarrierRangesEndpointTest extends ApiTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::createApiClient(['carrier_write']);
    }

    public static function tearDownAfterClass(): void
    {
        \Db::getInstance()->delete('carrier', "`name` = 'Test carrier ranges'");

        parent::tearDownAfterClass();
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'set carrier ranges endpoint' => ['PUT', '/carriers/1/ranges'];
    }

    public function testSetCarrierRanges(): void
    {
        $carrier = new \Carrier(null, (int) \Configuration::get('PS_LANG_DEFAULT'));
        $carrier->name = 'Test carrier ranges';
        $carrier->active = true;
        $carrier->delay = '48h';
        $carrier->is_free = false;
        $carrier->shipping_handling = false;
        $carrier->shipping_method = \Carrier::SHIPPING_METHOD_PRICE;
        $carrier->range_behavior = false;
        $carrier->add();
        $carrierId = (int) $carrier->id;

        $zoneId = (int) \Db::getInstance()->getValue(
            'SELECT `id_zone` FROM `' . _DB_PREFIX_ . 'zone` WHERE `active` = 1 ORDER BY `id_zone` ASC'
        );

        $this->updateItem(
            '/carriers/' . $carrierId . '/ranges',
            [
                'ranges' => [
                    ['id_zone' => $zoneId, 'range_from' => 0.0, 'range_to' => 100.0, 'range_price' => '10.00'],
                    ['id_zone' => $zoneId, 'range_from' => 100.0, 'range_to' => 500.0, 'range_price' => '20.00'],
                ],
            ],
            ['carrier_write'],
            Response::HTTP_NO_CONTENT
        );

        // A new version of the carrier may have been created; assert the price range table has entries
        // that share the same reference (the seeded carrier keeps the same id_reference across versions).
        $reference = (int) $carrier->id_reference;
        $rangeCount = (int) \Db::getInstance()->getValue(
            'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'range_price` rp
             INNER JOIN `' . _DB_PREFIX_ . 'carrier` c ON c.`id_carrier` = rp.`id_carrier`
             WHERE c.`id_reference` = ' . $reference
        );
        $this->assertGreaterThanOrEqual(2, $rangeCount);
    }
}
