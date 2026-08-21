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

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Operations;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\ResourceMetadataCollection;
use PHPUnit\Framework\TestCase;
use PrestaShop\Module\APIResources\ApiPlatform\Metadata\Resource\Factory\CoreVersionCompatibilityMetadataCollectionFactoryDecorator;
use PrestaShop\PrestaShop\Core\FeatureFlag\DisabledFeatureFlagStateChecker;
use PrestaShop\PrestaShop\Core\FeatureFlag\FeatureFlagStateCheckerInterface;
use PrestaShop\PrestaShop\Core\Version;

/**
 * Logic-level test for the (deprecated) module twin of the core CoreVersionCompatibilityMetadataCollectionFactoryDecorator.
 * The versioned operations use extreme min/max versions (1.0.0 and 99.99.99) plus the running Version::VERSION for the
 * inclusive bound cases, so the expectations hold on every core version of the CI matrix.
 */
class CoreVersionCompatibilityDecoratorTest extends TestCase
{
    // All the operations in their declaration order, expected when the filtering is bypassed
    private const ALL_OPERATIONS = [
        'no_version',
        'min_below',
        'min_equal',
        'min_above',
        'max_above',
        'max_equal',
        'max_below',
        'range_in',
        'range_out',
    ];

    private const EXPECTED_KEPT_OPERATIONS = [
        'no_version',
        'min_below',
        'min_equal',
        'max_above',
        'max_equal',
        'range_in',
    ];

    public function testIncompatibleOperationsAreFiltered(): void
    {
        if ($this->coreDecoratorClassExists()) {
            $this->markTestSkipped('On PrestaShop >= 9.2 the module decorator is a pure pass-through, filtering is covered by testPassThroughWhenCoreDecoratorClassExists');
        }

        $decorator = new CoreVersionCompatibilityMetadataCollectionFactoryDecorator(
            $this->buildDecoratedFactory(),
            new DisabledFeatureFlagStateChecker(),
        );

        $this->assertEquals(self::EXPECTED_KEPT_OPERATIONS, $this->getOperationNames($decorator->create('resourceClass')));
    }

    public function testPassThroughWhenCoreDecoratorClassExists(): void
    {
        // When the core twin decorator class exists (PrestaShop >= 9.2) the module decorator filters nothing,
        // the core is responsible for the filtering. The detection relies on class_exists, so this case can
        // only be exercised when the CI matrix runs against a core version that ships the class.
        if (!$this->coreDecoratorClassExists()) {
            $this->markTestSkipped('The core twin decorator class only exists on PrestaShop >= 9.2, filtering is covered by testIncompatibleOperationsAreFiltered');
        }

        $decorator = new CoreVersionCompatibilityMetadataCollectionFactoryDecorator(
            $this->buildDecoratedFactory(),
            new DisabledFeatureFlagStateChecker(),
        );

        $this->assertEquals(self::ALL_OPERATIONS, $this->getOperationNames($decorator->create('resourceClass')));
    }

    public function testEnabledExperimentalEndpointsFeatureFlagFiltersNothing(): void
    {
        $featureFlagStateChecker = $this->createMock(FeatureFlagStateCheckerInterface::class);
        $featureFlagStateChecker->method('isEnabled')->willReturn(true);

        $decorator = new CoreVersionCompatibilityMetadataCollectionFactoryDecorator(
            $this->buildDecoratedFactory(),
            $featureFlagStateChecker,
        );

        $this->assertEquals(self::ALL_OPERATIONS, $this->getOperationNames($decorator->create('resourceClass')));
    }

    private function coreDecoratorClassExists(): bool
    {
        return class_exists(\PrestaShopBundle\ApiPlatform\Metadata\Resource\Factory\CoreVersionCompatibilityMetadataCollectionFactoryDecorator::class);
    }

    private function buildDecoratedFactory(): ResourceMetadataCollectionFactoryInterface
    {
        // The operations are built with the generic Get operation and raw extra properties on purpose:
        // the minVersion/maxVersion named constructor arguments only exist on PrestaShop >= 9.2 while this
        // test must run against every supported core version
        $collection = new ResourceMetadataCollection('resourceClass', [
            (new ApiResource())->withOperations(new Operations([
                'no_version' => new Get(uriTemplate: '/no-version'),
                'min_below' => new Get(uriTemplate: '/min-below', extraProperties: ['minVersion' => '1.0.0']),
                'min_equal' => new Get(uriTemplate: '/min-equal', extraProperties: ['minVersion' => Version::VERSION]),
                'min_above' => new Get(uriTemplate: '/min-above', extraProperties: ['minVersion' => '99.99.99']),
                'max_above' => new Get(uriTemplate: '/max-above', extraProperties: ['maxVersion' => '99.99.99']),
                'max_equal' => new Get(uriTemplate: '/max-equal', extraProperties: ['maxVersion' => Version::VERSION]),
                'max_below' => new Get(uriTemplate: '/max-below', extraProperties: ['maxVersion' => '1.0.0']),
                'range_in' => new Get(uriTemplate: '/range-in', extraProperties: ['minVersion' => '1.0.0', 'maxVersion' => '99.99.99']),
                'range_out' => new Get(uriTemplate: '/range-out', extraProperties: ['minVersion' => '98.0.0', 'maxVersion' => '99.99.99']),
            ])),
        ]);

        $decoratedFactory = $this->createMock(ResourceMetadataCollectionFactoryInterface::class);
        $decoratedFactory->method('create')->willReturn($collection);

        return $decoratedFactory;
    }

    /**
     * @return string[]
     */
    private function getOperationNames(ResourceMetadataCollection $resourceMetadataCollection): array
    {
        $operationNames = [];
        /** @var ApiResource $resourceMetadata */
        foreach ($resourceMetadataCollection as $resourceMetadata) {
            foreach ($resourceMetadata->getOperations() as $operationName => $operation) {
                $operationNames[] = $operationName;
            }
        }

        return $operationNames;
    }
}
