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

class AttributeGroupEndpointTest extends ApiTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        // Add the fr-FR language to test multi lang values accurately
        LanguageResetter::resetLanguages();
        self::addLanguageByLocale('fr-FR');
        self::resetTables();
        // Pre-create the API Client with the needed scopes, this way we reduce the number of created API Clients
        self::createApiClient(['attribute_group_write', 'attribute_group_read']);
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
            'attribute_group',
            'attribute_group_lang',
            'attribute_group_shop',
        ]);
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'get endpoint' => [
            'GET',
            '/attributes/group/1',
        ];

        yield 'create endpoint' => [
            'POST',
            '/attributes/group',
        ];

        yield 'patch endpoint' => [
            'PATCH',
            '/attributes/group/1',
        ];

        yield 'delete endpoint' => [
            'DELETE',
            '/attributes/group/1',
        ];

        yield 'list endpoint' => [
            'GET',
            '/attributes/groups',
        ];

        yield 'bulk delete endpoint' => [
            'PUT',
            '/attributes/groups/delete',
        ];
    }

    public function testAddAttributeGroup(): int
    {
        $itemsCount = $this->countItems('/attributes/groups', ['attribute_group_read']);

        $postData = [
            'names' => [
                'en-US' => 'name en',
                'fr-FR' => 'name fr',
            ],
            'publicNames' => [
                'en-US' => 'public name en',
                'fr-FR' => 'public name fr',
            ],
            'type' => 'select',
            'shopIds' => [1],
        ];

        // Create an attribute group, the POST endpoint returns the created item as JSON
        $attributeGroup = $this->createItem('/attributes/group', $postData, ['attribute_group_write']);
        $this->assertArrayHasKey('attributeGroupId', $attributeGroup);
        $attributeGroupId = $attributeGroup['attributeGroupId'];

        // We assert the returned data matches what was posted (plus the ID)
        $this->assertEquals(
            ['attributeGroupId' => $attributeGroupId] + $postData,
            $attributeGroup
        );

        $newItemsCount = $this->countItems('/attributes/groups', ['attribute_group_read']);
        $this->assertEquals($itemsCount + 1, $newItemsCount);

        return $attributeGroupId;
    }

    /**
     * @depends testAddAttributeGroup
     *
     * @param int $attributeGroupId
     *
     * @return int
     */
    public function testGetAttributeGroup(int $attributeGroupId): int
    {
        $attributeGroup = $this->getItem('/attributes/group/' . $attributeGroupId, ['attribute_group_read']);
        $this->assertEquals([
            'attributeGroupId' => $attributeGroupId,
            'names' => [
                'en-US' => 'name en',
                'fr-FR' => 'name fr',
            ],
            'publicNames' => [
                'en-US' => 'public name en',
                'fr-FR' => 'public name fr',
            ],
            'type' => 'select',
            'shopIds' => [1],
        ], $attributeGroup);

        return $attributeGroupId;
    }

    /**
     * @depends testGetAttributeGroup
     *
     * @param int $attributeGroupId
     *
     * @return int
     */
    public function testPartialUpdateAttributeGroup(int $attributeGroupId): int
    {
        $patchData = [
            'names' => [
                'en-US' => 'updated name en',
                'fr-FR' => 'updated name fr',
            ],
            'publicNames' => [
                'en-US' => 'updated public name en',
                'fr-FR' => 'updated public name fr',
            ],
            'type' => 'radio',
            'shopIds' => [1],
        ];

        $updatedAttributeGroup = $this->partialUpdateItem('/attributes/group/' . $attributeGroupId, $patchData, ['attribute_group_write']);
        $this->assertEquals(['attributeGroupId' => $attributeGroupId] + $patchData, $updatedAttributeGroup);

        // We check that when we GET the item it is updated as expected
        $attributeGroup = $this->getItem('/attributes/group/' . $attributeGroupId, ['attribute_group_read']);
        $this->assertEquals(['attributeGroupId' => $attributeGroupId] + $patchData, $attributeGroup);

        // Test partial update
        $partialUpdateData = [
            'names' => [
                'fr-FR' => 'updated nom fr',
            ],
            'publicNames' => [
                'en-US' => 'updated public nom en',
            ],
        ];
        $expectedUpdatedData = [
            'attributeGroupId' => $attributeGroupId,
            'names' => [
                'en-US' => 'updated name en',
                'fr-FR' => 'updated nom fr',
            ],
            'publicNames' => [
                'en-US' => 'updated public nom en',
                'fr-FR' => 'updated public name fr',
            ],
            'type' => 'radio',
            'shopIds' => [1],
        ];
        $updatedAttributeGroup = $this->partialUpdateItem('/attributes/group/' . $attributeGroupId, $partialUpdateData, ['attribute_group_write']);
        $this->assertEquals($expectedUpdatedData, $updatedAttributeGroup);

        return $attributeGroupId;
    }

    /**
     * @depends testPartialUpdateAttributeGroup
     *
     * @param int $attributeGroupId
     *
     * @return int
     */
    public function testListAttributeGroups(int $attributeGroupId): int
    {
        // List by attributeGroupId in descending order so the created one comes first (and test ordering at the same time)
        $paginatedAttributeGroups = $this->listItems('/attributes/groups?orderBy=attributeGroupId&sortOrder=desc', ['attribute_group_read']);
        $this->assertGreaterThanOrEqual(1, $paginatedAttributeGroups['totalItems']);

        // Check the details to make sure filters mapping is correct
        $this->assertEquals('attributeGroupId', $paginatedAttributeGroups['orderBy']);

        // Test attribute should be the first returned in the list
        $testAttributeGroup = $paginatedAttributeGroups['items'][0];

        // Position should be at least 3 since there are three groups in the default fixtures data
        $this->assertGreaterThanOrEqual(3, $testAttributeGroup['position']);
        $position = $testAttributeGroup['position'];
        $expectedAttributeGroup = [
            'attributeGroupId' => $attributeGroupId,
            'name' => 'updated name en',
            'values' => 0,
            'position' => $position,
        ];
        $this->assertEquals($expectedAttributeGroup, $testAttributeGroup);

        $filteredAttributeGroups = $this->listItems('/attributes/groups', ['attribute_group_read'], [
            'attributeGroupId' => $attributeGroupId,
        ]);
        $this->assertEquals(1, $filteredAttributeGroups['totalItems']);

        $testAttributeGroup = $filteredAttributeGroups['items'][0];
        $this->assertEquals($expectedAttributeGroup, $testAttributeGroup);

        // Check the filters details
        $this->assertEquals([
            'attributeGroupId' => $attributeGroupId,
        ], $filteredAttributeGroups['filters']);

        return $attributeGroupId;
    }

    /**
     * @depends testListAttributeGroups
     *
     * @param int $attributeGroupId
     */
    public function testRemoveAttributeGroup(int $attributeGroupId): void
    {
        // Delete the item
        $return = $this->deleteItem('/attributes/group/' . $attributeGroupId, ['attribute_group_write']);
        // This endpoint return empty response and 204 HTTP code
        $this->assertNull($return);

        // Getting the item should result in a 404 now
        $this->getItem('/attributes/group/' . $attributeGroupId, ['attribute_group_read'], Response::HTTP_NOT_FOUND);
    }

    public function testBulkRemoveAttributeGroups(): void
    {
        $attributeGroups = $this->listItems('/attributes/groups', ['attribute_group_read']);

        // There are four attribute groups in default fixtures
        $this->assertEquals(4, $attributeGroups['totalItems']);

        // We remove the first two attribute groups
        $removeAttributeGroupIds = [
            $attributeGroups['items'][0]['attributeGroupId'],
            $attributeGroups['items'][2]['attributeGroupId'],
        ];

        $this->updateItem('/attributes/groups/delete', [
            'attributeGroupIds' => $removeAttributeGroupIds,
        ], ['attribute_group_write'], Response::HTTP_NO_CONTENT);

        // Assert the provided attribute groups have been removed
        foreach ($removeAttributeGroupIds as $attributeGroupId) {
            $this->getItem('/attributes/group/' . $attributeGroupId, ['attribute_group_read'], Response::HTTP_NOT_FOUND);
        }

        // Only two attribute group remain
        $this->assertEquals(2, $this->countItems('/attributes/groups', ['attribute_group_read']));
    }

    public function testInvalidAttributeGroup(): void
    {
        $attributeGroupInvalidData = [
            'names' => [
                // en-US (default language) value is missing
                // < character is forbidden
                'fr-FR' => 'name fr<',
            ],
            'publicNames' => [
                // en-US (default language) value is missing
                // < character is forbidden
                'fr-FR' => 'public name fr<',
            ],
            // Type is not in the expected choices
            'type' => 'random',
            // ShopId must not be empty
            'shopIds' => [],
        ];

        // Creating with invalid data should return a response with invalid constraint messages and use an http code 422
        $validationErrorsResponse = $this->createItem('/attributes/group', $attributeGroupInvalidData, ['attribute_group_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
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
                'propertyPath' => 'publicNames',
                'message' => 'The field publicNames is required at least in your default language.',
            ],
            [
                'propertyPath' => 'publicNames[fr-FR]',
                'message' => '"public name fr<" is invalid',
            ],
            [
                'propertyPath' => 'type',
                'message' => 'The value you selected is not a valid choice.',
            ],
            [
                'propertyPath' => 'shopIds',
                'message' => 'This value should not be blank.',
            ],
        ], $validationErrorsResponse);

        // Now create a valid attribute group to test the validation on PATCH request
        $validAttributeGroup = $this->createItem('/attributes/group', [
            'names' => [
                'en-US' => 'name en',
                'fr-FR' => 'name fr',
            ],
            'publicNames' => [
                'en-US' => 'name en',
                'fr-FR' => 'name fr',
            ],
            'type' => 'select',
            'shopIds' => [1],
        ], ['attribute_group_write']);

        $attributeGroupId = $validAttributeGroup['attributeGroupId'];
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
            // SAme for publicNames
            [
                'data' => [
                    'publicNames' => [
                        'en-US' => '',
                    ],
                ],
                'expectedErrors' => [
                    [
                        'propertyPath' => 'publicNames',
                        'message' => 'The field publicNames is required at least in your default language.',
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
            [
                'data' => [
                    'type' => 'toto',
                ],
                'expectedErrors' => [
                    [
                        'propertyPath' => 'type',
                        'message' => 'The value you selected is not a valid choice.',
                    ],
                ],
            ],
        ];
        foreach ($invalidUpdateData as $updateData) {
            $validationErrorsResponse = $this->partialUpdateItem('/attributes/group/' . $attributeGroupId, $updateData['data'], ['attribute_group_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
            $this->assertValidationErrors($updateData['expectedErrors'], $validationErrorsResponse);
        }
    }
}
