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
        parent::setUpBeforeClass();
        self::createApiClient(['product_read', 'product_write']);
    }

    public static function tearDownAfterClass(): void
    {
        parent::tearDownAfterClass();
        DatabaseDump::restoreTables(['feature_product']);
    }

    public static function getProtectedEndpoints(): iterable
    {
        yield 'list product feature values endpoint' => ['GET', '/products/1/feature-values'];

        // The write operations use the CQRS*Collection operations of core 9.2.1, filtered out on older cores
        if (self::isVersionAtLeast('9.2.1')) {
            yield 'set product feature values endpoint' => ['PUT', '/products/1/feature-values'];
            yield 'remove all product feature values endpoint' => ['DELETE', '/products/1/feature-values'];
        }
    }

    public function testListFeatureValues(): void
    {
        $this->assertSame([
            ['productId' => 1, 'featureId' => 1, 'featureValueId' => 4, 'custom' => false],
            ['productId' => 1, 'featureId' => 2, 'featureValueId' => 8, 'custom' => false],
        ], $this->getItem('/products/1/feature-values', ['product_read']));

        // The query doesn't check the product
        $this->assertSame([], $this->getItem('/products/999999/feature-values', ['product_read']));
        $this->getItem('/products/0/feature-values', ['product_read'], Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function testWriteOperationsAreFilteredOutOnOlderCores(): void
    {
        if (self::isVersionAtLeast('9.2.1')) {
            $this->markTestSkipped('The write operations exist from PrestaShop 9.2.1');
        }

        // The resource still loads: only its write operations are missing
        $this->updateItem('/products/1/feature-values', ['featureValues' => []], ['product_write'], Response::HTTP_METHOD_NOT_ALLOWED);
        $this->deleteItem('/products/1/feature-values', ['product_write'], Response::HTTP_METHOD_NOT_ALLOWED);
    }

    public function testSetFeatureValues(): void
    {
        $this->markTestSkippedByMinVersion('9.2.1');

        $featureValues = $this->updateItem('/products/1/feature-values', [
            'featureValues' => [
                ['featureId' => 1, 'featureValueId' => 3],
            ],
        ], ['product_write']);
        $expected = [
            ['productId' => 1, 'featureId' => 1, 'featureValueId' => 3, 'custom' => false],
        ];
        $this->assertSame($expected, $featureValues);
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
}
