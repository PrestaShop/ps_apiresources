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

class CarrierActionsEndpointTest extends ApiTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::createApiClient(['carrier_write']);
    }

    public static function tearDownAfterClass(): void
    {
        \Db::getInstance()->delete('carrier', "`name` = 'Test carrier gap'");

        parent::tearDownAfterClass();
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'bulk delete carriers endpoint' => ['DELETE', '/carriers/bulk-delete'];
    }

    public function testBulkDeleteCarriers(): void
    {
        $firstCarrierId = $this->createCarrier();
        $secondCarrierId = $this->createCarrier();

        $this->bulkDeleteItems(
            '/carriers/bulk-delete',
            ['carrierIds' => [$firstCarrierId, $secondCarrierId]],
            ['carrier_write'],
            Response::HTTP_NO_CONTENT
        );

        foreach ([$firstCarrierId, $secondCarrierId] as $carrierId) {
            $this->assertEmpty(
                \Db::getInstance()->getValue(
                    'SELECT `id_carrier` FROM `' . _DB_PREFIX_ . 'carrier` WHERE `id_carrier` = ' . $carrierId . ' AND `deleted` = 0'
                )
            );
        }
    }

    private function createCarrier(): int
    {
        $carrier = new \Carrier(null, (int) \Configuration::get('PS_LANG_DEFAULT'));
        $carrier->name = 'Test carrier gap';
        $carrier->active = true;
        $carrier->delay = '28 days later';
        $carrier->is_free = true;
        $carrier->shipping_handling = false;
        $carrier->add();

        return (int) $carrier->id;
    }
}
