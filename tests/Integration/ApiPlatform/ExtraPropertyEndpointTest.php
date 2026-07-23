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

class ExtraPropertyEndpointTest extends ApiTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::resetTables();
        self::createApiClient(['extra_property_read', 'extra_property_write']);
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        self::resetTables();
    }

    protected static function resetTables(): void
    {
        DatabaseDump::restoreTables([
            'extra_property_definition',
        ]);
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'get endpoint' => ['GET', '/extra-properties/1'];
        yield 'create endpoint' => ['POST', '/extra-properties'];
        yield 'patch endpoint' => ['PATCH', '/extra-properties/1'];
        yield 'delete endpoint' => ['DELETE', '/extra-properties/1'];
        yield 'list endpoint' => ['GET', '/extra-properties'];
        yield 'bulk-delete endpoint' => ['DELETE', '/extra-properties/bulk-delete'];
    }

    public function testAddExtraProperty(): int
    {
        $postData = [
            'entityName' => 'product',
            'propertyName' => 'api_test_internal_code',
            'fieldType' => 'string',
            'fieldScope' => 'common',
            'sqlIndex' => 'none',
            'displayFront' => false,
            'required' => false,
            'nullable' => true,
            'size' => 64,
            'labelWording' => 'Internal code',
            'labelDomain' => 'Admin.Catalog.Feature',
        ];

        $response = $this->createItem('/extra-properties', $postData, ['extra_property_write']);

        $this->assertArrayHasKey('extraPropertyId', $response);
        $this->assertSame('product', $response['entityName']);
        $this->assertSame('api_test_internal_code', $response['propertyName']);
        $this->assertNull($response['moduleName'] ?? null);
        $this->assertSame('string', $response['fieldType']);
        $this->assertSame('common', $response['fieldScope']);
        $this->assertSame('none', $response['sqlIndex']);
        $this->assertFalse($response['displayFront']);
        $this->assertFalse($response['required']);
        $this->assertTrue($response['nullable']);
        $this->assertSame(64, $response['size']);
        $this->assertSame('Internal code', $response['labelWording']);
        $this->assertSame('Admin.Catalog.Feature', $response['labelDomain']);

        return (int) $response['extraPropertyId'];
    }

    /**
     * @depends testAddExtraProperty
     */
    public function testGetExtraProperty(int $extraPropertyId): int
    {
        $response = $this->getItem('/extra-properties/' . $extraPropertyId, ['extra_property_read']);

        $this->assertSame($extraPropertyId, $response['extraPropertyId']);
        $this->assertSame('product', $response['entityName']);
        $this->assertSame('api_test_internal_code', $response['propertyName']);
        $this->assertNull($response['moduleName'] ?? null);
        $this->assertSame('string', $response['fieldType']);

        return $extraPropertyId;
    }

    /**
     * @depends testGetExtraProperty
     */
    public function testPartialUpdateExtraProperty(int $extraPropertyId): int
    {
        $patchData = [
            'displayFront' => true,
            'required' => true,
            'labelWording' => 'Internal code (updated)',
        ];

        $updated = $this->partialUpdateItem(
            '/extra-properties/' . $extraPropertyId,
            $patchData,
            ['extra_property_write']
        );

        $this->assertTrue($updated['displayFront']);
        $this->assertTrue($updated['required']);
        $this->assertSame('Internal code (updated)', $updated['labelWording']);
        // Structural fields must remain untouched.
        $this->assertSame('product', $updated['entityName']);
        $this->assertSame('api_test_internal_code', $updated['propertyName']);
        $this->assertSame('string', $updated['fieldType']);

        $fetched = $this->getItem('/extra-properties/' . $extraPropertyId, ['extra_property_read']);
        $this->assertTrue($fetched['displayFront']);
        $this->assertTrue($fetched['required']);
        $this->assertSame('Internal code (updated)', $fetched['labelWording']);

        return $extraPropertyId;
    }

    /**
     * @depends testPartialUpdateExtraProperty
     */
    public function testDeleteExtraProperty(int $extraPropertyId): void
    {
        $this->deleteItem('/extra-properties/' . $extraPropertyId, ['extra_property_write']);
        $this->getItem(
            '/extra-properties/' . $extraPropertyId,
            ['extra_property_read'],
            Response::HTTP_NOT_FOUND
        );
    }

    public function testBulkDeleteExtraProperties(): void
    {
        $ids = [];
        foreach (['api_test_bulk_a', 'api_test_bulk_b'] as $propertyName) {
            $created = $this->createItem(
                '/extra-properties',
                [
                    'entityName' => 'product',
                    'propertyName' => $propertyName,
                    'fieldType' => 'string',
                    'fieldScope' => 'common',
                    'sqlIndex' => 'none',
                    'displayFront' => false,
                    'required' => false,
                    'nullable' => true,
                ],
                ['extra_property_write']
            );
            $ids[] = (int) $created['extraPropertyId'];
        }

        $this->bulkDeleteItems(
            '/extra-properties/bulk-delete',
            ['extraPropertyIds' => $ids, 'dropColumn' => true],
            ['extra_property_write']
        );

        foreach ($ids as $id) {
            $this->getItem(
                '/extra-properties/' . $id,
                ['extra_property_read'],
                Response::HTTP_NOT_FOUND
            );
        }
    }

    public function testInvalidExtraProperty(): void
    {
        $invalidData = [
            // Missing entityName and propertyName — both NotBlank on Create.
            'fieldType' => 'string',
            'fieldScope' => 'common',
            'sqlIndex' => 'none',
            'displayFront' => false,
            'required' => false,
            'nullable' => true,
        ];

        $response = $this->createItem(
            '/extra-properties',
            $invalidData,
            ['extra_property_write'],
            Response::HTTP_UNPROCESSABLE_ENTITY
        );

        $this->assertValidationErrors([
            ['propertyPath' => 'entityName', 'message' => 'This value should not be blank.'],
            ['propertyPath' => 'propertyName', 'message' => 'This value should not be blank.'],
        ], $response);
    }
}
