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

class ManufacturerEndpointTest extends ApiTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        // Add the fr-FR language to test multi lang values accurately
        LanguageResetter::resetLanguages();
        self::addLanguageByLocale('fr-FR');
        self::resetTables();
        // Pre-create the API Client with the needed scopes, this way we reduce the number of created API Clients
        self::createApiClient(['manufacturer_write', 'manufacturer_read']);
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        // Reset DB as it was before this test
        LanguageResetter::resetLanguages();
        self::resetTables();
    }

    protected static function resetTables(): void
    {
        DatabaseDump::restoreTables([
            'manufacturer',
            'manufacturer_lang',
            'manufacturer_shop',
        ]);
    }

    public function getProtectedEndpoints(): iterable
    {
        yield 'get endpoint' => [
            'GET',
            '/manufacturer/1',
        ];

        yield 'get details endpoint' => [
            'GET',
            '/manufacturer/1/details/1',
        ];

        yield 'create endpoint' => [
            'POST',
            '/manufacturer',
        ];

        yield 'patch endpoint' => [
            'PATCH',
            '/manufacturer/1',
        ];

        yield 'bulk delete endpoint' => [
            'PUT',
            '/manufacturers/delete',
        ];

        yield 'delete endpoint' => [
            'DELETE',
            '/manufacturer/1',
        ];
    }

    public function testAddManufacturer(): int
    {
        $itemsCount = $this->countItems('/manufacturers', ['manufacturer_read']);
        $postData = [
            'name' => 'manufacturer name',
            'shortDescriptions' => [
                'en-US' => 'short description en',
                'fr-FR' => 'short description fr',
            ],
            'descriptions' => [
                'en-US' => 'description en',
                'fr-FR' => 'description fr',
            ],
            'metaTitles' => [
                'en-US' => 'meta title en',
                'fr-FR' => 'meta title fr',
            ],
            'metaDescriptions' => [
                'en-US' => 'meta description en',
                'fr-FR' => 'meta description fr',
            ],
            'shopIds' => [1],
            'enabled' => true,
        ];
        // Create an manufacturer, the POST endpoint returns the created item as JSON
        $manufacturer = $this->createItem('/manufacturer', $postData, ['manufacturer_write']);
        $this->assertArrayHasKey('manufacturerId', $manufacturer);
        $manufacturerId = $manufacturer['manufacturerId'];

        // We assert the returned data matches what was posted (plus the ID)
        $this->assertEquals(
            ['manufacturerId' => $manufacturerId] + $postData,
            $manufacturer
        );

        $newItemsCount = $this->countItems('/manufacturers', ['manufacturer_read']);
        $this->assertEquals($itemsCount + 1, $newItemsCount);

        return $manufacturerId;
    }

    /**
     * @depends testAddManufacturer
     *
     * @param int $manufacturerId
     *
     * @return int
     */
    public function testGetManufacturer(int $manufacturerId): int
    {
        $manufacturer = $this->getItem('/manufacturer/' . $manufacturerId, ['manufacturer_read']);
        $this->assertEquals([
            'manufacturerId' => $manufacturerId,
            'name' => 'manufacturer name',
            'shortDescriptions' => [
                'en-US' => 'short description en',
                'fr-FR' => 'short description fr',
            ],
            'descriptions' => [
                'en-US' => 'description en',
                'fr-FR' => 'description fr',
            ],
            'metaTitles' => [
                'en-US' => 'meta title en',
                'fr-FR' => 'meta title fr',
            ],
            'metaDescriptions' => [
                'en-US' => 'meta description en',
                'fr-FR' => 'meta description fr',
            ],
            'shopIds' => [1],
            'enabled' => true,
        ], $manufacturer);

        return $manufacturerId;
    }

    /**
     * @depends testGetManufacturer
     *
     * @param int $manufacturerId
     *
     * @return int
     */
    public function testGetManufacturerDetails(int $manufacturerId): int
    {
        $manufacturer = $this->getItem('/manufacturer/' . $manufacturerId . '/details/1', ['manufacturer_read']);

        $this->assertEquals([
            'name' => 'manufacturer name',
            'products' => [],
            'addresses' => [],
        ], $manufacturer);

        return $manufacturerId;
    }

    /**
     * @depends testGetManufacturer
     *
     * @param int $manufacturerId
     *
     * @return int
     */
    public function testUpdatePartialManufacturer(int $manufacturerId): int
    {
        $patchData = [
            'name' => 'updated manufacturer',
            'enabled' => true,
            'shortDescriptions' => [
                'en-US' => 'updated short desc en',
                'fr-FR' => 'updated short desc fr',
            ],
            'descriptions' => [
                'en-US' => 'updated description en',
                'fr-FR' => 'updated description fr',
            ],
            'metaTitles' => [
                'en-US' => 'updated meta title en',
                'fr-FR' => 'updated meta title en',
            ],
            'metaDescriptions' => [
                'en-US' => 'updated meta description en',
                'fr-FR' => 'updated meta description fr',
            ],
            'shopIds' => [1],
        ];

        $updatedManufacturer = $this->partialUpdateItem('/manufacturer/' . $manufacturerId, $patchData, ['manufacturer_write']);
        $this->assertEquals(['manufacturerId' => $manufacturerId] + $patchData, $updatedManufacturer);

        // We check that when we GET the item it is updated as expected
        $manufacturer = $this->getItem('/manufacturer/' . $manufacturerId, ['manufacturer_read']);
        $this->assertEquals(['manufacturerId' => $manufacturerId] + $patchData, $manufacturer);

        // Test partial update
        $partialUpdateData = [
            'name' => 'updated manufacturer name',
            'enabled' => false,
            'shortDescriptions' => [
                'en-US' => 'updated short description en',
                'fr-FR' => 'updated short description fr',
            ],
        ];
        $expectedUpdatedData = [
            'manufacturerId' => $manufacturerId,
            'name' => 'updated manufacturer name',
            'enabled' => false,
            'shortDescriptions' => [
                'en-US' => 'updated short description en',
                'fr-FR' => 'updated short description fr',
            ],
            'descriptions' => [
                'en-US' => 'updated description en',
                'fr-FR' => 'updated description fr',
            ],
            'metaTitles' => [
                'en-US' => 'updated meta title en',
                'fr-FR' => 'updated meta title en',
            ],
            'metaDescriptions' => [
                'en-US' => 'updated meta description en',
                'fr-FR' => 'updated meta description fr',
            ],
            'shopIds' => [1],
        ];

        $updatedManufacturer = $this->partialUpdateItem('/manufacturer/' . $manufacturerId, $partialUpdateData, ['manufacturer_write']);
        $this->assertEquals($expectedUpdatedData, $updatedManufacturer);

        return $manufacturerId;
    }

    /**
     * @depends testUpdatePartialManufacturer
     *
     * @param int $manufacturerId
     *
     * @return int
     */
    public function testListManufacturers(int $manufacturerId): int
    {
        // List by manufacturerId in descending order so the created one comes first (and test ordering at the same time)
        foreach (['manufacturerId', 'active', 'products_count', 'addresses_count', 'name'] as $orderBy) {
            $paginatedManufacturers = $this->listItems('/manufacturers?orderBy=' . $orderBy . '&sortOrder=desc', ['manufacturer_read']);
            $this->assertGreaterThanOrEqual(1, $paginatedManufacturers['totalItems']);

            // Check the details to make sure filters mapping is correct
            $this->assertEquals($orderBy, $paginatedManufacturers['orderBy']);
        }

        // Test manufacturer should be the first returned in the list
        $testManufacturer = $paginatedManufacturers['items'][0];

        $expectedManufacturer = [
            'manufacturerId' => $manufacturerId,
            'name' => 'updated manufacturer name',
            'addressesCount' => '--',
            'enabled' => false,
        ];
        $this->assertEquals($expectedManufacturer, $testManufacturer);

        $filteredManufacturers = $this->listItems('/manufacturers', ['manufacturer_read'], [
            'manufacturerId' => $manufacturerId,
        ]);
        $this->assertEquals(1, $filteredManufacturers['totalItems']);

        $testManufacturer = $filteredManufacturers['items'][0];
        $this->assertEquals($expectedManufacturer, $testManufacturer);

        // Check the filters details
        $this->assertEquals([
            'manufacturerId' => $manufacturerId,
        ], $filteredManufacturers['filters']);

        return $manufacturerId;
    }

    /**
     * @depends testListManufacturers
     *
     * @param int $manufacturerId
     */
    public function testRemoveManufacturer(int $manufacturerId): void
    {
        // Delete the item
        $return = $this->deleteItem('/manufacturer/' . $manufacturerId, ['manufacturer_write']);
        // This endpoint return empty response and 204 HTTP code
        $this->assertNull($return);

        // Getting the item should result in a 404 now
        $this->getItem('/manufacturer/' . $manufacturerId, ['manufacturer_read'], Response::HTTP_NOT_FOUND);
    }

    public function testBulkRemoveManufacturers(): void
    {
        $manufacturers = $this->listItems('/manufacturers', ['manufacturer_read']);

        // There are four manufacturers in default fixtures
        $this->assertEquals(3, $manufacturers['totalItems']);

        // We remove the first two manufacturers
        $removeManufacturerIds = [
            $manufacturers['items'][0]['manufacturerId'],
            $manufacturers['items'][2]['manufacturerId'],
        ];

        $this->updateItem('/manufacturers/delete', [
            'manufacturerIds' => $removeManufacturerIds,
        ], ['manufacturer_write'], Response::HTTP_NO_CONTENT);

        // Assert the provided manufacturers have been removed
        foreach ($removeManufacturerIds as $manufacturerId) {
            $this->getItem('/manufacturer/' . $manufacturerId, ['manufacturer_read'], Response::HTTP_NOT_FOUND);
        }

        // Only two manufacturer remain
        $this->assertEquals(1, $this->countItems('/manufacturers', ['manufacturer_read']));
    }

    public function testInvalidFilterManufacturers()
    {
        $orderByInvalid = 'INVALID_FILTER';

        $this->requestApi('GET', '/manufacturers?orderBy=' . $orderByInvalid . '&sortOrder=desc', null, ['manufacturer_read'], Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testInvalidManufacturer(): void
    {
        $manufacturer = $this->getItem('/manufacturer/9999', ['manufacturer_read'], Response::HTTP_NOT_FOUND);

        $manufacturerInvalidData = [
            'name' => 'updated manufacturer name',
            'enabled' => false,
            'shortDescriptions' => [
                // <script> is not valid
                'en-US' => 'updated short description en<script>',
                'fr-FR' => 'updated short description fr',
            ],
            'descriptions' => [
                'en-US' => 'updated description en',
                'fr-FR' => 'updated description fr',
            ],
            'metaTitles' => [
                'en-US' => 'updated meta title en',
                'fr-FR' => 'updated meta title en',
            ],
            'metaDescriptions' => [
                'en-US' => 'updated meta description en',
                'fr-FR' => 'updated meta description fr',
            ],
            'shopIds' => [1],
        ];

        // Creating with invalid data should return a response with invalid constraint messages and use an http code 422
        $validationErrorsResponse = $this->createItem('/manufacturer', $manufacturerInvalidData, ['manufacturer_write'], Response::HTTP_UNPROCESSABLE_ENTITY);

        $this->assertIsArray($validationErrorsResponse);
        $this->assertEquals($validationErrorsResponse['detail'], 'Failed to add new manufacturer "updated manufacturer name"');
        $manufacturerData = $manufacturerInvalidData;
        $manufacturerData['shortDescriptions']['en-US'] = 'updated short description en';

        // Now create a valid manufacturer to test the validation on PATCH request
        $validManufacturer = $this->createItem('/manufacturer', $manufacturerData, ['manufacturer_write']);
        $manufacturerId = $validManufacturer['manufacturerId'];
        // <script> is not valid
        $manufacturerData['shortDescriptions']['en-US'] = 'updated short description en<script>';

        $validationErrorsResponse = $this->partialUpdateItem('/manufacturer/' . $manufacturerId, $manufacturerData, ['manufacturer_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}
