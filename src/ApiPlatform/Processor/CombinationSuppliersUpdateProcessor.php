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

namespace PrestaShop\Module\APIResources\ApiPlatform\Processor;

use ApiPlatform\Metadata\Operation;
use PrestaShopBundle\ApiPlatform\NormalizationMapper;
use PrestaShopBundle\ApiPlatform\Processor\CommandProcessor as BaseCommandProcessor;

/**
 * Custom processor for combination suppliers update that forces returning the fresh list by
 * re-running the configured CQRSQuery using uriVariables (so combinationId is present).
 */
class CombinationSuppliersUpdateProcessor extends BaseCommandProcessor
{
    protected function denormalizeCommandResult(mixed $commandResult, Operation $operation, array $uriVariables): mixed
    {
        // Ignore command result to ensure the subsequent Query gets proper identifiers from URI
        $normalizedCommandResult = $uriVariables + $this->contextParametersProvider->getContextParameters();

        $queryClass = $this->getCQRSQueryClass($operation);
        if (!$queryClass) {
            return $this->denormalizeApiPlatformDTO($normalizedCommandResult, $operation);
        }

        // Build and run CQRS query
        $CQRSQuery = $this->domainSerializer->denormalize($normalizedCommandResult, $queryClass, null, [NormalizationMapper::NORMALIZATION_MAPPING => $this->getCQRSQueryMapping($operation)]);
        $CQRSQueryResult = $this->commandBus->handle($CQRSQuery);

        // Manually denormalize as a collection of ApiResource DTOs
        $normalizedQueryResult = $this->domainSerializer->normalize($CQRSQueryResult, null, [NormalizationMapper::NORMALIZATION_MAPPING => $this->getCQRSQueryMapping($operation)]);
        if (!\is_array($normalizedQueryResult)) {
            return [];
        }

        $apiResourceClass = $operation->getClass();
        $apiResourceMapping = $this->getApiResourceMapping($operation);
        foreach ($normalizedQueryResult as $key => $item) {
            $normalizedQueryResult[$key] = $this->domainSerializer->denormalize($item, $apiResourceClass, null, [NormalizationMapper::NORMALIZATION_MAPPING => $apiResourceMapping]);
        }

        return $normalizedQueryResult;
    }
}
