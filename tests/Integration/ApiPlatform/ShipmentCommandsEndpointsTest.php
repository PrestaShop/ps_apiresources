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

class ShipmentCommandsEndpointsTest extends ApiTestCase
{
    /**
     * SwitchShipmentCarrierCommand exists since 9.1.0, so that is where the shipment_write
     * scope starts existing at all.
     */
    private const MIN_VERSION = '9.1.0';

    /**
     * CreateShipment, FulfillShipmentCommand and AddProductToShipment only landed in 9.2.0.
     */
    private const COMMANDS_MIN_VERSION = '9.2.0';

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        // Below 9.1.0 no Shipment operation survives ApiResourceScopesExtractor, so the
        // shipment_write scope does not exist and even creating the API client fails.
        if (self::isVersionUnder(self::MIN_VERSION)) {
            static::markTestSkipped(sprintf('The Shipment domain requires PrestaShop >= %s.', self::MIN_VERSION));
        }

        self::createApiClient(['shipment_write']);
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'switch carrier endpoint' => ['PUT', '/shipments/1/carrier-assignments'];

        // The three other commands only exist from 9.2.0 on, so their operations are filtered
        // out of the routing below that version and the endpoints answer 404, not 401
        if (self::isVersionAtLeast(self::COMMANDS_MIN_VERSION)) {
            yield 'create shipment endpoint' => ['POST', '/shipments'];
            yield 'fulfill shipment endpoint' => ['PUT', '/shipments/1/fulfillments'];
            yield 'add product to shipment endpoint' => ['POST', '/shipments/1/product-additions'];
        }
    }
}
