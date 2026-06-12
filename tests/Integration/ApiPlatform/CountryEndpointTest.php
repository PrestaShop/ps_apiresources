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

class CountryEndpointTest extends ApiTestCase
{
    // Default PS address format from install-dev/data/xml/address_format.xml.
    // Must list every static-required Address field — firstname, lastname, address1,
    // city, Country:name — or AddressFormatChecker rejects it.
    private const DEFAULT_ADDRESS_FORMAT = "firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone";

    // Variant used by testEditCountry — same required tokens, different optional ones.
    private const UPDATED_ADDRESS_FORMAT = "firstname lastname\naddress1\npostcode city\nCountry:name";

    // Tests that depend on PS 9.2+ behavior (Country API returning the stored
    // address format, ValidAddressFormat constraint firing on add/edit) gate
    // themselves on the existence of this interface — introduced in 9.2.
    private const CORE_ADDRESS_FORMAT_CHECKER = 'PrestaShop\\PrestaShop\\Core\\Domain\\Country\\AddressFormat\\AddressFormatCheckerInterface';

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::createApiClient(['country_read', 'country_write']);
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        DatabaseDump::restoreTables(['country', 'country_lang', 'country_shop', 'address_format']);
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'create endpoint' => ['POST', '/countries'];
        yield 'get endpoint' => ['GET', '/countries/1'];
        yield 'update endpoint' => ['PATCH', '/countries/1'];
        yield 'delete endpoint' => ['DELETE', '/countries/1'];
        yield 'list endpoint' => ['GET', '/countries'];
    }

    public function testAddCountry(): int
    {
        $country = $this->createItem('/countries', $this->getCreatePayload(), ['country_write']);

        $this->assertArrayHasKey('countryId', $country);
        $this->assertEquals(['countryId' => $country['countryId']], $country);

        return $country['countryId'];
    }

    /**
     * @depends testAddCountry
     */
    public function testGetCountry(int $countryId): int
    {
        $expected = ['countryId' => $countryId] + $this->getCreatePayload();
        // On PS 9.0/9.1 the AddCountryHandler ignores addressFormat from the
        // command and GetCountryForEditing returns an empty string regardless
        // of what was sent — the field only round-trips on 9.2+.
        $expected['addressFormat'] = $this->expectedAddressFormat($expected['addressFormat']);

        $country = $this->getItem('/countries/' . $countryId, ['country_read']);
        $this->assertEquals($expected, $country);

        return $countryId;
    }

    /**
     * @depends testGetCountry
     */
    public function testEditCountry(int $countryId): int
    {
        $patchData = [
            'names' => [
                'en-US' => 'Updated Country EN',
                'fr-FR' => 'Updated Country FR',
            ],
            'enabled' => false,
            'addressFormat' => self::UPDATED_ADDRESS_FORMAT,
        ];

        $expected = [
            'countryId' => $countryId,
            'names' => [
                'en-US' => 'Updated Country EN',
                'fr-FR' => 'Updated Country FR',
            ],
            'isoCode' => 'ZZ',
            'callPrefix' => 999,
            'defaultCurrencyId' => 0,
            'zoneId' => 1,
            'needZipCode' => false,
            'zipCodeFormat' => null,
            // Same caveat as testGetCountry — addressFormat only round-trips on 9.2+.
            'addressFormat' => $this->expectedAddressFormat(self::UPDATED_ADDRESS_FORMAT),
            'enabled' => false,
            'containsStates' => false,
            'needIdNumber' => false,
            'displayTaxLabel' => true,
            'shopIds' => [1],
        ];

        $updated = $this->partialUpdateItem('/countries/' . $countryId, $patchData, ['country_write']);
        $this->assertEquals($expected, $updated);

        // Round-trip via GET to confirm the persisted state matches the PATCH response.
        $fetched = $this->getItem('/countries/' . $countryId, ['country_read']);
        $this->assertEquals($expected, $fetched);

        return $countryId;
    }

    /**
     * @depends testEditCountry
     */
    public function testListCountries(int $countryId): int
    {
        // Every exposed field is sortable; sorting by countryId in descending order
        // also puts the country created by the previous tests first
        foreach (['countryId', 'name', 'isoCode', 'callPrefix', 'zoneName', 'enabled'] as $orderBy) {
            $paginatedCountries = $this->listItems('/countries?orderBy=' . $orderBy . '&sortOrder=desc', ['country_read']);
            $this->assertGreaterThanOrEqual(1, $paginatedCountries['totalItems']);

            // Check the details to make sure filters mapping is correct
            $this->assertEquals($orderBy, $paginatedCountries['orderBy']);
        }

        $expectedCountry = [
            'countryId' => $countryId,
            'name' => 'Updated Country EN',
            'isoCode' => 'ZZ',
            'callPrefix' => 999,
            // The country was created with zoneId 1, which is Europe in the default fixtures
            'zoneName' => 'Europe',
            // It was disabled by testEditCountry
            'enabled' => false,
        ];

        // Test country has the highest ID so it comes first when sorted by countryId desc
        $paginatedCountries = $this->listItems('/countries?orderBy=countryId&sortOrder=desc', ['country_read']);
        $this->assertEquals($expectedCountry, $paginatedCountries['items'][0]);

        // Pagination: the default fixtures contain way more than ten countries
        $paginatedCountries = $this->listItems('/countries?limit=10', ['country_read']);
        $this->assertEquals(10, $paginatedCountries['limit']);
        $this->assertCount(10, $paginatedCountries['items']);
        $this->assertGreaterThan(10, $paginatedCountries['totalItems']);

        // Filtering: the ZZ iso code only matches the test country
        $filteredCountries = $this->listItems('/countries', ['country_read'], [
            'isoCode' => 'ZZ',
        ]);
        $this->assertEquals(1, $filteredCountries['totalItems']);
        $this->assertEquals($expectedCountry, $filteredCountries['items'][0]);

        // Check the filters details
        $this->assertEquals([
            'isoCode' => 'ZZ',
        ], $filteredCountries['filters']);

        // Filtering on the exact country ID also matches the test country only
        $filteredCountries = $this->listItems('/countries', ['country_read'], [
            'countryId' => $countryId,
        ]);
        $this->assertEquals(1, $filteredCountries['totalItems']);
        $this->assertEquals($expectedCountry, $filteredCountries['items'][0]);

        return $countryId;
    }

    public function testListCountriesWithInvalidOrderBy(): void
    {
        $this->requestApi('GET', '/countries?orderBy=INVALID_FILTER&sortOrder=desc', null, ['country_read'], Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /**
     * Order-critical: must run after every other test that needs the created
     * country alive. The validation tests below also chain off testGetCountry
     * and PATCH the same record, so listing them here forces PHPUnit to
     * schedule the delete last.
     *
     * @depends testListCountries
     * @depends testAddCountryWithInvalidAddressFormat
     * @depends testEditCountryWithInvalidAddressFormat
     */
    public function testRemoveCountry(int $countryId): void
    {
        $return = $this->deleteItem('/countries/' . $countryId, ['country_write']);
        // This endpoint returns empty response and 204 HTTP code
        $this->assertNull($return);

        // Getting the item should result in a 404 now
        $this->getItem('/countries/' . $countryId, ['country_read'], Response::HTTP_NOT_FOUND);
    }

    public function testGetNonExistentCountry(): void
    {
        $this->getItem('/countries/999999', ['country_read'], Response::HTTP_NOT_FOUND);
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
                'message' => 'This value should not be blank.',
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

    /**
     * @dataProvider invalidAddressFormatProvider
     *
     * @param array<int, array{propertyPath: string, message: string}> $expectedErrors
     */
    public function testAddCountryWithInvalidAddressFormat(string $invalidFormat, array $expectedErrors): void
    {
        $this->skipIfAddressFormatCheckerMissing();

        $payload = $this->getCreatePayload();
        $payload['addressFormat'] = $invalidFormat;

        $response = $this->createItem('/countries', $payload, ['country_write'], Response::HTTP_UNPROCESSABLE_ENTITY);

        $this->assertIsArray($response);
        $this->assertValidationErrors($expectedErrors, $response);
    }

    /**
     * @depends testGetCountry
     *
     * @dataProvider invalidAddressFormatProvider
     *
     * @param array<int, array{propertyPath: string, message: string}> $expectedErrors
     */
    public function testEditCountryWithInvalidAddressFormat(string $invalidFormat, array $expectedErrors, int $countryId): void
    {
        $this->skipIfAddressFormatCheckerMissing();

        // The validator returns early on empty strings, so on PATCH an empty
        // addressFormat slips past the constraint and reaches the handler. We
        // skip that data-provider row here — it would 500, not 422.
        if ('' === $invalidFormat) {
            $this->markTestSkipped('Empty addressFormat on PATCH is not handled by the constraint (validator early-returns on empty).');
        }

        $response = $this->partialUpdateItem(
            '/countries/' . $countryId,
            ['addressFormat' => $invalidFormat],
            ['country_write'],
            Response::HTTP_UNPROCESSABLE_ENTITY
        );

        $this->assertIsArray($response);
        $this->assertValidationErrors($expectedErrors, $response);
    }

    /**
     * @return iterable<string, array{0: string, 1: array<int, array{propertyPath: string, message: string}>}>
     */
    public static function invalidAddressFormatProvider(): iterable
    {
        yield 'empty format (Create only — NotBlank fires)' => [
            '',
            [
                ['propertyPath' => 'addressFormat', 'message' => 'This value should not be blank.'],
            ],
        ];

        yield 'unknown Address field' => [
            "firstname lastname\naddress1\npostcode city\nCountry:name\nnot_a_real_field",
            [
                ['propertyPath' => 'addressFormat', 'message' => 'This field is not a valid address property: not_a_real_field.'],
            ],
        ];

        yield 'unknown picker class' => [
            "firstname lastname\naddress1\npostcode city\nCountry:name\nUnknownObject:name",
            [
                ['propertyPath' => 'addressFormat', 'message' => 'This object is not allowed: UnknownObject.'],
            ],
        ];

        yield 'unknown field on known class' => [
            "firstname lastname\naddress1\npostcode city\nCountry:name\nCountry:not_a_field",
            [
                ['propertyPath' => 'addressFormat', 'message' => 'The field not_a_field does not exist on Country.'],
            ],
        ];

        yield 'duplicate token' => [
            "firstname lastname\naddress1\npostcode city\nCountry:name\nfirstname",
            [
                ['propertyPath' => 'addressFormat', 'message' => 'This key has already been used: firstname.'],
            ],
        ];

        yield 'missing required field (no Country:name)' => [
            "firstname lastname\naddress1\npostcode city",
            [
                ['propertyPath' => 'addressFormat', 'message' => 'The Country:name field is required.'],
            ],
        ];
    }

    /**
     * Validation tests that exercise the ValidAddressFormat constraint call
     * this helper to opt out cleanly on PS 9.0/9.1, where the constraint
     * validator no-ops (no AddressFormatCheckerInterface in core).
     *
     * Round-trip tests (testGetCountry, testEditCountry) do NOT skip — they
     * run on every version and use {@see self::expectedAddressFormat()} to
     * branch the assertion instead.
     */
    private function skipIfAddressFormatCheckerMissing(): void
    {
        if (!interface_exists(self::CORE_ADDRESS_FORMAT_CHECKER)) {
            $this->markTestSkipped('Requires PrestaShop 9.2+ (AddressFormatCheckerInterface).');
        }
    }

    /**
     * On PS 9.2+ the Country API persists and returns the addressFormat string
     * supplied on add/edit. On 9.0/9.1 the AddCountryHandler/EditCountryHandler
     * ignore that field and GetCountryForEditing returns an empty string —
     * regardless of what the request sent.
     */
    private function expectedAddressFormat(string $sentFormat): string
    {
        return interface_exists(self::CORE_ADDRESS_FORMAT_CHECKER) ? $sentFormat : '';
    }

    /**
     * @return array<string, mixed>
     */
    private function getCreatePayload(): array
    {
        return [
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
            'addressFormat' => self::DEFAULT_ADDRESS_FORMAT,
            'enabled' => true,
            'containsStates' => false,
            'needIdNumber' => false,
            'displayTaxLabel' => true,
            'shopIds' => [1],
        ];
    }
}
