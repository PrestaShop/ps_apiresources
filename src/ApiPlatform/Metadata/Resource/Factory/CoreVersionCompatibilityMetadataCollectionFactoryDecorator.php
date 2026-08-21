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

namespace PrestaShop\Module\APIResources\ApiPlatform\Metadata\Resource\Factory;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Resource\Factory\ResourceMetadataCollectionFactoryInterface;
use ApiPlatform\Metadata\Resource\ResourceMetadataCollection;
use PrestaShop\PrestaShop\Core\FeatureFlag\FeatureFlagSettings;
use PrestaShop\PrestaShop\Core\FeatureFlag\FeatureFlagStateCheckerInterface;
use PrestaShop\PrestaShop\Core\Version;

/**
 * This factory decorates the ApiPlatform default resource factory. It looks into each operation and checks
 * if the extra properties minVersion and/or maxVersion are defined, if the running core version is out of the
 * declared bounds the operation is removed. This means the operation is not visible in Swagger, and it's not
 * used to generate the api routing, so it's not usable at all and returns a 404.
 *
 * Both bounds are inclusive and compared with version_compare, so its semantics apply as-is (note for example
 * that 9.2.0-beta.1 < 9.2.0). No validation is performed on the version strings.
 *
 * The filtering applies in both prod and debug mode, unless the experimental endpoints feature flag is
 * enabled. Note that the core version constant lags behind the actual content of the development branches
 * (Version::VERSION is only bumped at release time), so enable the feature flag to work with an endpoint
 * gated on a not-yet-released version.
 *
 * @deprecated This class is a duplicate of
 * PrestaShopBundle\ApiPlatform\Metadata\Resource\Factory\CoreVersionCompatibilityMetadataCollectionFactoryDecorator
 * introduced in PrestaShop 9.2.0. It only exists so that the minVersion/maxVersion extra properties are honored
 * when the module runs on PrestaShop < 9.2 (it is a pure pass-through when the core decorator is detected, to
 * avoid double filtering). Remove this class and its service declaration in config/admin/services.yml as soon
 * as the module requires PrestaShop >= 9.2.
 */
class CoreVersionCompatibilityMetadataCollectionFactoryDecorator implements ResourceMetadataCollectionFactoryInterface
{
    public function __construct(
        private readonly ResourceMetadataCollectionFactoryInterface $decorated,
        private readonly FeatureFlagStateCheckerInterface $featureFlagStateChecker,
    ) {
    }

    public function create(string $resourceClass): ResourceMetadataCollection
    {
        // We call the original method since we only want to alter the result of this method.
        $resourceMetadataCollection = $this->decorated->create($resourceClass);

        // The core twin decorator exists on PrestaShop >= 9.2 and already handles the filtering, so this
        // decorator is a pure pass-through. The check relies on class_exists instead of injecting the core
        // service, which would create a circular reference in the decoration chain.
        if (class_exists(\PrestaShopBundle\ApiPlatform\Metadata\Resource\Factory\CoreVersionCompatibilityMetadataCollectionFactoryDecorator::class)) {
            return $resourceMetadataCollection;
        }

        // In debug and prod mode we always hide the incompatible endpoints, unless the experimental endpoints are forcefully enabled
        if ($this->areExperimentalEndpointsEnabled()) {
            return $resourceMetadataCollection;
        }

        /** @var ApiResource $resourceMetadata */
        foreach ($resourceMetadataCollection as $resourceMetadata) {
            $operations = $resourceMetadata->getOperations();
            /** @var Operation $operation */
            foreach ($operations as $key => $operation) {
                if (!$this->isCompatibleWithCoreVersion($operation)) {
                    $operations->remove($key);
                }
            }
        }

        return $resourceMetadataCollection;
    }

    private function isCompatibleWithCoreVersion(Operation $operation): bool
    {
        $extraProperties = $operation->getExtraProperties();
        if (!empty($extraProperties['minVersion']) && version_compare(Version::VERSION, $extraProperties['minVersion'], '<')) {
            return false;
        }
        if (!empty($extraProperties['maxVersion']) && version_compare(Version::VERSION, $extraProperties['maxVersion'], '>')) {
            return false;
        }

        return true;
    }

    /**
     * This decorator is implied during cache clearing which would fail when the shop is not installed
     * because the DB config is not set up yet. So we protected the feature flag fetching in a try/catch
     * and return false (default value) in case of an error.
     *
     * @return bool
     */
    private function areExperimentalEndpointsEnabled(): bool
    {
        try {
            return $this->featureFlagStateChecker->isEnabled(FeatureFlagSettings::FEATURE_FLAG_ADMIN_API_EXPERIMENTAL_ENDPOINTS);
        } catch (\Throwable) {
            return false;
        }
    }
}
