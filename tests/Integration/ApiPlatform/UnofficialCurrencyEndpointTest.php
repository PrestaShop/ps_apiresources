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

class UnofficialCurrencyEndpointTest extends ApiTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        DatabaseDump::restoreTables(['currency', 'currency_shop', 'currency_lang']);
        self::createApiClient(['currency_write']);
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        DatabaseDump::restoreTables(['currency', 'currency_shop', 'currency_lang']);
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'create unofficial currency endpoint' => ['POST', '/currencies/unofficials'];
    }

    public function testCreateUnofficialCurrency(): void
    {
        $body = [
            'isoCode' => 'ABC',
            'exchangeRate' => 1.23,
            'enabled' => true,
        ];

        $result = $this->requestApi(
            'POST',
            '/currencies/unofficials',
            $body,
            ['currency_write'],
            Response::HTTP_CREATED
        );

        $this->assertIsArray($result);
        $this->assertArrayHasKey('currencyId', $result);
        $this->assertIsInt($result['currencyId']);
        $this->assertGreaterThan(0, $result['currencyId']);

        $seededIsoCode = (string) \Db::getInstance()->getValue(
            'SELECT `iso_code` FROM `' . _DB_PREFIX_ . 'currency` WHERE `id_currency` = ' . $result['currencyId']
        );
        $this->assertSame('ABC', $seededIsoCode);
    }
}
