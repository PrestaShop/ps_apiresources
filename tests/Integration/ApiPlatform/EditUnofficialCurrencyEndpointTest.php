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

class EditUnofficialCurrencyEndpointTest extends ApiTestCase
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
        yield 'edit unofficial currency endpoint' => ['PATCH', '/currencies/unofficials/999999'];
    }

    public function testEditUnofficialCurrency(): void
    {
        $currencyId = $this->seedUnofficialCurrency('EDT');

        $this->requestApi(
            'PATCH',
            '/currencies/unofficials/' . $currencyId,
            ['isoCode' => 'EDU', 'enabled' => false],
            ['currency_write'],
            Response::HTTP_OK
        );

        $iso = (string) \Db::getInstance()->getValue(
            'SELECT `iso_code` FROM `' . _DB_PREFIX_ . 'currency` WHERE `id_currency` = ' . $currencyId
        );
        $this->assertSame('EDU', $iso);
    }

    public function testEditUnknownCurrencyReturnsNotFound(): void
    {
        $this->requestApi(
            'PATCH',
            '/currencies/unofficials/999999',
            ['isoCode' => 'ZZZ'],
            ['currency_write'],
            Response::HTTP_NOT_FOUND
        );
    }

    private function seedUnofficialCurrency(string $iso): int
    {
        \Db::getInstance()->insert('currency', [
            'iso_code' => $iso,
            'numeric_iso_code' => '000',
            'precision' => 2,
            'conversion_rate' => 1.5,
            'deleted' => 0,
            'active' => 1,
            'unofficial' => 1,
            'modified' => 1,
        ]);

        return (int) \Db::getInstance()->Insert_ID();
    }
}
