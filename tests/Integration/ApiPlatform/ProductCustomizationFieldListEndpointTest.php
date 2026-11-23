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

use PrestaShop\PrestaShop\Core\Domain\Product\Customization\ValueObject\CustomizationFieldType;
use PrestaShop\PrestaShop\Core\Domain\Product\ValueObject\ProductType;
use Symfony\Component\HttpFoundation\Response;
use Tests\Resources\Resetter\ProductResetter;

class ProductCustomizationFieldListEndpointTest extends ApiTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        ProductResetter::resetProducts();
        self::createApiClient(['product_read', 'product_write']);
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        ProductResetter::resetProducts();
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'list customization fields endpoint' => ['GET', '/products/1/customization-fields'];

        // The write operations use the CQRS*Collection operations of core 9.2.2, filtered out on older cores
        if (self::isVersionAtLeast('9.2.2')) {
            yield 'set customization fields endpoint' => ['PUT', '/products/1/customization-fields'];
            yield 'remove all customization fields endpoint' => ['DELETE', '/products/1/customization-fields'];
        }
    }

    public function testListCustomizationFields(): int
    {
        $product = $this->createItem('/products', [
            'type' => ProductType::TYPE_STANDARD,
            'names' => [
                'en-US' => 'customizable product',
                'fr-FR' => 'produit personnalisable',
            ],
        ], ['product_write']);
        $this->assertArrayHasKey('productId', $product);
        $productId = $product['productId'];

        $this->assertSame([], $this->getItem('/products/' . $productId . '/customization-fields', ['product_read']));

        // The query doesn't check the product
        $this->assertSame([], $this->getItem('/products/999999/customization-fields', ['product_read']));
        $this->getItem('/products/0/customization-fields', ['product_read'], Response::HTTP_UNPROCESSABLE_ENTITY);

        return $productId;
    }

    public function testWriteOperationsAreFilteredOutOnOlderCores(): void
    {
        if (self::isVersionAtLeast('9.2.2')) {
            $this->markTestSkipped('The write operations exist from PrestaShop 9.2.2');
        }

        // The resource still loads: only its write operations are missing
        $this->updateItem('/products/1/customization-fields', ['customizationFields' => []], ['product_write'], Response::HTTP_METHOD_NOT_ALLOWED);
        $this->deleteItem('/products/1/customization-fields', ['product_write'], Response::HTTP_METHOD_NOT_ALLOWED);
    }

    /**
     * @depends testListCustomizationFields
     */
    public function testSetCustomizationFields(int $productId): array
    {
        $this->markTestSkippedByMinVersion('9.2.2');
        $uri = '/products/' . $productId . '/customization-fields';

        $fields = $this->updateItem($uri, [
            'customizationFields' => [
                [
                    'type' => CustomizationFieldType::TYPE_TEXT,
                    'names' => [
                        'en-US' => 'Engraving',
                        'fr-FR' => 'Gravure',
                    ],
                    'required' => true,
                    'addedByModule' => false,
                ],
                [
                    'type' => CustomizationFieldType::TYPE_FILE,
                    'names' => [
                        'en-US' => 'Picture',
                        'fr-FR' => 'Image',
                    ],
                    'required' => false,
                    'addedByModule' => true,
                ],
            ],
        ], ['product_write']);
        $this->assertCount(2, $fields);
        $fields = $this->sortById($fields);
        $fieldIds = array_column($fields, 'customizationFieldId');
        foreach ($fieldIds as $fieldId) {
            $this->assertIsInt($fieldId);
        }
        $expectedFields = [
            [
                'productId' => $productId,
                'customizationFieldId' => $fieldIds[0],
                'type' => CustomizationFieldType::TYPE_TEXT,
                'names' => [
                    'en-US' => 'Engraving',
                    'fr-FR' => 'Gravure',
                ],
                'required' => true,
                'addedByModule' => false,
            ],
            [
                'productId' => $productId,
                'customizationFieldId' => $fieldIds[1],
                'type' => CustomizationFieldType::TYPE_FILE,
                'names' => [
                    'en-US' => 'Picture',
                    'fr-FR' => 'Image',
                ],
                'required' => false,
                'addedByModule' => true,
            ],
        ];
        $this->assertEquals($expectedFields, $fields);
        $this->assertEquals($expectedFields, $this->sortById($this->getItem($uri, ['product_read'])));

        // A field with an id is updated, the fields left out are removed
        $expectedFields = [
            [
                'productId' => $productId,
                'customizationFieldId' => $fieldIds[0],
                'type' => CustomizationFieldType::TYPE_TEXT,
                'names' => [
                    'en-US' => 'Message',
                    'fr-FR' => 'Message FR',
                ],
                'required' => false,
                'addedByModule' => false,
            ],
        ];
        $fields = $this->updateItem($uri, [
            'customizationFields' => [
                [
                    'customizationFieldId' => $fieldIds[0],
                    'type' => CustomizationFieldType::TYPE_TEXT,
                    'names' => [
                        'en-US' => 'Message',
                        'fr-FR' => 'Message FR',
                    ],
                    'required' => false,
                    'addedByModule' => false,
                ],
            ],
        ], ['product_write']);
        $this->assertEquals($expectedFields, $fields);
        $this->assertEquals($expectedFields, $this->getItem($uri, ['product_read']));

        return $expectedFields;
    }

    /**
     * @depends testListCustomizationFields
     * @depends testSetCustomizationFields
     */
    public function testSetInvalidCustomizationFields(int $productId, array $expectedFields): void
    {
        $uri = '/products/' . $productId . '/customization-fields';

        // A missing list, an explicit null and an empty list give the same violation
        foreach ([[], ['customizationFields' => null], ['customizationFields' => []]] as $body) {
            $response = $this->requestApi('PUT', $uri, null, ['product_write'], Response::HTTP_UNPROCESSABLE_ENTITY, [
                'body' => json_encode((object) $body),
                'headers' => ['Content-Type' => 'application/json'],
            ]);
            $this->assertValidationErrors([
                ['propertyPath' => 'customizationFields', 'message' => 'This value should not be blank.'],
            ], $response);
        }

        // The items are validated before the command is built
        $response = $this->updateItem($uri, [
            'customizationFields' => [
                [
                    'names' => [
                        'en-US' => 'Message',
                    ],
                ],
                [
                    'customizationFieldId' => 0,
                    'type' => 5,
                    'names' => [],
                    'required' => true,
                    'addedByModule' => false,
                ],
            ],
        ], ['product_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertValidationErrors([
            ['propertyPath' => 'customizationFields[0].type', 'message' => 'This value should not be null.'],
            ['propertyPath' => 'customizationFields[0].required', 'message' => 'This value should not be null.'],
            ['propertyPath' => 'customizationFields[0].addedByModule', 'message' => 'This value should not be null.'],
            ['propertyPath' => 'customizationFields[1].customizationFieldId', 'message' => 'This value should be positive.'],
            ['propertyPath' => 'customizationFields[1].type', 'message' => 'The value you selected is not a valid choice.'],
            ['propertyPath' => 'customizationFields[1].names', 'message' => 'This value should not be blank.'],
        ], $response);

        $this->updateItem('/products/0/customization-fields', ['customizationFields' => [
            ['type' => CustomizationFieldType::TYPE_TEXT, 'names' => ['en-US' => 'Message'], 'required' => false, 'addedByModule' => false],
        ]], ['product_write'], Response::HTTP_UNPROCESSABLE_ENTITY);

        // Nothing changed
        $this->assertEquals($expectedFields, $this->getItem($uri, ['product_read']));
    }

    /**
     * @depends testListCustomizationFields
     * @depends testSetCustomizationFields
     */
    public function testRemoveAllCustomizationFields(int $productId): void
    {
        $uri = '/products/' . $productId . '/customization-fields';
        $this->assertNotEmpty($this->getItem($uri, ['product_read']));

        $this->assertSame([], $this->deleteItem($uri, ['product_write'], Response::HTTP_OK));
        $this->assertSame([], $this->getItem($uri, ['product_read']));

        $this->deleteItem('/products/999999/customization-fields', ['product_write'], Response::HTTP_NOT_FOUND);
        $this->deleteItem('/products/0/customization-fields', ['product_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    /**
     * The core reads the fields in no guaranteed order.
     */
    private function sortById(array $fields): array
    {
        usort($fields, static fn (array $a, array $b): int => $a['customizationFieldId'] <=> $b['customizationFieldId']);

        return $fields;
    }
}
