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

class ProductFeatureValueListEndpointTest extends ApiTestCase
{
    public static function setUpBeforeClass(): void
    {
        // The fixture features of product 1: a feature deleted by an earlier test class takes its values and their
        // product associations with it. Restored before the parent installs fr-FR, which copies the values in it
        self::resetTables();
        parent::setUpBeforeClass();
        self::createApiClient(['product_read', 'product_write']);
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        self::resetTables();
    }

    protected static function resetTables(): void
    {
        DatabaseDump::restoreTables([
            'feature',
            'feature_lang',
            'feature_product',
            'feature_shop',
            'feature_value',
            'feature_value_lang',
        ]);
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'list product feature values endpoint' => ['GET', '/products/1/feature-values'];

        // The write operations use the CQRS*Collection operations of core 9.2.2, filtered out on older cores
        if (self::isVersionAtLeast('9.2.2')) {
            yield 'set product feature values endpoint' => ['PUT', '/products/1/feature-values'];
            yield 'remove all product feature values endpoint' => ['DELETE', '/products/1/feature-values'];
        }
    }

    public function testListFeatureValues(): void
    {
        // The demo associations of product 1, not a fixture of the test: they can only be created through the PUT,
        // which the cores before 9.2.2 don't have, while the GET is tested on every core
        // The locales of a value come in no guaranteed order
        $this->assertEquals([
            ['productId' => 1, 'featureId' => 1, 'featureValueId' => 4, 'custom' => false, 'values' => ['en-US' => 'Cotton', 'fr-FR' => 'Cotton']],
            ['productId' => 1, 'featureId' => 2, 'featureValueId' => 8, 'custom' => false, 'values' => ['en-US' => 'Short sleeves', 'fr-FR' => 'Short sleeves']],
        ], $this->getItem('/products/1/feature-values', ['product_read']));

        // The query doesn't check the product
        $this->assertSame([], $this->getItem('/products/999999/feature-values', ['product_read']));
        $this->getItem('/products/0/feature-values', ['product_read'], Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testWriteOperationsAreFilteredOutOnOlderCores(): void
    {
        if (self::isVersionAtLeast('9.2.2')) {
            $this->markTestSkipped('The write operations exist from PrestaShop 9.2.2');
        }

        // The resource still loads: only its write operations are missing
        $this->updateItem('/products/1/feature-values', ['featureValues' => []], ['product_write'], Response::HTTP_METHOD_NOT_ALLOWED);
        $this->deleteItem('/products/1/feature-values', ['product_write'], Response::HTTP_METHOD_NOT_ALLOWED);
    }

    /**
     * @depends testListFeatureValues
     */
    public function testSetFeatureValues(): void
    {
        $this->markTestSkippedByMinVersion('9.2.2');

        $featureValues = $this->updateItem('/products/1/feature-values', [
            'featureValues' => [
                ['featureId' => 1, 'featureValueId' => 3],
            ],
        ], ['product_write']);
        $expected = [
            ['productId' => 1, 'featureId' => 1, 'featureValueId' => 3, 'custom' => false, 'values' => ['en-US' => 'Ceramic', 'fr-FR' => 'Ceramic']],
        ];
        $this->assertEquals($expected, $featureValues);
        $this->assertEquals($expected, $this->getItem('/products/1/feature-values', ['product_read']));
    }

    /**
     * @depends testSetFeatureValues
     */
    public function testSetInvalidFeatureValues(): void
    {
        $expected = $this->getItem('/products/1/feature-values', ['product_read']);

        // An unknown id of the body answers 404
        $this->updateItem('/products/1/feature-values', ['featureValues' => [['featureId' => 1, 'featureValueId' => 99999]]], ['product_write'], Response::HTTP_NOT_FOUND);
        $this->updateItem('/products/1/feature-values', ['featureValues' => [['featureId' => 99999, 'featureValueId' => 3]]], ['product_write'], Response::HTTP_NOT_FOUND);
        // A value of another feature, or the same value twice, answers 422
        $this->updateItem('/products/1/feature-values', ['featureValues' => [['featureId' => 2, 'featureValueId' => 3]]], ['product_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->updateItem('/products/1/feature-values', ['featureValues' => [
            ['featureId' => 1, 'featureValueId' => 3],
            ['featureId' => 1, 'featureValueId' => 3],
        ]], ['product_write'], Response::HTTP_UNPROCESSABLE_ENTITY);

        // The list and its items are validated before the command is built
        $response = $this->updateItem('/products/1/feature-values', ['featureValues' => []], ['product_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertValidationErrors([
            ['propertyPath' => 'featureValues', 'message' => 'This value should not be blank.'],
        ], $response);
        $response = $this->updateItem('/products/1/feature-values', ['featureValues' => [['featureId' => 0], ['featureId' => 1, 'featureValueId' => -3]]], ['product_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertValidationErrors([
            ['propertyPath' => 'featureValues[0].featureId', 'message' => 'This value should be positive.'],
            ['propertyPath' => 'featureValues[0].featureValueId', 'message' => 'This value should not be null.'],
            ['propertyPath' => 'featureValues[1].featureValueId', 'message' => 'This value should be positive.'],
        ], $response);
        // A missing list and an explicit null give the same violation
        foreach ([[], ['featureValues' => null]] as $body) {
            $response = $this->requestApi('PUT', '/products/1/feature-values', null, ['product_write'], Response::HTTP_UNPROCESSABLE_ENTITY, ['body' => json_encode((object) $body), 'headers' => ['Content-Type' => 'application/json']]);
            $this->assertValidationErrors([
                ['propertyPath' => 'featureValues', 'message' => 'This value should not be blank.'],
            ], $response);
        }
        // A wrong type is refused by the serializer
        $this->updateItem('/products/1/feature-values', ['featureValues' => [['featureId' => 'abc', 'featureValueId' => 3]]], ['product_write'], Response::HTTP_BAD_REQUEST);

        // Nothing changed
        $this->assertSame($expected, $this->getItem('/products/1/feature-values', ['product_read']));
    }

    /**
     * @depends testSetFeatureValues
     */
    public function testRemoveAllFeatureValues(): void
    {
        $this->assertSame([], $this->deleteItem('/products/1/feature-values', ['product_write'], Response::HTTP_OK));
        $this->assertSame([], $this->getItem('/products/1/feature-values', ['product_read']));
    }

    public function testWriteFeatureValuesOfUnknownProduct(): void
    {
        $this->markTestSkippedByMinVersion('9.2.2');

        $this->updateItem('/products/0/feature-values', ['featureValues' => [['featureId' => 1, 'featureValueId' => 3]]], ['product_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->deleteItem('/products/0/feature-values', ['product_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
        // Unlike the GET, the commands check the product
        $this->updateItem('/products/999999/feature-values', ['featureValues' => [['featureId' => 1, 'featureValueId' => 3]]], ['product_write'], Response::HTTP_NOT_FOUND);
        $this->deleteItem('/products/999999/feature-values', ['product_write'], Response::HTTP_NOT_FOUND);
    }
}
