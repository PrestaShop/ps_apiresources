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

class CurrencyExchangeRateEndpointTest extends ApiTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::createApiClient(['currency_read', 'currency_write']);
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'get exchange rate endpoint' => ['GET', '/currencies/exchange-rates?isoCode=USD'];
        yield 'refresh exchange rates endpoint' => ['PUT', '/currencies/exchange-rates'];
    }

    public function testGetExchangeRate(): void
    {
        $result = $this->getItem('/currencies/exchange-rates?isoCode=USD', ['currency_read']);

        // The response carries the rate and nothing else
        $this->assertEquals(['isoCode', 'exchangeRate'], array_keys($result));
        $this->assertSame('USD', $result['isoCode']);
        // Post-normalization, DecimalNumber-typed props serialize as string.
        $this->assertTrue(is_string($result['exchangeRate']) || is_numeric($result['exchangeRate']));
    }

    /*
     * PUT /currencies/exchange-rates is declared in getProtectedEndpoints() but has no
     * behavioural test: RefreshExchangeRatesCommand calls the remote PrestaShop currency
     * service, so a live assertion would depend on network access from the CI runner and
     * would fail with CannotRefreshExchangeRatesException whenever it is unavailable. The
     * scope protection and the routing are covered, the refresh itself is not.
     *
     * The GET above already depends on that same feed being reachable, which is why no
     * "unknown iso code returns 404" case is asserted either: it would only be testing the
     * runner's network.
     */
}
