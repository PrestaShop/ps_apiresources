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

class ExchangeRateEndpointTest extends ApiTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::createApiClient(['currency_read']);
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'get currency exchange rate endpoint' => ['GET', '/currencies/exchange-rates?isoCode=USD'];
    }

    public function testGetCurrencyExchangeRate(): void
    {
        // Use an active non-default currency; the default one has rate 1.
        $isoCode = (string) \Db::getInstance()->getValue(
            'SELECT `iso_code` FROM `' . _DB_PREFIX_ . 'currency`
             WHERE `deleted` = 0 AND `active` = 1 AND `iso_code` <> \'\'
             ORDER BY `id_currency` ASC'
        );

        $result = $this->getItem('/currencies/exchange-rates?isoCode=' . urlencode($isoCode), ['currency_read']);

        $this->assertArrayHasKey('exchangeRate', $result);
        $this->assertNotEmpty($result['exchangeRate']);
    }

    public function testGetUnknownExchangeRateReturnsNotFound(): void
    {
        $this->requestApi(
            'GET',
            '/currencies/exchange-rates?isoCode=ZZZ',
            null,
            ['currency_read'],
            Response::HTTP_NOT_FOUND
        );
    }
}
