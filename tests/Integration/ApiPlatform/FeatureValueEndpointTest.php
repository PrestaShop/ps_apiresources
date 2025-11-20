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

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Tests\Resources\DatabaseDump;
use Tests\Resources\Resetter\LanguageResetter;

class FeatureValueEndpointTest extends ApiTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        LanguageResetter::resetLanguages();
        self::addLanguageByLocale('fr-FR');
        self::resetTables();
        self::createApiClient(['feature_value_read', 'feature_value_write']);
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        LanguageResetter::resetLanguages();
        self::resetTables();
    }

    protected static function resetTables(): void
    {
        DatabaseDump::restoreTables([
            'feature_value',
            'feature_value_lang',
        ]);
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'get endpoint' => [
            'GET',
            '/features/values/1',
        ];

        yield 'create endpoint' => [
            'POST',
            '/features/values',
        ];

        yield 'patch endpoint' => [
            'PATCH',
            '/features/values/1',
        ];

        yield 'delete endpoint' => [
            'DELETE',
            '/features/values/1',
        ];

        yield 'list endpoint' => [
            'GET',
            '/features/1/values',
        ];

        yield 'bulk delete endpoint' => [
            'DELETE',
            '/features/values/batch',
        ];
    }

    public function testAddFeatureValue(): int
    {
        $postData = [
            'values' => [
                'en-US' => 'Feature value EN',
                'fr-FR' => 'Feature value FR',
            ],
            'featureId' => 1,
        ];

        $featureValue = $this->createItem('/features/values', $postData, ['feature_value_write']);
        $this->assertArrayHasKey('featureValueId', $featureValue);

        $featureValueId = $featureValue['featureValueId'];
        $this->assertSame($postData['values'], $featureValue['values']);

        return $featureValueId;
    }

    /**
     * @depends testAddFeatureValue
     */
    public function testGetFeatureValue(int $featureValueId): int
    {
        $feature = $this->getItem('/features/values/' . $featureValueId, ['feature_value_read']);
        $this->assertEquals($featureValueId, $feature['featureValueId']);
        $this->assertArrayHasKey('values', $feature);

        return $featureValueId;
    }

    /**
     * @depends testGetFeatureValue
     */
    public function testPartialUpdateFeatureValue(int $featureValueId): int
    {
        $patchData = [
            'values' => [
                'en-US' => 'Updated Feature value EN',
                'fr-FR' => 'Updated Feature value FR',
            ],
        ];

        $updatedFeature = $this->partialUpdateItem('/features/values/' . $featureValueId, $patchData, ['feature_value_write']);
        $this->assertSame($patchData['values'], $updatedFeature['values']);

        return $featureValueId;
    }

    /**
     * @depends testPartialUpdateFeatureValue
     */
    public function testListFeatureValues(int $featureValueId): int
    {
        $paginatedFeatures = $this->listItems('/features/1/values?orderBy=featureValueId&sortOrder=desc', ['feature_value_read']);
        $this->assertGreaterThanOrEqual(1, $paginatedFeatures['totalItems']);
        $this->assertEquals('featureValueId', $paginatedFeatures['orderBy']);

        $firstFeature = $paginatedFeatures['items'][0];

        $this->assertEquals($featureValueId, $firstFeature['featureValueId']);

        return $featureValueId;
    }

    /**
     * @depends testListFeatureValues
     */
    public function testRemoveFeatureValue(int $featureValueId): void
    {
        $this->deleteItem('/features/values/' . $featureValueId, ['feature_value_write']);
        $this->getItem('/features/values/' . $featureValueId, ['feature_value_read'], Response::HTTP_NOT_FOUND);
    }

    public function testBulkRemoveFeatureValues(): void
    {
        $features = $this->listItems('/features/1/values', ['feature_value_read']);

        $this->assertGreaterThanOrEqual(2, $features['totalItems']);

        $removeFeatureIds = [
            $features['items'][0]['featureValueId'],
        ];

        $this->deleteBatch('/features/values/batch', [
            'featureValueIds' => $removeFeatureIds,
        ], ['feature_value_write'], Response::HTTP_NO_CONTENT);

        foreach ($removeFeatureIds as $featureValueId) {
            $this->getItem('/features/values/' . $featureValueId, ['feature_value_read'], Response::HTTP_NOT_FOUND);
        }
    }

    public function testInvalidFeatureValue(): void
    {
        $invalidData = [
            'values' => [
                'fr-FR' => 'Invalid<',
            ],
        ];

        $validationErrorsResponse = $this->createItem('/features/values', $invalidData, ['feature_value_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertIsArray($validationErrorsResponse);

        $this->assertValidationErrors([
            [
                'propertyPath' => 'values',
                'message' => 'The field values is required at least in your default language.',
            ],
            [
                'propertyPath' => 'values[fr-FR]',
                'message' => '"Invalid<" is invalid',
            ],
        ], $validationErrorsResponse);
    }

    protected function deleteBatch(string $endPointUrl, ?array $data, array $scopes = [], ?int $expectedHttpCode = null, ?array $requestOptions = null): array|string|null
    {
        return $this->requestApi(Request::METHOD_DELETE, $endPointUrl, $data, $scopes, $expectedHttpCode, $requestOptions);
    }
}
