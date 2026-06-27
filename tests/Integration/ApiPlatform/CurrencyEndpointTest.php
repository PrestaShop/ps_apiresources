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

class CurrencyEndpointTest extends ApiTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::createApiClient(['currency_read', 'currency_write']);
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        DatabaseDump::restoreTables(['currency', 'currency_lang', 'currency_shop']);
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'create endpoint' => ['POST', '/currencies'];
        yield 'get endpoint' => ['GET', '/currencies/1'];
        yield 'update endpoint' => ['PATCH', '/currencies/1'];
        yield 'delete endpoint' => ['DELETE', '/currencies/1'];
    }

    public function testAddCurrency(): int
    {
        // Official currency: names/symbols are resolved from CLDR, only the ISO code and
        // exchange rate are required.
        $currency = $this->createItem('/currencies', [
            'isoCode' => 'CHF',
            'exchangeRate' => 1.08,
            'enabled' => true,
            'shopIds' => [1],
        ], ['currency_write']);

        $this->assertArrayHasKey('currencyId', $currency);

        return $currency['currencyId'];
    }

    /**
     * @depends testAddCurrency
     */
    public function testGetCurrency(int $currencyId): int
    {
        $currency = $this->getItem('/currencies/' . $currencyId, ['currency_read']);

        $this->assertEquals($currencyId, $currency['currencyId']);
        $this->assertEquals('CHF', $currency['isoCode']);
        $this->assertEquals(1.08, (float) $currency['exchangeRate']);
        $this->assertFalse($currency['unofficial']);
        $this->assertNotEmpty($currency['names']);
        $this->assertNotEmpty($currency['symbols']);
        $this->assertEquals([1], $currency['shopIds']);

        return $currencyId;
    }

    /**
     * @depends testGetCurrency
     */
    public function testUpdateCurrency(int $currencyId): int
    {
        $updated = $this->partialUpdateItem('/currencies/' . $currencyId, [
            'exchangeRate' => 1.25,
            'enabled' => false,
        ], ['currency_write']);

        $this->assertEquals(1.25, (float) $updated['exchangeRate']);
        $this->assertFalse($updated['enabled']);

        $fetched = $this->getItem('/currencies/' . $currencyId, ['currency_read']);
        $this->assertEquals(1.25, (float) $fetched['exchangeRate']);
        $this->assertFalse($fetched['enabled']);

        return $currencyId;
    }

    /**
     * @depends testUpdateCurrency
     */
    public function testDeleteCurrency(int $currencyId): void
    {
        $return = $this->deleteItem('/currencies/' . $currencyId, ['currency_write']);
        $this->assertNull($return);

        $this->getItem('/currencies/999999', ['currency_read'], Response::HTTP_NOT_FOUND);
    }

    public function testInvalidCurrency(): void
    {
        $validationErrorsResponse = $this->createItem(
            '/currencies',
            [
                'isoCode' => '',
                'exchangeRate' => 1.0,
                'enabled' => true,
            ],
            ['currency_write'],
            Response::HTTP_UNPROCESSABLE_ENTITY
        );
        $this->assertIsArray($validationErrorsResponse);
        $this->assertValidationErrors([
            [
                'propertyPath' => 'isoCode',
                'message' => 'This value should not be blank.',
            ],
        ], $validationErrorsResponse);
    }
}
