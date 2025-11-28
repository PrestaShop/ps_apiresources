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
 * Ensures uriVariables (productId) are available when building the return query
 * after generating combinations, so the response includes the right productId.
 */
class GenerateProductCombinationsProcessor extends BaseCommandProcessor
{
    /**
     * {@inheritdoc}
     */
    protected function denormalizeCommandResult(mixed $commandResult, Operation $operation, array $uriVariables): mixed
    {
        $queryClass = $this->getCQRSQueryClass($operation);
        if (!$queryClass) {
            return $this->domainSerializer->denormalize($commandResult, $operation->getClass(), null, [NormalizationMapper::NORMALIZATION_MAPPING => $this->getApiResourceMapping($operation)]);
        }

        // Merge uriVariables so [productId] mapping is always present
        $normalizedCommandResult = array_merge($commandResult ?? [], $uriVariables, $this->contextParametersProvider->getContextParameters());

        return $this->handleCQRSQueryAndReturnResult($queryClass, $normalizedCommandResult, $operation);
    }
}
