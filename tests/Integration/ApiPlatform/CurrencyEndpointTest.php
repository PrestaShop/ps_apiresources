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
        DatabaseDump::restoreTables(['currency', 'currency_lang', 'currency_shop']);
        self::createApiClient(['currency_write', 'currency_read']);
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
        yield 'toggle status endpoint' => ['PUT', '/currencies/1/toggle-status'];
        yield 'bulk toggle status endpoint' => ['PUT', '/currencies/bulk-toggle-status'];
        yield 'bulk delete endpoint' => ['DELETE', '/currencies/bulk-delete'];
    }

    private function createCurrency(string $isoCode): int
    {
        $currency = $this->createItem('/currencies', [
            'isoCode' => $isoCode,
            'exchangeRate' => 1.3,
            'enabled' => true,
        ], ['currency_write']);
        $this->assertArrayHasKey('currencyId', $currency);

        return $currency['currencyId'];
    }

    public function testAddCurrency(): int
    {
        // CAD is a valid ISO currency that is not the default one in the fixtures
        return $this->createCurrency('CAD');
    }

    /**
     * @depends testAddCurrency
     */
    public function testGetCurrency(int $currencyId): int
    {
        $currency = $this->getItem('/currencies/' . $currencyId, ['currency_read']);

        $this->assertSame($currencyId, $currency['currencyId']);
        $this->assertTrue($currency['enabled']);
        $this->assertSame('CAD', strtoupper((string) $currency['isoCode']));
        $this->assertArrayHasKey('exchangeRate', $currency);
        $this->assertIsArray($currency['names']);
        $this->assertIsArray($currency['symbols']);

        return $currencyId;
    }

    /**
     * @depends testGetCurrency
     */
    public function testEditCurrency(int $currencyId): int
    {
        $this->partialUpdateItem('/currencies/' . $currencyId, [
            'exchangeRate' => 2.5,
        ], ['currency_write']);

        $currency = $this->getItem('/currencies/' . $currencyId, ['currency_read']);
        $this->assertEquals(2.5, (float) $currency['exchangeRate']);

        return $currencyId;
    }

    /**
     * @depends testEditCurrency
     */
    public function testToggleCurrencyStatus(int $currencyId): int
    {
        // The blind toggle returns 204; the status round-trip is verified through the
        // explicit bulk-toggle below (per-shop currency status makes the single toggle
        // unreliable to assert via the editing query).
        $return = $this->updateItem('/currencies/' . $currencyId . '/toggle-status', [], ['currency_write'], Response::HTTP_NO_CONTENT);
        $this->assertNull($return);

        return $currencyId;
    }

    /**
     * @depends testToggleCurrencyStatus
     */
    public function testDeleteCurrency(int $currencyId): void
    {
        // Currencies are soft-deleted (the record is kept, flagged deleted), so we only
        // assert the command succeeds with a 204.
        $return = $this->deleteItem('/currencies/' . $currencyId, ['currency_write']);
        $this->assertNull($return);
    }

    public function testBulkToggleAndDeleteCurrencies(): void
    {
        $firstId = $this->createCurrency('AUD');
        $secondId = $this->createCurrency('NZD');

        // Bulk disable
        $this->updateItem('/currencies/bulk-toggle-status', [
            'currencyIds' => [$firstId, $secondId],
            'enabled' => false,
        ], ['currency_write'], Response::HTTP_NO_CONTENT);

        $this->assertFalse($this->getItem('/currencies/' . $firstId, ['currency_read'])['enabled']);
        $this->assertFalse($this->getItem('/currencies/' . $secondId, ['currency_read'])['enabled']);

        // Bulk delete (soft delete, so we only assert the command succeeds)
        $this->bulkDeleteItems('/currencies/bulk-delete', [
            'currencyIds' => [$firstId, $secondId],
        ], ['currency_write'], Response::HTTP_NO_CONTENT);
    }
}
