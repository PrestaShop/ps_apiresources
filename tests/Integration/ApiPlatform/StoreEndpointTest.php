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

class StoreEndpointTest extends ApiTestCase
{
    private const STORE_TABLES = ['store', 'store_lang', 'store_shop'];

    // The store commands exist from PrestaShop 9.3.0 (PrestaShop#41414)
    private const COMMANDS_VERSION = '9.3.0';

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        DatabaseDump::restoreTables(self::STORE_TABLES);
        self::createApiClient(['store_read', 'store_write']);
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        DatabaseDump::restoreTables(self::STORE_TABLES);
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'get endpoint' => ['GET', '/stores/1'];
        yield 'toggle status endpoint' => ['PUT', '/stores/1/toggle-status'];
        yield 'delete endpoint' => ['DELETE', '/stores/1'];
        yield 'bulk update status endpoint' => ['PUT', '/stores/bulk-update-status'];
        yield 'bulk delete endpoint' => ['DELETE', '/stores/bulk-delete'];
        // The create and update operations are filtered out on older cores, where they answer 404 instead of 401
        if (self::isVersionAtLeast(self::COMMANDS_VERSION)) {
            yield 'create endpoint' => ['POST', '/stores'];
            yield 'update endpoint' => ['PATCH', '/stores/1'];
        }
    }

    public function testAddStore(): int
    {
        $this->markTestSkippedByMinVersion(self::COMMANDS_VERSION);

        $store = $this->createItem('/stores', $this->getCreatePayload(), ['store_write']);
        $this->assertEquals($this->expectedStore($store['storeId']), $store);

        return $store['storeId'];
    }

    public function testGetStore(): int
    {
        $storeId = $this->createFixtureStore();
        $this->assertEquals($this->expectedStore($storeId), $this->getItem('/stores/' . $storeId, ['store_read']));

        return $storeId;
    }

    /**
     * @depends testGetStore
     */
    public function testEditStore(int $storeId): int
    {
        $this->markTestSkippedByMinVersion(self::COMMANDS_VERSION);

        $patchData = [
            'names' => [
                'en-US' => 'Updated store EN',
                'fr-FR' => 'Updated store FR',
            ],
            'addresses' => [
                'en-US' => '1 updated street EN',
                'fr-FR' => '1 updated street FR',
            ],
            'secondaryAddresses' => [
                'en-US' => 'Floor 2 EN',
                'fr-FR' => 'Floor 2 FR',
            ],
            'hours' => [
                'en-US' => ['', '10:00 | 19:00', '10:00 | 19:00', '10:00 | 19:00', '10:00 | 19:00', '10:00 | 19:00', '10:00 | 12:00'],
                'fr-FR' => ['', '10:00 | 19:00', '10:00 | 19:00', '10:00 | 19:00', '10:00 | 19:00', '10:00 | 19:00', '10:00 | 12:00'],
            ],
            'notes' => [
                'en-US' => 'Updated note EN',
                'fr-FR' => 'Updated note FR',
            ],
            'city' => 'Lille',
            'postcode' => '59000',
            'latitude' => 50.62925,
            'longitude' => 3.057256,
            'phone' => '0320000000',
            'fax' => '0320000001',
            'email' => 'lille@example.com',
            'enabled' => false,
        ];
        $expected = $this->expectedStore($storeId, $patchData);

        $updated = $this->partialUpdateItem('/stores/' . $storeId, $patchData, ['store_write']);
        $this->assertEquals($expected, $updated);
        // The persisted state matches the PATCH response
        $this->assertEquals($expected, $this->getItem('/stores/' . $storeId, ['store_read']));

        // A partial update with one field leaves the others untouched
        $updated = $this->partialUpdateItem('/stores/' . $storeId, ['city' => 'Roubaix'], ['store_write']);
        $expected['city'] = 'Roubaix';
        $this->assertEquals($expected, $updated);

        // A localized field replaces every language: the language left out gets the value of the default language
        // when the legacy model requires the field (names, addresses), an empty value otherwise (notes)
        $updated = $this->partialUpdateItem('/stores/' . $storeId, [
            'names' => ['en-US' => 'Only EN'],
            'notes' => ['en-US' => 'Only EN note'],
        ], ['store_write']);
        $expected['names'] = ['en-US' => 'Only EN', 'fr-FR' => 'Only EN'];
        $expected['notes'] = ['en-US' => 'Only EN note', 'fr-FR' => ''];
        $this->assertEquals($expected, $updated);

        // An empty string clears the phone, the fax and the email, read back as null
        $updated = $this->partialUpdateItem('/stores/' . $storeId, ['phone' => '', 'fax' => '', 'email' => ''], ['store_write']);
        $expected['phone'] = null;
        $expected['fax'] = null;
        $expected['email'] = null;
        $this->assertEquals($expected, $updated);

        // A null coordinate is refused before the validation: the core serializer cannot turn null into a
        // DecimalNumber (a malformed request, 400), the stored value stays
        $this->partialUpdateItem('/stores/' . $storeId, ['latitude' => null, 'longitude' => null], ['store_write'], Response::HTTP_BAD_REQUEST);
        $this->assertEquals($expected, $this->getItem('/stores/' . $storeId, ['store_read']));

        return $storeId;
    }

    /**
     * @depends testEditStore
     */
    public function testEditStoreState(int $storeId): int
    {
        $this->markTestSkippedByMinVersion(self::COMMANDS_VERSION);

        $countryIdUS = $this->getCountryId('US');
        $stateIdFlorida = $this->getStateId('FL');

        // A country containing states needs one of its states: the country and the state move together
        $expected = $this->getItem('/stores/' . $storeId, ['store_read']);
        $this->partialUpdateItem('/stores/' . $storeId, ['countryId' => $countryIdUS], ['store_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertEquals($expected, $this->getItem('/stores/' . $storeId, ['store_read']));
        $expected = array_replace($expected, ['countryId' => $countryIdUS, 'stateId' => $stateIdFlorida]);
        $this->assertEquals($expected, $this->partialUpdateItem('/stores/' . $storeId, ['countryId' => $countryIdUS, 'stateId' => $stateIdFlorida], ['store_write']));

        // The state cannot be removed while the country requires one, nor kept when the new country has none
        $this->partialUpdateItem('/stores/' . $storeId, ['stateId' => null], ['store_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->partialUpdateItem('/stores/' . $storeId, ['countryId' => $this->getCountryId('FR')], ['store_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->partialUpdateItem('/stores/' . $storeId, ['stateId' => $this->getStateId('ON')], ['store_write'], Response::HTTP_UNPROCESSABLE_ENTITY);

        $this->assertEquals($expected, $this->getItem('/stores/' . $storeId, ['store_read']));

        // A null state is removed together with the country change
        $expected = array_replace($expected, ['countryId' => $this->getCountryId('FR'), 'stateId' => null]);
        $this->assertEquals($expected, $this->partialUpdateItem('/stores/' . $storeId, ['countryId' => $this->getCountryId('FR'), 'stateId' => null], ['store_write']));
        $this->assertEquals($expected, $this->getItem('/stores/' . $storeId, ['store_read']));

        return $storeId;
    }

    /**
     * @depends testGetStore
     */
    public function testToggleStoreStatus(int $storeId): int
    {
        // The edit test may have changed the status of the fixture: the toggle flips whatever the current state is
        $enabled = $this->getItem('/stores/' . $storeId, ['store_read'])['enabled'];
        $this->assertNull($this->updateItem('/stores/' . $storeId . '/toggle-status', null, ['store_write'], Response::HTTP_NO_CONTENT));
        $this->assertSame(!$enabled, $this->getItem('/stores/' . $storeId, ['store_read'])['enabled']);
        $this->assertNull($this->updateItem('/stores/' . $storeId . '/toggle-status', null, ['store_write'], Response::HTTP_NO_CONTENT));
        $this->assertSame($enabled, $this->getItem('/stores/' . $storeId, ['store_read'])['enabled']);

        return $storeId;
    }

    /**
     * @depends testToggleStoreStatus
     */
    public function testDeleteStore(int $storeId): void
    {
        // The endpoint returns an empty response and a 204 HTTP code
        $this->assertNull($this->deleteItem('/stores/' . $storeId, ['store_write']));
        $this->getItem('/stores/' . $storeId, ['store_read'], Response::HTTP_NOT_FOUND);
    }

    /**
     * @return int[]
     */
    public function testBulkUpdateStatusStores(): array
    {
        $storeIds = [$this->createFixtureStore(), $this->createFixtureStore()];

        $this->assertNull($this->updateItem('/stores/bulk-update-status', ['storeIds' => $storeIds, 'enabled' => false], ['store_write'], Response::HTTP_NO_CONTENT));
        foreach ($storeIds as $storeId) {
            $this->assertFalse($this->getItem('/stores/' . $storeId, ['store_read'])['enabled']);
        }

        return $storeIds;
    }

    /**
     * @depends testBulkUpdateStatusStores
     *
     * @param int[] $storeIds
     */
    public function testBulkDeleteStores(array $storeIds): void
    {
        $this->assertNull($this->bulkDeleteItems('/stores/bulk-delete', ['storeIds' => $storeIds], ['store_write']));
        foreach ($storeIds as $storeId) {
            $this->getItem('/stores/' . $storeId, ['store_read'], Response::HTTP_NOT_FOUND);
        }
    }

    public function testNotFoundStore(): void
    {
        $this->getItem('/stores/999999', ['store_read'], Response::HTTP_NOT_FOUND);
        $this->deleteItem('/stores/999999', ['store_write'], Response::HTTP_NOT_FOUND);
        $this->updateItem('/stores/999999/toggle-status', null, ['store_write'], Response::HTTP_NOT_FOUND);
        if (self::isVersionAtLeast(self::COMMANDS_VERSION)) {
            $this->partialUpdateItem('/stores/999999', ['city' => 'Nowhere'], ['store_write'], Response::HTTP_NOT_FOUND);
        }
    }

    public function testInvalidStoreId(): void
    {
        // The id passes the \d+ requirement and is refused by the value object: a mapped exception, no field list
        $this->getItem('/stores/0', ['store_read'], Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->deleteItem('/stores/0', ['store_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->updateItem('/stores/0/toggle-status', null, ['store_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
        if (self::isVersionAtLeast(self::COMMANDS_VERSION)) {
            $this->partialUpdateItem('/stores/0', ['city' => 'Nowhere'], ['store_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }

    public function testAddStoreWithInvalidData(): void
    {
        $this->markTestSkippedByMinVersion(self::COMMANDS_VERSION);

        // The optional fields get the defaults of the back office form before the validation
        $response = $this->createItem('/stores', ['city' => ''], ['store_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
        $notNull = ['message' => 'This value should not be null.'];
        $this->assertValidationErrors([
            ['propertyPath' => 'names', 'message' => 'The field names is required at least in your default language.'],
            ['propertyPath' => 'addresses', 'message' => 'The field addresses is required at least in your default language.'],
            ['propertyPath' => 'countryId'] + $notNull,
            ['propertyPath' => 'city', 'message' => 'This value should not be blank.'],
            ['propertyPath' => 'latitude'] + $notNull,
            ['propertyPath' => 'longitude'] + $notNull,
        ], $response);

        // A localized field without its default language
        $response = $this->createItem('/stores', $this->getCreatePayload(['names' => ['fr-FR' => 'Only FR']]), ['store_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertValidationErrors([
            ['propertyPath' => 'names', 'message' => 'The field names is required at least in your default language.'],
        ], $response);
    }

    public function testAddStoreWithInvalidValues(): void
    {
        $this->markTestSkippedByMinVersion(self::COMMANDS_VERSION);

        $response = $this->createItem('/stores', $this->getCreatePayload([
            'countryId' => -1,
            'stateId' => 0,
            'city' => str_repeat('a', 65),
            'postcode' => str_repeat('1', 13),
            'phone' => str_repeat('1', 17),
            'fax' => str_repeat('1', 17),
            'email' => 'not-an-email',
            'hours' => ['en-US' => ['09:00 | 18:00', '09:00 | 18:00', '09:00 | 18:00'], 'fr-FR' => 'not a list'],
            'shopIds' => [0],
        ]), ['store_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertValidationErrors([
            ['propertyPath' => 'countryId', 'message' => 'This value should be positive.'],
            ['propertyPath' => 'stateId', 'message' => 'This value should be positive.'],
            ['propertyPath' => 'city', 'message' => 'This value is too long. It should have 64 characters or less.'],
            ['propertyPath' => 'postcode', 'message' => 'This value is too long. It should have 12 characters or less.'],
            ['propertyPath' => 'phone', 'message' => 'This value is too long. It should have 16 characters or less.'],
            ['propertyPath' => 'fax', 'message' => 'This value is too long. It should have 16 characters or less.'],
            ['propertyPath' => 'email', 'message' => 'This value is not a valid email address.'],
            // The front office reads exactly 7 days per language
            ['propertyPath' => 'hours[en-US]', 'message' => 'This collection should contain exactly 7 elements.'],
            ['propertyPath' => 'hours[fr-FR]', 'message' => 'This value should be of type array.'],
            ['propertyPath' => 'shopIds[0]', 'message' => 'This value should be positive.'],
        ], $response);

        // Refused by the typed regex and clean HTML constraints: the message carries the value, only the status is asserted
        foreach ([
            ['names' => ['en-US' => 'Name with <tag>', 'fr-FR' => 'Nom avec <tag>']],
            ['addresses' => ['en-US' => 'Address with <tag>', 'fr-FR' => 'Adresse avec <tag>']],
            ['city' => 'City with <tag>'],
            ['postcode' => 'post code!'],
            ['phone' => 'phone number'],
            ['notes' => ['en-US' => '<script>alert(1)</script>', 'fr-FR' => '<script>alert(1)</script>']],
        ] as $overrides) {
            $this->createItem('/stores', $this->getCreatePayload($overrides), ['store_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // An empty shop association is refused
        $response = $this->createItem('/stores', $this->getCreatePayload(['shopIds' => []]), ['store_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertValidationErrors([
            ['propertyPath' => 'shopIds', 'message' => 'This value should not be blank.'],
        ], $response);
    }

    public function testAddStoreWithInconsistentState(): void
    {
        $this->markTestSkippedByMinVersion(self::COMMANDS_VERSION);

        $countryIdUS = $this->getCountryId('US');
        $stateIdFlorida = $this->getStateId('FL');

        // Refused by the core validator: a mapped constraint exception, no field list. An unknown country or state
        // answers the same 422 as an inconsistent one
        foreach ([
            ['countryId' => 999999],
            ['stateId' => $stateIdFlorida],
            ['countryId' => $countryIdUS],
            ['countryId' => $countryIdUS, 'stateId' => $this->getStateId('ON')],
            ['countryId' => $countryIdUS, 'stateId' => 999999],
        ] as $overrides) {
            $this->createItem('/stores', $this->getCreatePayload($overrides), ['store_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $store = $this->createItem('/stores', $this->getCreatePayload(['countryId' => $countryIdUS, 'stateId' => $stateIdFlorida]), ['store_write']);
        $this->assertEquals($this->expectedStore($store['storeId'], ['countryId' => $countryIdUS, 'stateId' => $stateIdFlorida]), $store);
        $this->deleteItem('/stores/' . $store['storeId'], ['store_write']);
    }

    public function testAddStoreWithoutShopIds(): void
    {
        $this->markTestSkippedByMinVersion(self::COMMANDS_VERSION);

        $payload = $this->getCreatePayload();
        unset($payload['shopIds']);

        // The shops of the context are the default
        $store = $this->createItem('/stores', $payload, ['store_write']);
        $this->assertEquals($this->expectedStore($store['storeId'], ['shopIds' => [1]]), $store);
        $this->deleteItem('/stores/' . $store['storeId'], ['store_write']);
    }

    public function testAddStoreWithOutOfRangeCoordinates(): void
    {
        $this->markTestSkippedByMinVersion(self::COMMANDS_VERSION);

        // The core commands do not check the bounds of the coordinates yet: a value of 5 digits or more before the
        // decimal point exceeds the 13 characters of the legacy column once stored with 8 decimals, a raw legacy
        // exception the API does not map. A range check in the core commands would answer 422
        // (StoreConstraintException) and refuse 200 as well
        $this->createItem('/stores', $this->getCreatePayload(['latitude' => 10000]), ['store_write'], Response::HTTP_INTERNAL_SERVER_ERROR);
        $this->createItem('/stores', $this->getCreatePayload(['longitude' => -10000]), ['store_write'], Response::HTTP_INTERNAL_SERVER_ERROR);
    }

    public function testEditStoreWithInvalidValues(): void
    {
        $this->markTestSkippedByMinVersion(self::COMMANDS_VERSION);

        $storeId = $this->createFixtureStore();

        $response = $this->partialUpdateItem('/stores/' . $storeId, [
            'countryId' => 0,
            'city' => '',
            'email' => 'not-an-email',
            'shopIds' => [],
        ], ['store_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertValidationErrors([
            ['propertyPath' => 'countryId', 'message' => 'This value should be positive.'],
            ['propertyPath' => 'city', 'message' => 'This value should not be blank.'],
            ['propertyPath' => 'email', 'message' => 'This value is not a valid email address.'],
            ['propertyPath' => 'shopIds', 'message' => 'This value should not be blank.'],
        ], $response);

        // A localized field patched without its default language
        $this->partialUpdateItem('/stores/' . $storeId, ['names' => ['fr-FR' => 'Only FR']], ['store_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->partialUpdateItem('/stores/' . $storeId, ['addresses' => ['fr-FR' => 'Only FR']], ['store_write'], Response::HTTP_UNPROCESSABLE_ENTITY);

        $this->deleteItem('/stores/' . $storeId, ['store_write']);
    }

    /**
     * The fixture goes through the API from PrestaShop 9.3.0; the older cores have no create command, the legacy
     * model stands in until 9.3.0 is the oldest supported core.
     */
    private function createFixtureStore(): int
    {
        if (self::isVersionAtLeast(self::COMMANDS_VERSION)) {
            return $this->createItem('/stores', $this->getCreatePayload(), ['store_write'])['storeId'];
        }

        $payload = $this->getCreatePayload();
        $store = new \Store();
        $store->name = [1 => $payload['names']['en-US']];
        $store->address1 = [1 => $payload['addresses']['en-US']];
        $store->postcode = $payload['postcode'];
        $store->city = $payload['city'];
        $store->latitude = (string) $payload['latitude'];
        $store->longitude = (string) $payload['longitude'];
        $store->phone = $payload['phone'];
        $store->email = $payload['email'];
        $store->active = $payload['enabled'];
        $store->id_country = $payload['countryId'];
        $store->save();

        return (int) $store->id;
    }

    /**
     * The payload of the fixture, the expected payloads are built from it.
     *
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    private function getCreatePayload(array $overrides = []): array
    {
        return array_replace([
            'names' => [
                'en-US' => 'My store EN',
                'fr-FR' => 'My store FR',
            ],
            'addresses' => [
                'en-US' => '1 store street EN',
                'fr-FR' => '1 store street FR',
            ],
            'secondaryAddresses' => [
                'en-US' => 'Floor 1 EN',
                'fr-FR' => 'Floor 1 FR',
            ],
            'hours' => [
                'en-US' => ['09:00 | 18:00', '09:00 | 18:00', '09:00 | 18:00', '09:00 | 18:00', '09:00 | 18:00', '10:00 | 16:00', ''],
                'fr-FR' => ['09:00 | 18:00', '09:00 | 18:00', '09:00 | 18:00', '09:00 | 18:00', '09:00 | 18:00', '10:00 | 16:00', ''],
            ],
            'notes' => [
                'en-US' => 'Free parking',
                'fr-FR' => 'Parking gratuit',
            ],
            'countryId' => $this->getCountryId('FR'),
            'stateId' => null,
            'city' => 'Rinxent',
            'postcode' => '62720',
            'latitude' => 50.8046176,
            'longitude' => 1.73905362,
            'phone' => '0321000000',
            'fax' => null,
            'email' => 'store@example.com',
            'enabled' => true,
            'shopIds' => [1],
        ], $overrides);
    }

    /**
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    private function expectedStore(int $storeId, array $overrides = []): array
    {
        $expected = array_replace(['storeId' => $storeId] + $this->getCreatePayload() + ['imageUrl' => null], $overrides);
        if (self::isVersionUnder(self::COMMANDS_VERSION)) {
            // Before PrestaShop 9.3.0 the core query only returns the id and the status, the picture stays null
            return ['storeId' => $expected['storeId'], 'enabled' => $expected['enabled'], 'imageUrl' => null];
        }

        return $expected;
    }

    private function getCountryId(string $isoCode): int
    {
        return (int) \Db::getInstance()->getValue('SELECT id_country FROM `' . _DB_PREFIX_ . 'country` WHERE iso_code = "' . pSQL($isoCode) . '"');
    }

    private function getStateId(string $isoCode): int
    {
        return (int) \Db::getInstance()->getValue('SELECT id_state FROM `' . _DB_PREFIX_ . 'state` WHERE iso_code = "' . pSQL($isoCode) . '"');
    }
}
