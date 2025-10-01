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

class TaxEndpointTest extends ApiTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        // Add the fr-FR language to test multi lang values accurately
        LanguageResetter::resetLanguages();
        self::addLanguageByLocale('fr-FR');
        self::resetTables();
        self::createApiClient(['tax_write', 'tax_read']);
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
        DatabaseDump::restoreTables(['tax', 'tax_lang']);
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'create endpoint' => [
            'POST',
            '/taxes',
        ];

        yield 'get endpoint' => [
            'GET',
            '/taxes/1',
        ];

        yield 'update endpoint' => [
            'PATCH',
            '/taxes/1',
        ];

        yield 'delete endpoint' => [
            'DELETE',
            '/taxes/1',
        ];

        yield 'list endpoint' => [
            'GET',
            '/taxes',
        ];

        yield 'bulk delete endpoint' => [
            'PUT',
            '/taxes/bulk-delete',
        ];

        yield 'bulk toggle status endpoint' => [
            'PUT',
            '/taxes/bulk-set-status',
        ];
    }

    public function testAddTax(): int
    {
        $taxes = $this->listItems('/taxes', ['tax_read']);
        $itemsCount = $this->countItems('/taxes', ['tax_read']);

        $tax = $this->createItem('/taxes', [
            'names' => [
                'en-US' => 'My Tax EN',
                'fr-FR' => 'My Tax FR',
            ],
            'enabled' => false,
            'rate' => 1.23,
        ], ['tax_write']);
        $this->assertArrayHasKey('taxId', $tax);
        $taxId = $tax['taxId'];
        $this->assertEquals(
            [
                'taxId' => $taxId,
            ],
            $tax
        );

        $newItemsCount = $this->countItems('/taxes', ['tax_read']);
        $this->assertEquals($itemsCount + 1, $newItemsCount);

        return $taxId;
    }

    /**
     * @depends testAddTax
     *
     * @param int $taxId
     *
     * @return int
     */
    public function testGetTax(int $taxId): int
    {
        $tax = $this->getItem('/taxes/' . $taxId, ['tax_read']);
        $this->assertEquals(
            [
                'taxId' => $taxId,
                'names' => [
                    'en-US' => 'My Tax EN',
                    'fr-FR' => 'My Tax FR',
                ],
                'enabled' => false,
                'rate' => 1.23,
            ],
            $tax
        );

        return $taxId;
    }

    /**
     * @depends testGetTax
     *
     * @param int $taxId
     *
     * @return int
     */
    public function testUpdateTax(int $taxId): int
    {
        $updatedTax = $this->partialUpdateItem('/taxes/' . $taxId, [
            'names' => [
                'en-US' => 'My Tax Updated EN',
                'fr-FR' => 'My Tax Updated FR',
            ],
        ], ['tax_write']);
        $this->assertEquals(
            [
                'taxId' => $taxId,
                'names' => [
                    'en-US' => 'My Tax Updated EN',
                    'fr-FR' => 'My Tax Updated FR',
                ],
                'enabled' => false,
                'rate' => 1.23,
            ],
            $updatedTax
        );

        $updatedTax = $this->partialUpdateItem('/taxes/' . $taxId, [
            'enabled' => true,
        ], ['tax_write']);
        $this->assertEquals(
            [
                'taxId' => $taxId,
                'names' => [
                    'en-US' => 'My Tax Updated EN',
                    'fr-FR' => 'My Tax Updated FR',
                ],
                'enabled' => true,
                'rate' => 1.23,
            ],
            $updatedTax
        );

        $updatedTax = $this->partialUpdateItem('/taxes/' . $taxId, [
            'rate' => 9.87,
        ], ['tax_write']);
        $this->assertEquals(
            [
                'taxId' => $taxId,
                'names' => [
                    'en-US' => 'My Tax Updated EN',
                    'fr-FR' => 'My Tax Updated FR',
                ],
                'enabled' => true,
                'rate' => 9.87,
            ],
            $updatedTax
        );

        return $taxId;
    }

    /**
     * @depends testUpdateTax
     *
     * @param int $taxId
     *
     * @return int
     */
    public function testGetUpdatedTax(int $taxId): int
    {
        $tax = $this->getItem('/taxes/' . $taxId, ['tax_read']);
        $this->assertEquals(
            [
                'taxId' => $taxId,
                'names' => [
                    'en-US' => 'My Tax Updated EN',
                    'fr-FR' => 'My Tax Updated FR',
                ],
                'enabled' => true,
                'rate' => 9.87,
            ],
            $tax
        );

        return $taxId;
    }

    /**
     * @depends testGetUpdatedTax
     *
     * @param int $taxId
     *
     * @return int
     */
    public function testListTaxs(int $taxId): int
    {
        $taxes = $this->listItems('/taxes', ['tax_read']);
        $this->assertCount(50, $taxes['items']);
        $this->assertEquals(53, $taxes['totalItems']);

        $taxes = $this->listItems('/taxes?limit=100', ['tax_read']);
        $this->assertGreaterThanOrEqual(1, $taxes['totalItems']);

        // Search for the one created previously during the tests and assert its data in the list
        $testTax = null;
        foreach ($taxes['items'] as $tax) {
            if ($tax['taxId'] === $taxId) {
                $testTax = $tax;
            }
        }
        $this->assertNotNull($testTax);
        $this->assertEquals(
            [
                'taxId' => $taxId,
                'name' => 'My Tax Updated EN',
                'enabled' => true,
                'rate' => 9.87,
            ],
            $testTax
        );

        return $taxId;
    }

    /**
     * @depends testListTaxs
     *
     * @param int $taxId
     *
     * @return int
     */
    public function testDeleteTax(int $taxId): void
    {
        $return = $this->deleteItem('/taxes/' . $taxId, ['tax_write']);
        // This endpoint return empty response and 204 HTTP code
        $this->assertNull($return);

        // Getting the item should result in a 404 now
        $this->getItem('/taxes/' . $taxId, ['tax_read'], Response::HTTP_NOT_FOUND);
    }

    /**
     * @depends testDeleteTax
     *
     * @param int $taxId
     *
     * @return int
     */
    public function testBulkDeleteSetStatus(): void
    {
        $taxes = $this->listItems('/taxes', ['tax_read']);

        // There are taxes in default fixtures
        $this->assertEquals(52, $taxes['totalItems']);

        // We update the two taxes
        $bulkTaxs = [
            $taxes['items'][2]['taxId'],
            $taxes['items'][3]['taxId'],
        ];
        foreach ($bulkTaxs as $taxId) {
            $tax = $this->getItem('/taxes/' . $taxId, ['tax_read']);

            $this->assertEquals(true, $tax['enabled']);
        }

        $this->updateItem('/taxes/bulk-set-status', [
            'taxIds' => $bulkTaxs,
            'enabled' => false,
        ], ['tax_write'], Response::HTTP_NO_CONTENT);

        // Assert the provided taxes have been removed
        foreach ($bulkTaxs as $taxId) {
            $tax = $this->getItem('/taxes/' . $taxId, ['tax_read']);

            $this->assertEquals(false, $tax['enabled']);
        }

        $this->assertEquals(52, $this->countItems('/taxes', ['tax_read']));
    }

    /**
     * @depends testDeleteTax
     */
    public function testBulkDeleteTaxs(): void
    {
        $taxes = $this->listItems('/taxes', ['tax_read']);

        // There are taxes in default fixtures
        $this->assertEquals(52, $taxes['totalItems']);

        // We remove the two taxes
        $bulkTaxs = [
            $taxes['items'][2]['taxId'],
            $taxes['items'][3]['taxId'],
        ];

        $this->updateItem('/taxes/bulk-delete', [
            'taxIds' => $bulkTaxs,
        ], ['tax_write'], Response::HTTP_NO_CONTENT);

        // Assert the provided taxes have been removed
        foreach ($bulkTaxs as $taxId) {
            $this->getItem('/taxes/' . $taxId, ['tax_read'], Response::HTTP_NOT_FOUND);
        }

        $this->assertEquals(50, $this->countItems('/taxes', ['tax_read']));
    }

    public function testCreateInvalidTax(): void
    {
        // Creating with invalid data should return a response with invalid constraint messages and use an http code 422
        $validationErrorsResponse = $this->createItem('/taxes', [
            'name' => '',
        ], ['tax_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertIsArray($validationErrorsResponse);
        $this->assertValidationErrors([
            [
                'propertyPath' => 'rate',
                'message' => 'This value should not be null.',
            ],
            [
                'propertyPath' => 'enabled',
                'message' => 'This value should not be null.',
            ],
        ], $validationErrorsResponse);
    }
}
