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
use Tests\Resources\Resetter\LanguageResetter;

class CountryEndpointTest extends ApiTestCase
{
    // France — present in all PS 9.x default fixtures
    private const FRANCE_ID = 8;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        LanguageResetter::resetLanguages();
        self::addLanguageByLocale('fr-FR');
        self::createApiClient(['country_read', 'country_write']);
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        LanguageResetter::resetLanguages();
        DatabaseDump::restoreTables(['country', 'country_lang', 'country_shop']);
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'create endpoint' => ['POST', '/countries'];
        yield 'get endpoint' => ['GET', '/countries/1'];
    }

    public function testAddCountry(): int
    {
        $country = $this->createItem('/countries', [
            'names' => [
                'en-US' => 'My Country EN',
                'fr-FR' => 'My Country FR',
            ],
            'isoCode' => 'ZZ',
            'callPrefix' => 999,
            'defaultCurrencyId' => 0,
            'zoneId' => 1,
            'needZipCode' => false,
            'zipCodeFormat' => null,
            'addressFormat' => '',
            'enabled' => true,
            'containsStates' => false,
            'needIdNumber' => false,
            'displayTaxLabel' => true,
            'shopIds' => [1],
        ], ['country_write']);

        $this->assertArrayHasKey('countryId', $country);
        $this->assertEquals(['countryId' => $country['countryId']], $country);

        return $country['countryId'];
    }

    public function testAddCountryWithInvalidData(): void
    {
        $validationErrorsResponse = $this->createItem('/countries', [
            'isoCode' => '',
        ], ['country_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertIsArray($validationErrorsResponse);
        $this->assertValidationErrors([
            [
                'propertyPath' => 'names',
                'message' => 'The field names is required at least in your default language.',
            ],
            [
                'propertyPath' => 'isoCode',
                'message' => 'This value should not be blank.',
            ],
            [
                'propertyPath' => 'callPrefix',
                'message' => 'This value should not be null.',
            ],
            [
                'propertyPath' => 'defaultCurrencyId',
                'message' => 'This value should not be null.',
            ],
            [
                'propertyPath' => 'zoneId',
                'message' => 'This value should not be null.',
            ],
            [
                'propertyPath' => 'needZipCode',
                'message' => 'This value should not be null.',
            ],
            [
                'propertyPath' => 'addressFormat',
                'message' => 'This value should not be null.',
            ],
            [
                'propertyPath' => 'enabled',
                'message' => 'This value should not be null.',
            ],
            [
                'propertyPath' => 'containsStates',
                'message' => 'This value should not be null.',
            ],
            [
                'propertyPath' => 'needIdNumber',
                'message' => 'This value should not be null.',
            ],
            [
                'propertyPath' => 'displayTaxLabel',
                'message' => 'This value should not be null.',
            ],
        ], $validationErrorsResponse);
    }

    public function testGetCountry(): void
    {
        $country = $this->getItem('/countries/' . self::FRANCE_ID, ['country_read']);

        $this->assertEquals([
            'countryId' => self::FRANCE_ID,
            'names' => [
                'en-US' => 'France',
                'fr-FR' => 'France',
            ],
            'isoCode' => 'FR',
            'callPrefix' => 33,
            'defaultCurrencyId' => 0,
            'zoneId' => 1,
            'needZipCode' => true,
            'zipCodeFormat' => 'NNNNN',
            'addressFormat' => '',
            'enabled' => true,
            'containsStates' => false,
            'needIdNumber' => false,
            'displayTaxLabel' => true,
            'shopIds' => [1],
        ], $country);
    }

    public function testGetNonExistentCountry(): void
    {
        $this->getItem('/countries/999999', ['country_read'], Response::HTTP_NOT_FOUND);
    }
}
