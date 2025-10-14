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

namespace PsApiResourcesTest\Integration\ApiPlatform;

use Symfony\Component\HttpFoundation\Response;
use Tests\Resources\DatabaseDump;
use Tests\Resources\Resetter\LanguageResetter;

class AttributeEndpointTest extends ApiTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        // Add the fr-FR language to test multi lang values accurately
        LanguageResetter::resetLanguages();
        self::addLanguageByLocale('fr-FR');
        self::resetTables();
        // Pre-create the API Client with the needed scopes, this way we reduce the number of created API Clients
        self::createApiClient(['attribute_write', 'attribute_read']);
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
            'attribute',
            'attribute_lang',
            'attribute_shop',
        ]);
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'get endpoint' => [
            'GET',
            '/attributes/attribute/1',
        ];

        yield 'create endpoint' => [
            'POST',
            '/attributes/attribute',
        ];

        yield 'patch endpoint' => [
            'PATCH',
            '/attributes/attribute/1',
        ];

        yield 'delete endpoint' => [
            'DELETE',
            '/attributes/attribute/1',
        ];

        yield 'list endpoint' => [
            'GET',
            '/attributes/group/1/attributes',
        ];

        yield 'bulk delete endpoint' => [
            'PUT',
            '/attributes/attributes/delete',
        ];
    }

    public function testAddAttribute(): int
    {
        $itemsCount = $this->countItems('/attributes/group/1/attributes', ['attribute_read']);

        $postData = [
            'names' => [
                'en-US' => 'name en',
                'fr-FR' => 'name fr',
            ],
            'attributeGroupId' => 1,
            'color' => '',
            'shopIds' => [1],
        ];

        // Create an attribute, the POST endpoint returns the created item as JSON
        $attribute = $this->createItem('/attributes/attribute', $postData, ['attribute_write']);
        $this->assertArrayHasKey('attributeId', $attribute);
        $attributeId = $attribute['attributeId'];

        // We assert the returned data matches what was posted (plus the ID)
        $this->assertEquals(
            ['attributeId' => $attributeId] + $postData,
            $attribute
        );

        $newItemsCount = $this->countItems('/attributes/group/1/attributes', ['attribute_read']);
        $this->assertEquals($itemsCount + 1, $newItemsCount);

        return $attributeId;
    }

    /**
     * @depends testAddAttribute
     *
     * @param int $attributeId
     *
     * @return int
     */
    public function testGetAttribute(int $attributeId): int
    {
        $attribute = $this->getItem('/attributes/attribute/' . $attributeId, ['attribute_read']);
        $this->assertEquals([
            'attributeId' => $attributeId,
            'names' => [
                'en-US' => 'name en',
                'fr-FR' => 'name fr',
            ],
            'shopIds' => [1],
            'attributeGroupId' => 1,
            'color' => '',
        ], $attribute);

        return $attributeId;
    }

    /**
     * @depends testGetAttribute
     *
     * @param int $attributeId
     *
     * @return int
     */
    public function testPartialUpdateAttribute(int $attributeId): int
    {
        $patchData = [
            'names' => [
                'en-US' => 'updated name en',
                'fr-FR' => 'updated name fr',
            ],
            'shopIds' => [1],
            'attributeGroupId' => 1,
            'color' => '',
        ];

        $updatedAttribute = $this->partialUpdateItem('/attributes/attribute/' . $attributeId, $patchData, ['attribute_write']);
        $this->assertEquals(['attributeId' => $attributeId] + $patchData, $updatedAttribute);

        // We check that when we GET the item it is updated as expected
        $attribute = $this->getItem('/attributes/attribute/' . $attributeId, ['attribute_read']);
        $this->assertEquals(['attributeId' => $attributeId] + $patchData, $attribute);

        // Test partial update
        $partialUpdateData = [
            'names' => [
                'fr-FR' => 'updated nom fr',
            ],
        ];
        $expectedUpdatedData = [
            'attributeId' => $attributeId,
            'names' => [
                'en-US' => 'updated name en',
                'fr-FR' => 'updated nom fr',
            ],
            'attributeGroupId' => 1,
            'shopIds' => [1],
            'color' => '',
        ];
        $updatedAttribute = $this->partialUpdateItem('/attributes/attribute/' . $attributeId, $partialUpdateData, ['attribute_write']);
        $this->assertEquals($expectedUpdatedData, $updatedAttribute);

        return $attributeId;
    }

    /**
     * @depends testPartialUpdateAttribute
     *
     * @param int $attributeId
     *
     * @return int
     */
    public function testListAttributes(int $attributeId): int
    {
        // List by attributeId in descending order so the created one comes first (and test ordering at the same time)
        $paginatedAttributes = $this->listItems('/attributes/group/1/attributes?orderBy=attributeId&sortOrder=desc', ['attribute_read']);
        $this->assertGreaterThanOrEqual(1, $paginatedAttributes['totalItems']);

        // Check the details to make sure filters mapping is correct
        $this->assertEquals('attributeId', $paginatedAttributes['orderBy']);

        // Test attribute should be the first returned in the list
        $testAttribute = $paginatedAttributes['items'][0];

        // Position should be at least 3 since there are three attribute in the default fixtures data
        $this->assertGreaterThanOrEqual(3, $testAttribute['position']);
        $position = $testAttribute['position'];
        $expectedAttribute = [
            'attributeId' => $attributeId,
            'name' => 'updated name en',
            'position' => $position,
        ];
        $this->assertEquals($expectedAttribute, $testAttribute);

        $filteredAttributes = $this->listItems('/attributes/group/1/attributes', ['attribute_read'], [
            'attributeId' => $attributeId,
        ]);
        $this->assertEquals(1, $filteredAttributes['totalItems']);

        $testAttribute = $filteredAttributes['items'][0];
        $this->assertEquals($expectedAttribute, $testAttribute);

        // Check the filters details
        $this->assertEquals([
            'attributeId' => $attributeId,
        ], $filteredAttributes['filters']);

        return $attributeId;
    }

    /**
     * @depends testListAttributes
     *
     * @param int $attributeId
     */
    public function testRemoveAttribute(int $attributeId): void
    {
        // Delete the item
        $return = $this->deleteItem('/attributes/attribute/' . $attributeId, ['attribute_write']);
        // This endpoint return empty response and 204 HTTP code
        $this->assertNull($return);

        // Getting the item should result in a 404 now
        $this->getItem('/attributes/attribute/' . $attributeId, ['attribute_read'], Response::HTTP_NOT_FOUND);
    }

    /**
     * @depends testRemoveAttribute
     */
    public function testBulkRemoveAttributes(): void
    {
        $attributes = $this->listItems('/attributes/group/1/attributes', ['attribute_read']);

        // There are four attributes in group with id 1
        $this->assertEquals(4, $attributes['totalItems']);

        // We remove the first two attributes
        $removeAttributeIds = [
            $attributes['items'][0]['attributeId'],
            $attributes['items'][2]['attributeId'],
        ];

        $this->updateItem('/attributes/attributes/delete', [
            'attributeIds' => $removeAttributeIds,
        ], ['attribute_write'], Response::HTTP_NO_CONTENT);

        // Assert the provided attributes have been removed
        foreach ($removeAttributeIds as $attributeId) {
            $this->getItem('/attributes/attribute/' . $attributeId, ['attribute_read'], Response::HTTP_NOT_FOUND);
        }

        // Only two attribute remain
        $this->assertEquals(2, $this->countItems('/attributes/group/1/attributes', ['attribute_read']));
    }

    public function testInvalidAttribute(): void
    {
        $attributeInvalidData = [
            'names' => [
                // en-US (default language) value is missing
                // < character is forbidden
                'fr-FR' => 'name fr<',
            ],
            'attributeGroupId' => 1,
            'color' => '',
            // ShopId must not be empty
            'shopIds' => [],
        ];

        // Creating with invalid data should return a response with invalid constraint messages and use an http code 422
        $validationErrorsResponse = $this->createItem('/attributes/attribute', $attributeInvalidData, ['attribute_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertIsArray($validationErrorsResponse);
        $this->assertValidationErrors([
            [
                'propertyPath' => 'names',
                'message' => 'The field names is required at least in your default language.',
            ],
            [
                'propertyPath' => 'names[fr-FR]',
                'message' => '"name fr<" is invalid',
            ],
            [
                'propertyPath' => 'shopIds',
                'message' => 'This value should not be blank.',
            ],
        ], $validationErrorsResponse);

        // Now create a valid attribute to test the validation on PATCH request
        $validAttribute = $this->createItem('/attributes/attribute', [
            'names' => [
                'en-US' => 'name en',
                'fr-FR' => 'name fr',
            ],
            'publicNames' => [
                'en-US' => 'name en',
                'fr-FR' => 'name fr',
            ],
            'attributeGroupId' => 1,
            'color' => '',
            'shopIds' => [1],
        ], ['attribute_write']);

        $attributeId = $validAttribute['attributeId'];
        $invalidUpdateData = [
            // Only the provided data is validated (we only get one invalid error)
            [
                'data' => [
                    'names' => [
                        'en-US' => 'name en<',
                    ],
                ],
                'expectedErrors' => [
                    [
                        'propertyPath' => 'names[en-US]',
                        'message' => '"name en<" is invalid',
                    ],
                ],
            ],
            // We can partially update only one language, the DefaultLanguage constraint doesn't block because en-US is not specified
            [
                'data' => [
                    'names' => [
                        'fr-FR' => 'name fr<',
                    ],
                ],
                'expectedErrors' => [
                    [
                        'propertyPath' => 'names[fr-FR]',
                        'message' => '"name fr<" is invalid',
                    ],
                ],
            ],
            // However trying to force empty value is forbidden
            [
                'data' => [
                    'names' => [
                        'en-US' => '',
                    ],
                ],
                'expectedErrors' => [
                    [
                        'propertyPath' => 'names',
                        'message' => 'The field names is required at least in your default language.',
                    ],
                ],
            ],
            [
                'data' => [
                    'shopIds' => [
                    ],
                ],
                'expectedErrors' => [
                    [
                        'propertyPath' => 'shopIds',
                        'message' => 'This value should not be blank.',
                    ],
                ],
            ],
        ];
        foreach ($invalidUpdateData as $updateData) {
            $validationErrorsResponse = $this->partialUpdateItem('/attributes/attribute/' . $attributeId, $updateData['data'], ['attribute_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
            $this->assertValidationErrors($updateData['expectedErrors'], $validationErrorsResponse);
        }
    }
}
