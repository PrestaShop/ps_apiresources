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

use PrestaShop\PrestaShop\Core\Domain\Country\Exception\CannotDeleteCountryException;
use PrestaShop\PrestaShop\Core\Domain\Country\Exception\CountryNotFoundException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Tests\Resources\DatabaseDump;

class CountryEndpointTest extends ApiTestCase
{
    // Default PS address format from install-dev/data/xml/address_format.xml: it lists every
    // required token (firstname, lastname, address1, city, Country:name).
    private const DEFAULT_ADDRESS_FORMAT = "firstname lastname\ncompany\nvat_number\naddress1\naddress2\npostcode city\nCountry:name\nphone";

    // Same required tokens, different optional ones.
    private const UPDATED_ADDRESS_FORMAT = "firstname lastname\naddress1\npostcode city\nCountry:name";

    private const COUNTRY_TABLES = ['country', 'country_lang', 'country_shop', 'address_format'];

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        DatabaseDump::restoreTables(self::COUNTRY_TABLES);
        self::createApiClient(['country_read', 'country_write']);
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        DatabaseDump::restoreTables(self::COUNTRY_TABLES);
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'create endpoint' => ['POST', '/countries'];
        yield 'get endpoint' => ['GET', '/countries/1'];
        yield 'update endpoint' => ['PATCH', '/countries/1'];
        yield 'delete endpoint' => ['DELETE', '/countries/1'];
        yield 'list endpoint' => ['GET', '/countries'];
        // The bulk operations are filtered out on older cores, where they answer 404 instead of 401
        if (self::isVersionAtLeast('9.2.0')) {
            yield 'toggle status endpoint' => ['PUT', '/countries/1/toggle-status'];
            yield 'bulk update status endpoint' => ['PUT', '/countries/bulk-update-status'];
            yield 'bulk update zone endpoint' => ['PUT', '/countries/bulk-update-zone'];
            yield 'bulk delete endpoint' => ['DELETE', '/countries/bulk-delete'];
        }
    }

    public function testAddCountry(): int
    {
        $country = $this->createItem('/countries', $this->getCreatePayload(), ['country_write']);
        $this->assertEquals($this->expectedCountry($country['countryId']), $country);

        return $country['countryId'];
    }

    /**
     * @depends testAddCountry
     */
    public function testGetCountry(int $countryId): int
    {
        $this->assertEquals($this->expectedCountry($countryId), $this->getItem('/countries/' . $countryId, ['country_read']));

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
            'zipCodeFormat' => 'NNNNN',
            'addressFormat' => self::UPDATED_ADDRESS_FORMAT,
        ];
        $expected = $this->expectedCountry($countryId, $patchData);

        $updated = $this->partialUpdateItem('/countries/' . $countryId, $patchData, ['country_write']);
        $this->assertEquals($expected, $updated);
        // The persisted state matches the PATCH response
        $this->assertEquals($expected, $this->getItem('/countries/' . $countryId, ['country_read']));

        // A partial update with one field leaves the others untouched; before PrestaShop 9.2.0 the core edit command
        // needs names in every body (its getter is not nullable)
        $singleField = ['callPrefix' => 998];
        if (self::isVersionUnder('9.2.0')) {
            $singleField['names'] = $patchData['names'];
        }
        $updated = $this->partialUpdateItem('/countries/' . $countryId, $singleField, ['country_write']);
        $this->assertEquals($this->expectedCountry($countryId, $patchData + ['callPrefix' => 998]), $updated);

        return $countryId;
    }

    /**
     * @depends testEditCountry
     */
    public function testListCountries(int $countryId): int
    {
        // The whole envelope is asserted on a list the test fully controls: an exact filter on its own fixture
        $filtered = $this->listItems('/countries?orderBy=countryId&sortOrder=asc', ['country_read'], ['isoCode' => 'ZZ']);
        $this->assertEquals([
            'totalItems' => 1,
            'orderBy' => 'countryId',
            'sortOrder' => 'asc',
            'limit' => 50,
            'offset' => 0,
            'filters' => ['isoCode' => 'ZZ'],
            'items' => [
                [
                    'countryId' => $countryId,
                    'name' => 'Updated Country EN',
                    'isoCode' => 'ZZ',
                    'callPrefix' => 998,
                    'zoneName' => 'Europe',
                    'enabled' => false,
                ],
            ],
        ], $filtered);

        // The boolean filter matches exactly
        $disabled = $this->listItems('/countries', ['country_read'], ['isoCode' => 'ZZ', 'enabled' => false]);
        $this->assertEquals(1, $disabled['totalItems']);
        $enabled = $this->listItems('/countries', ['country_read'], ['isoCode' => 'ZZ', 'enabled' => true]);
        $this->assertEquals(0, $enabled['totalItems']);

        // The sort is asserted on the items, with a second fixture: the newest country has the highest id
        $second = $this->createItem('/countries', $this->getCreatePayload(['isoCode' => 'XY', 'callPrefix' => 997]), ['country_write']);
        $sorted = $this->listItems('/countries?orderBy=countryId&sortOrder=desc&limit=2', ['country_read']);
        $this->assertEquals(2, $sorted['limit']);
        $this->assertSame([$second['countryId'], $countryId], array_column($sorted['items'], 'countryId'));
        $paged = $this->listItems('/countries?orderBy=countryId&sortOrder=desc&limit=1&offset=1', ['country_read']);
        $this->assertSame([$countryId], array_column($paged['items'], 'countryId'));
        $this->deleteItem('/countries/' . $second['countryId'], ['country_write']);

        return $countryId;
    }

    /**
     * @depends testListCountries
     */
    public function testRemoveCountry(int $countryId): void
    {
        // The endpoint returns an empty response and a 204 HTTP code
        $this->assertNull($this->deleteItem('/countries/' . $countryId, ['country_write']));
        $this->getItem('/countries/' . $countryId, ['country_read'], Response::HTTP_NOT_FOUND);
    }

    public function testToggleCountryStatus(): void
    {
        // The toggle command exists from PrestaShop 9.2.0
        $this->markTestSkippedByMinVersion('9.2.0');

        $countryId = $this->createItem('/countries', $this->getCreatePayload(['isoCode' => 'XL', 'callPrefix' => 984]), ['country_write'])['countryId'];

        // No body: the status flips and the updated country is returned
        $expected = $this->expectedCountry($countryId, ['isoCode' => 'XL', 'callPrefix' => 984, 'enabled' => false]);
        $this->assertEquals($expected, $this->updateItem('/countries/' . $countryId . '/toggle-status', null, ['country_write']));
        $expected['enabled'] = true;
        $this->assertEquals($expected, $this->updateItem('/countries/' . $countryId . '/toggle-status', null, ['country_write']));
        $this->assertEquals($expected, $this->getItem('/countries/' . $countryId, ['country_read']));

        $this->updateItem('/countries/0/toggle-status', null, ['country_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->updateItem('/countries/999999/toggle-status', null, ['country_write'], Response::HTTP_NOT_FOUND);

        $this->deleteItem('/countries/' . $countryId, ['country_write']);
    }

    public function testNotFoundCountry(): void
    {
        $this->getItem('/countries/999999', ['country_read'], Response::HTTP_NOT_FOUND);
        $this->partialUpdateItem('/countries/999999', ['enabled' => true], ['country_write'], Response::HTTP_NOT_FOUND);
        $this->deleteItem('/countries/999999', ['country_write'], Response::HTTP_NOT_FOUND);
    }

    public function testInvalidCountryId(): void
    {
        // The id passes the \d+ requirement and is refused by the value object: a mapped exception, no field list
        $this->getItem('/countries/0', ['country_read'], Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->partialUpdateItem('/countries/0', ['enabled' => true], ['country_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->deleteItem('/countries/0', ['country_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testAddCountryWithInvalidData(): void
    {
        $response = $this->createItem('/countries', ['isoCode' => ''], ['country_write'], Response::HTTP_UNPROCESSABLE_ENTITY);

        // From PrestaShop 9.2.0 the default values of the form are applied before the validation,
        // older cores require every field
        $defaultsApplied = self::isVersionAtLeast('9.2.0');
        $notNull = ['message' => 'This value should not be null.'];
        $expectedErrors = array_values(array_filter([
            ['propertyPath' => 'names', 'message' => 'The field names is required at least in your default language.'],
            ['propertyPath' => 'isoCode', 'message' => 'This value should not be blank.'],
            ['propertyPath' => 'callPrefix'] + $notNull,
            $defaultsApplied ? null : ['propertyPath' => 'defaultCurrencyId'] + $notNull,
            ['propertyPath' => 'zoneId'] + $notNull,
            $defaultsApplied ? null : ['propertyPath' => 'needZipCode'] + $notNull,
            ['propertyPath' => 'addressFormat', 'message' => 'This value should not be blank.'],
            $defaultsApplied ? null : ['propertyPath' => 'enabled'] + $notNull,
            $defaultsApplied ? null : ['propertyPath' => 'containsStates'] + $notNull,
            $defaultsApplied ? null : ['propertyPath' => 'needIdNumber'] + $notNull,
            $defaultsApplied ? null : ['propertyPath' => 'displayTaxLabel'] + $notNull,
            $defaultsApplied ? null : ['propertyPath' => 'shopIds'] + $notNull,
        ]));
        $this->assertValidationErrors($expectedErrors, $response);
    }

    public function testAddCountryWithInvalidValues(): void
    {
        $response = $this->createItem('/countries', $this->getCreatePayload([
            'isoCode' => 'ZZZ',
            'callPrefix' => -1,
            'defaultCurrencyId' => -1,
            'zoneId' => 0,
            'zipCodeFormat' => str_repeat('N', 13),
            'shopIds' => [0],
        ]), ['country_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertValidationErrors([
            ['propertyPath' => 'isoCode', 'message' => 'This value is too long. It should have 2 characters or less.'],
            ['propertyPath' => 'callPrefix', 'message' => 'This value should be either positive or zero.'],
            ['propertyPath' => 'defaultCurrencyId', 'message' => 'This value should be either positive or zero.'],
            ['propertyPath' => 'zoneId', 'message' => 'This value should be positive.'],
            ['propertyPath' => 'zipCodeFormat', 'message' => 'This value is too long. It should have 12 characters or less.'],
            ['propertyPath' => 'shopIds[0]', 'message' => 'This value should be positive.'],
        ], $response);

        // Refused by the typed regex constraints: the message carries the value, only the status is asserted
        foreach ([
            ['names' => ['en-US' => 'Name with <tag>', 'fr-FR' => 'Nom avec <tag>']],
            ['isoCode' => '1Z'],
            ['zipCodeFormat' => 'ABC!'],
        ] as $overrides) {
            $this->createItem('/countries', $this->getCreatePayload($overrides), ['country_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    public function testEditCountryWithInvalidValues(): void
    {
        $countryId = $this->createItem('/countries', $this->getCreatePayload(['isoCode' => 'XX', 'callPrefix' => 996]), ['country_write'])['countryId'];

        $response = $this->partialUpdateItem('/countries/' . $countryId, [
            'callPrefix' => -1,
            'zoneId' => 0,
        ], ['country_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertValidationErrors([
            ['propertyPath' => 'callPrefix', 'message' => 'This value should be either positive or zero.'],
            ['propertyPath' => 'zoneId', 'message' => 'This value should be positive.'],
        ], $response);

        // A localized field patched without its default language
        $this->partialUpdateItem('/countries/' . $countryId, ['names' => ['fr-FR' => 'Only FR']], ['country_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
        // An empty ISO code is refused by the module constraint on every core
        $response = $this->partialUpdateItem('/countries/' . $countryId, ['isoCode' => ''], ['country_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertValidationErrors([
            ['propertyPath' => 'isoCode', 'message' => 'This value should not be blank.'],
        ], $response);
        if (self::isVersionAtLeast('9.2.0')) {
            // Refused by the core handler, a mapped exception without field list (older cores ignore the format on write)
            $this->partialUpdateItem('/countries/' . $countryId, ['addressFormat' => ''], ['country_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->deleteItem('/countries/' . $countryId, ['country_write']);
    }

    public function testAddCountryWithUnknownZone(): void
    {
        if (self::isVersionUnder('9.2.1')) {
            // Older cores store the unknown zone id
            $country = $this->createItem('/countries', $this->getCreatePayload(['zoneId' => 999999]), ['country_write']);
            $this->assertEquals(999999, $country['zoneId']);
            $this->deleteItem('/countries/' . $country['countryId'], ['country_write']);

            return;
        }

        // The core checks that the zone exists from PrestaShop 9.2.1
        $this->createItem('/countries', $this->getCreatePayload(['zoneId' => 999999]), ['country_write'], Response::HTTP_NOT_FOUND);

        $countryId = $this->createItem('/countries', $this->getCreatePayload(['isoCode' => 'XW', 'callPrefix' => 995]), ['country_write'])['countryId'];
        $this->partialUpdateItem('/countries/' . $countryId, ['zoneId' => 999999], ['country_write'], Response::HTTP_NOT_FOUND);
        $this->deleteItem('/countries/' . $countryId, ['country_write']);
    }

    public function testAddCountryWithDuplicateIsoCode(): void
    {
        if (self::isVersionUnder('9.2.0')) {
            // Older cores create the duplicate
            $country = $this->createItem('/countries', $this->getCreatePayload(['isoCode' => 'FR']), ['country_write']);
            $this->assertEquals('FR', $country['isoCode']);
            $this->deleteItem('/countries/' . $country['countryId'], ['country_write']);

            return;
        }

        // The ISO code is unique from PrestaShop 9.2.0
        $this->createItem('/countries', $this->getCreatePayload(['isoCode' => 'FR']), ['country_write'], Response::HTTP_UNPROCESSABLE_ENTITY);

        $countryId = $this->createItem('/countries', $this->getCreatePayload(['isoCode' => 'XV', 'callPrefix' => 994]), ['country_write'])['countryId'];
        $this->partialUpdateItem('/countries/' . $countryId, ['isoCode' => 'FR'], ['country_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->deleteItem('/countries/' . $countryId, ['country_write']);
    }

    public function testDeleteDefaultCountry(): void
    {
        $defaultCountryId = (int) \Configuration::get('PS_COUNTRY_DEFAULT');

        // The core refuses the default country from PrestaShop 9.2.1; before, the legacy model throws an exception
        // the core does not map: the request answers 500 and the country is not deleted either
        $expectedStatus = self::isVersionAtLeast('9.2.1') ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_INTERNAL_SERVER_ERROR;
        $this->deleteItem('/countries/' . $defaultCountryId, ['country_write'], $expectedStatus);
        $this->assertEquals($defaultCountryId, $this->getItem('/countries/' . $defaultCountryId, ['country_read'])['countryId']);
    }

    public function testAddCountryWithoutShopIds(): void
    {
        $payload = $this->getCreatePayload(['isoCode' => 'XU', 'callPrefix' => 993]);
        unset($payload['shopIds']);

        if (self::isVersionUnder('9.2.0')) {
            // No default value on older cores: the field is required
            $response = $this->createItem('/countries', $payload, ['country_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
            $this->assertValidationErrors([
                ['propertyPath' => 'shopIds', 'message' => 'This value should not be null.'],
            ], $response);

            return;
        }

        // The shops of the context are the default
        $country = $this->createItem('/countries', $payload, ['country_write']);
        $this->assertEquals([1], $country['shopIds']);
        $this->deleteItem('/countries/' . $country['countryId'], ['country_write']);
    }

    /**
     * @dataProvider invalidAddressFormatProvider
     *
     * @param array<int, array{propertyPath: string, message: string}> $expectedErrors
     */
    public function testAddCountryWithInvalidAddressFormat(string $invalidFormat, array $expectedErrors): void
    {
        // The address format is checked by the core from PrestaShop 9.2.0
        $this->markTestSkippedByMinVersion('9.2.0');

        $response = $this->createItem('/countries', $this->getCreatePayload(['addressFormat' => $invalidFormat]), ['country_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertValidationErrors($expectedErrors, $response);
    }

    /**
     * @dataProvider invalidAddressFormatProvider
     *
     * @param array<int, array{propertyPath: string, message: string}> $expectedErrors
     */
    public function testEditCountryWithInvalidAddressFormat(string $invalidFormat, array $expectedErrors): void
    {
        $this->markTestSkippedByMinVersion('9.2.0');

        $countryId = $this->createItem('/countries', $this->getCreatePayload(['isoCode' => 'XT', 'callPrefix' => 992]), ['country_write'])['countryId'];

        $response = $this->partialUpdateItem('/countries/' . $countryId, ['addressFormat' => $invalidFormat], ['country_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
        if ('' !== $invalidFormat) {
            $this->assertValidationErrors($expectedErrors, $response);
        }
        // An empty format on a partial update is refused by the core handler: a mapped exception, no field list

        $this->deleteItem('/countries/' . $countryId, ['country_write']);
    }

    /**
     * @return iterable<string, array{0: string, 1: array<int, array{propertyPath: string, message: string}>}>
     */
    public static function invalidAddressFormatProvider(): iterable
    {
        yield 'empty format' => [
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

    public function testBulkUpdateStatusCountries(): void
    {
        $this->markTestSkippedByMinVersion('9.2.0');

        $first = $this->createItem('/countries', $this->getCreatePayload(['isoCode' => 'XS', 'callPrefix' => 991]), ['country_write'])['countryId'];
        $second = $this->createItem('/countries', $this->getCreatePayload(['isoCode' => 'XR', 'callPrefix' => 990]), ['country_write'])['countryId'];

        $this->assertNull($this->updateItem('/countries/bulk-update-status', ['countryIds' => [$first, $second], 'enabled' => false], ['country_write'], Response::HTTP_NO_CONTENT));
        $this->assertFalse($this->getItem('/countries/' . $first, ['country_read'])['enabled']);
        $this->assertFalse($this->getItem('/countries/' . $second, ['country_read'])['enabled']);

        // A partial failure reports the unknown country and updates the others
        $this->bulkCommandItemsWithExpectedErrors(
            Request::METHOD_PUT,
            '/countries/bulk-update-status',
            ['countryIds' => [$first, 999999], 'enabled' => true],
            [
                [
                    'type' => CountryNotFoundException::class,
                    'message' => 'Country #999999 was not found',
                    'status' => Response::HTTP_NOT_FOUND,
                ],
            ],
            ['country_write']
        );
        $this->assertTrue($this->getItem('/countries/' . $first, ['country_read'])['enabled']);

        // The list and the status are validated before anything runs
        $response = $this->updateItem('/countries/bulk-update-status', ['countryIds' => ['3abc'], 'enabled' => true], ['country_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertValidationErrors([
            ['propertyPath' => 'countryIds[0]', 'message' => 'This value should be of type integer.'],
        ], $response);
        $this->updateItem('/countries/bulk-update-status', ['countryIds' => [], 'enabled' => true], ['country_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->updateItem('/countries/bulk-update-status', ['countryIds' => [$first]], ['country_write'], Response::HTTP_UNPROCESSABLE_ENTITY);

        $this->deleteItem('/countries/' . $first, ['country_write']);
        $this->deleteItem('/countries/' . $second, ['country_write']);
    }

    public function testBulkUpdateZoneCountries(): void
    {
        $this->markTestSkippedByMinVersion('9.2.0');

        $first = $this->createItem('/countries', $this->getCreatePayload(['isoCode' => 'XQ', 'callPrefix' => 989]), ['country_write'])['countryId'];
        $second = $this->createItem('/countries', $this->getCreatePayload(['isoCode' => 'XP', 'callPrefix' => 988]), ['country_write'])['countryId'];

        $this->assertNull($this->updateItem('/countries/bulk-update-zone', ['countryIds' => [$first, $second], 'zoneId' => 2], ['country_write'], Response::HTTP_NO_CONTENT));
        $this->assertEquals(2, $this->getItem('/countries/' . $first, ['country_read'])['zoneId']);
        $this->assertEquals(2, $this->getItem('/countries/' . $second, ['country_read'])['zoneId']);

        // An unknown zone is refused before any country is touched
        $this->updateItem('/countries/bulk-update-zone', ['countryIds' => [$first], 'zoneId' => 999999], ['country_write'], Response::HTTP_NOT_FOUND);
        $this->assertEquals(2, $this->getItem('/countries/' . $first, ['country_read'])['zoneId']);
        $response = $this->updateItem('/countries/bulk-update-zone', ['countryIds' => [$first], 'zoneId' => 0], ['country_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertValidationErrors([
            ['propertyPath' => 'zoneId', 'message' => 'This value should be positive.'],
        ], $response);

        // A partial failure reports the unknown country and updates the others
        $this->bulkCommandItemsWithExpectedErrors(
            Request::METHOD_PUT,
            '/countries/bulk-update-zone',
            ['countryIds' => [$first, 999999], 'zoneId' => 1],
            [
                [
                    'type' => CountryNotFoundException::class,
                    'message' => 'Country #999999 was not found',
                    'status' => Response::HTTP_NOT_FOUND,
                ],
            ],
            ['country_write']
        );
        $this->assertEquals(1, $this->getItem('/countries/' . $first, ['country_read'])['zoneId']);

        $this->deleteItem('/countries/' . $first, ['country_write']);
        $this->deleteItem('/countries/' . $second, ['country_write']);
    }

    public function testBulkDeleteCountries(): void
    {
        // The bulk command exists from PrestaShop 9.2.0
        $this->markTestSkippedByMinVersion('9.2.0');

        $first = $this->createItem('/countries', $this->getCreatePayload(['isoCode' => 'XO', 'callPrefix' => 987]), ['country_write'])['countryId'];
        $second = $this->createItem('/countries', $this->getCreatePayload(['isoCode' => 'XN', 'callPrefix' => 986]), ['country_write'])['countryId'];

        $this->assertNull($this->bulkDeleteItems('/countries/bulk-delete', ['countryIds' => [$first]], ['country_write']));
        $this->getItem('/countries/' . $first, ['country_read'], Response::HTTP_NOT_FOUND);

        // A partial failure reports the unknown country and deletes the others
        $this->bulkCommandItemsWithExpectedErrors(
            Request::METHOD_DELETE,
            '/countries/bulk-delete',
            ['countryIds' => [$second, 999999]],
            [
                [
                    'type' => CountryNotFoundException::class,
                    'message' => 'Country #999999 was not found',
                    'status' => Response::HTTP_NOT_FOUND,
                ],
            ],
            ['country_write']
        );
        $this->getItem('/countries/' . $second, ['country_read'], Response::HTTP_NOT_FOUND);

        // The default country of the shop is refused from PrestaShop 9.2.1 and the others are deleted; before, the
        // exception of the legacy model is not collected and aborts the whole request with a 500
        $third = $this->createItem('/countries', $this->getCreatePayload(['isoCode' => 'XM', 'callPrefix' => 985]), ['country_write'])['countryId'];
        $defaultCountryId = (int) \Configuration::get('PS_COUNTRY_DEFAULT');
        if (self::isVersionUnder('9.2.1')) {
            $this->bulkDeleteItems('/countries/bulk-delete', ['countryIds' => [$defaultCountryId, $third]], ['country_write'], Response::HTTP_INTERNAL_SERVER_ERROR);
            $this->assertEquals($defaultCountryId, $this->getItem('/countries/' . $defaultCountryId, ['country_read'])['countryId']);
            $this->deleteItem('/countries/' . $third, ['country_write']);
        } else {
            $this->bulkCommandItemsWithExpectedErrors(
                Request::METHOD_DELETE,
                '/countries/bulk-delete',
                ['countryIds' => [$defaultCountryId, $third]],
                [
                    [
                        'type' => CannotDeleteCountryException::class,
                        'message' => sprintf('The default country #%d cannot be deleted', $defaultCountryId),
                        'status' => Response::HTTP_UNPROCESSABLE_ENTITY,
                    ],
                ],
                ['country_write']
            );
            $this->getItem('/countries/' . $third, ['country_read'], Response::HTTP_NOT_FOUND);
            $this->assertEquals($defaultCountryId, $this->getItem('/countries/' . $defaultCountryId, ['country_read'])['countryId']);
        }

        // The list is validated before anything runs
        $this->bulkDeleteItems('/countries/bulk-delete', ['countryIds' => []], ['country_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
        $response = $this->bulkDeleteItems('/countries/bulk-delete', ['countryIds' => ['3abc']], ['country_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertValidationErrors([
            ['propertyPath' => 'countryIds[0]', 'message' => 'This value should be of type integer.'],
        ], $response);
    }

    /**
     * The payload of the fixture, the expected payloads are built from it. The ISO codes of the fixtures are
     * user-assigned ones (ZZ, XA to XZ): no installed country uses them.
     *
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    private function getCreatePayload(array $overrides = []): array
    {
        return array_replace([
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
        ], $overrides);
    }

    /**
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    private function expectedCountry(int $countryId, array $overrides = []): array
    {
        $expected = array_replace(['countryId' => $countryId] + $this->getCreatePayload(), $overrides);
        // Before PrestaShop 9.2.0 the handlers ignore the address format and the query returns an empty string
        $expected['addressFormat'] = self::isVersionAtLeast('9.2.0') ? $expected['addressFormat'] : '';

        return $expected;
    }
}
