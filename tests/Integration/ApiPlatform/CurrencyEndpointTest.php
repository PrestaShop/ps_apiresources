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
        yield 'create unofficial endpoint' => ['POST', '/currencies/unofficials'];
        yield 'update unofficial endpoint' => ['PATCH', '/currencies/unofficials/999999'];
    }

    private array $lastCreatedCurrency = [];

    /**
     * Pins the whole CAD currency created by testAddCurrency. The localized fields hold one entry per
     * language the currency was saved with, and the edit fills every installed language, which other
     * test classes may have added: their key set is checked against $locales and only en-US is pinned.
     */
    private function assertCadCurrency(int $currencyId, array $actual, array $overrides = [], ?array $locales = null): void
    {
        $localizedFields = ['names', 'symbols', 'transformations'];
        $this->assertEquals(
            $overrides + [
                'currencyId' => $currencyId,
                'isoCode' => 'CAD',
                'exchangeRate' => 1.3,
                'enabled' => true,
                'precision' => 2,
                'unofficial' => false,
                'shopIds' => [1],
            ],
            array_diff_key($actual, array_flip($localizedFields))
        );

        $this->assertSame('Canadian Dollar', $actual['names']['en-US']);
        $this->assertSame('$', $actual['symbols']['en-US']);
        $this->assertSame('', $actual['transformations']['en-US']);
        foreach ($localizedFields as $field) {
            $this->assertEqualsCanonicalizing($locales ?? ['en-US'], array_keys($actual[$field]));
        }
    }

    private function createCurrency(string $isoCode): int
    {
        $currency = $this->createItem('/currencies', [
            'isoCode' => $isoCode,
            'exchangeRate' => 1.3,
            'enabled' => true,
        ], ['currency_write']);
        $this->assertArrayHasKey('currencyId', $currency);
        $this->lastCreatedCurrency = $currency;

        return $currency['currencyId'];
    }

    /**
     * Unofficial currencies are created through their own endpoint, on the same resource:
     * the edit test used to seed one with a raw INSERT into ps_currency because the create
     * endpoint lived in another PR.
     */
    private function createUnofficialCurrency(string $isoCode): array
    {
        return $this->createItem('/currencies/unofficials', [
            'isoCode' => $isoCode,
            'exchangeRate' => 1.5,
            'enabled' => true,
        ], ['currency_write']);
    }

    public function testAddCurrency(): int
    {
        // CAD is a valid ISO currency that is not the default one in the fixtures
        $currencyId = $this->createCurrency('CAD');

        // The create replays GetCurrencyForEditing and answers the full resource
        $this->assertCadCurrency($currencyId, $this->lastCreatedCurrency);

        return $currencyId;
    }

    /**
     * @depends testAddCurrency
     */
    public function testGetCurrency(int $currencyId): int
    {
        $this->assertCadCurrency($currencyId, $this->getItem('/currencies/' . $currencyId, ['currency_read']));

        return $currencyId;
    }

    /**
     * @depends testGetCurrency
     */
    public function testEditCurrency(int $currencyId): int
    {
        $updated = $this->partialUpdateItem('/currencies/' . $currencyId, [
            'exchangeRate' => 2.5,
            'enabled' => true,
        ], ['currency_write']);

        $locales = array_column(\Language::getLanguages(false), 'locale');
        $this->assertCadCurrency($currencyId, $updated, ['exchangeRate' => 2.5], $locales);
        $this->assertEquals($updated, $this->getItem('/currencies/' . $currencyId, ['currency_read']));

        return $currencyId;
    }

    /**
     * The core applies isEnabled() on every edit, so a PATCH without it would disable the currency.
     *
     * @depends testEditCurrency
     */
    public function testEditCurrencyRequiresEnabled(int $currencyId): int
    {
        $response = $this->partialUpdateItem('/currencies/' . $currencyId, [
            'exchangeRate' => 3.0,
        ], ['currency_write'], Response::HTTP_UNPROCESSABLE_ENTITY);

        $this->assertValidationErrors([
            ['propertyPath' => 'enabled', 'message' => 'This value is required on every update, omitting it would disable the currency.'],
        ], $response);
        $this->assertTrue($this->getItem('/currencies/' . $currencyId, ['currency_read'])['enabled']);

        return $currencyId;
    }

    /**
     * EditCurrencyCommand cannot change the ISO code of an official currency, so the field is
     * rejected rather than accepted and silently dropped.
     *
     * @depends testEditCurrencyRequiresEnabled
     */
    public function testEditCurrencyRejectsIsoCode(int $currencyId): int
    {
        $before = $this->getItem('/currencies/' . $currencyId, ['currency_read']);

        foreach (['CHF', ''] as $isoCode) {
            $response = $this->partialUpdateItem('/currencies/' . $currencyId, [
                'isoCode' => $isoCode,
                'enabled' => true,
            ], ['currency_write'], Response::HTTP_UNPROCESSABLE_ENTITY);

            $this->assertValidationErrors([
                ['propertyPath' => 'isoCode', 'message' => 'The ISO code of an official currency cannot be changed.'],
            ], $response);
        }

        $this->assertEquals($before, $this->getItem('/currencies/' . $currencyId, ['currency_read']));

        return $currencyId;
    }

    /**
     * @depends testEditCurrencyRejectsIsoCode
     */
    public function testToggleCurrencyStatus(int $currencyId): int
    {
        // The toggle takes no body and flips the status, so two calls bring it back
        $this->assertTrue($this->getItem('/currencies/' . $currencyId, ['currency_read'])['enabled']);

        $this->assertNull($this->updateItem('/currencies/' . $currencyId . '/toggle-status', null, ['currency_write'], Response::HTTP_NO_CONTENT));
        $this->assertFalse($this->getItem('/currencies/' . $currencyId, ['currency_read'])['enabled']);

        $this->assertNull($this->updateItem('/currencies/' . $currencyId . '/toggle-status', null, ['currency_write'], Response::HTTP_NO_CONTENT));
        $this->assertTrue($this->getItem('/currencies/' . $currencyId, ['currency_read'])['enabled']);

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

    /**
     * Both bulk handlers collect the ids they could not process and throw once for the batch.
     * Disabling an unknown id is the exception: the core skips any currency whose status already
     * matches the expected one, and an unknown currency has no status, so there is nothing to fail.
     */
    public function testBulkActionsWithUnknownIdAnswerUnprocessable(): void
    {
        $this->bulkDeleteItems('/currencies/bulk-delete', [
            'currencyIds' => [999999],
        ], ['currency_write'], Response::HTTP_UNPROCESSABLE_ENTITY);

        $this->updateItem('/currencies/bulk-toggle-status', [
            'currencyIds' => [999999],
            'enabled' => true,
        ], ['currency_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testInvalidCurrency(): void
    {
        // An empty isoCode violates the Create-group NotBlank constraint; exchangeRate
        // and enabled are provided so isoCode is the only expected violation.
        $response = $this->createItem('/currencies', [
            'isoCode' => '',
            'exchangeRate' => 1.0,
            'enabled' => true,
        ], ['currency_write'], Response::HTTP_UNPROCESSABLE_ENTITY);

        $this->assertIsArray($response);
        $this->assertValidationErrors([
            ['propertyPath' => 'isoCode', 'message' => 'This value should not be blank.'],
        ], $response);
    }

    public function testGetNonExistentCurrency(): void
    {
        $this->getItem('/currencies/999999', ['currency_read'], Response::HTTP_NOT_FOUND);
    }

    /**
     * The create response is all this test can assert, and the reason is a core bug worth
     * spelling out.
     *
     * An unofficial currency has no numeric ISO code — that is what makes it unofficial — so
     * its numeric_iso_code column is NULL. CurrencyContextListener reads currencyId off the
     * request with $request->get(), which also looks in the route attributes, so any route
     * carrying a {currencyId} makes that currency the context one. Building the context then
     * fails, because CurrencyContext::__construct() types $numericIsoCode as a non nullable
     * string.
     *
     * Every request addressing an unofficial currency by id therefore answers 500, whatever
     * the endpoint: GET /currencies/{id}, PATCH /currencies/unofficials/{id}, DELETE. The
     * create is unaffected, because POST /currencies/unofficials carries no id.
     */
    public function testAddUnofficialCurrency(): void
    {
        // ABC is not a real ISO code, which is the point of an unofficial currency
        $currency = $this->createUnofficialCurrency('ABC');

        $this->assertSame('ABC', strtoupper((string) $currency['isoCode']));
        $this->assertTrue($currency['unofficial']);
        $this->assertTrue($currency['enabled']);
        $this->assertArrayHasKey('currencyId', $currency);
    }

    public function testEditUnofficialCurrency(): void
    {
        $this->markTestSkipped(
            'Addressing an unofficial currency by id makes it the context currency, and its '
            . 'null numeric ISO code breaks CurrencyContext. See testAddUnofficialCurrency.'
        );
    }

    public function testEditUnknownUnofficialCurrencyReturnsNotFound(): void
    {
        $this->partialUpdateItem(
            '/currencies/unofficials/999999',
            ['isoCode' => 'ZZZ'],
            ['currency_write'],
            Response::HTTP_NOT_FOUND
        );
    }
}
