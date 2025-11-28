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

class FeatureEndpointTest extends ApiTestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        LanguageResetter::resetLanguages();
        self::addLanguageByLocale('fr-FR');
        self::resetTables();
        self::createApiClient(['feature_read', 'feature_write']);
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
            'feature',
            'feature_lang',
            'feature_shop',
        ]);
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'get endpoint' => [
            'GET',
            '/features/1',
        ];

        yield 'create endpoint' => [
            'POST',
            '/features',
        ];

        yield 'patch endpoint' => [
            'PATCH',
            '/features/1',
        ];

        yield 'delete endpoint' => [
            'DELETE',
            '/features/1',
        ];

        yield 'list endpoint' => [
            'GET',
            '/features',
        ];

        yield 'bulk delete endpoint' => [
            'DELETE',
            '/features/bulk-delete',
        ];
    }

    public function testAddFeature(): int
    {
        $postData = [
            'names' => [
                'en-US' => 'Feature EN',
                'fr-FR' => 'Feature FR',
            ],
            'shopIds' => [1],
        ];

        $feature = $this->createItem('/features', $postData, ['feature_write']);
        $this->assertArrayHasKey('featureId', $feature);
        $featureId = $feature['featureId'];

        $this->assertSame($postData['names'], $feature['names']);

        return $featureId;
    }

    /**
     * @depends testAddFeature
     */
    public function testGetFeature(int $featureId): int
    {
        $feature = $this->getItem('/features/' . $featureId, ['feature_read']);
        $this->assertEquals($featureId, $feature['featureId']);
        $this->assertArrayHasKey('names', $feature);

        return $featureId;
    }

    /**
     * @depends testGetFeature
     */
    public function testPartialUpdateFeature(int $featureId): int
    {
        $patchData = [
            'names' => [
                'en-US' => 'Updated Feature EN',
                'fr-FR' => 'Updated Feature FR',
            ],
            'shopIds' => [1],
        ];

        $updatedFeature = $this->partialUpdateItem('/features/' . $featureId, $patchData, ['feature_write']);
        $this->assertSame($patchData['names'], $updatedFeature['names']);

        return $featureId;
    }

    /**
     * @depends testPartialUpdateFeature
     */
    public function testListFeatures(int $featureId): int
    {
        $paginatedFeatures = $this->listItems('/features?orderBy=featureId&sortOrder=desc', ['feature_read']);
        $this->assertGreaterThanOrEqual(1, $paginatedFeatures['totalItems']);
        $this->assertEquals('featureId', $paginatedFeatures['orderBy']);

        $firstFeature = $paginatedFeatures['items'][0];
        $this->assertEquals($featureId, $firstFeature['featureId']);

        return $featureId;
    }

    /**
     * @depends testListFeatures
     */
    public function testRemoveFeature(int $featureId): void
    {
        $this->deleteItem('/features/' . $featureId, ['feature_write']);
        $this->getItem('/features/' . $featureId, ['feature_read'], Response::HTTP_NOT_FOUND);
    }

    public function testBulkDeleteFeatures(): void
    {
        $features = $this->listItems('/features', ['feature_read']);

        $this->assertGreaterThanOrEqual(2, $features['totalItems']);

        $removeFeatureIds = [
            $features['items'][0]['featureId'],
        ];

        $this->bulkDeleteItems('/features/bulk-delete', [
            'featureIds' => $removeFeatureIds,
        ], ['feature_write']);

        foreach ($removeFeatureIds as $featureId) {
            $this->getItem('/features/' . $featureId, ['feature_read'], Response::HTTP_NOT_FOUND);
        }
    }

    public function testInvalidFeature(): void
    {
        $invalidData = [
            'names' => [
                'fr-FR' => 'Invalid<',
            ],
            'shopIds' => [],
        ];

        $validationErrorsResponse = $this->createItem('/features', $invalidData, ['feature_write'], Response::HTTP_UNPROCESSABLE_ENTITY);
        $this->assertIsArray($validationErrorsResponse);

        $this->assertValidationErrors([
            [
                'propertyPath' => 'names',
                'message' => 'The field names is required at least in your default language.',
            ],
            [
                'propertyPath' => 'names[fr-FR]',
                'message' => '"Invalid<" is invalid',
            ],
            [
                'propertyPath' => 'shopIds',
                'message' => 'This value should not be blank.',
            ],
        ], $validationErrorsResponse);
    }
}
