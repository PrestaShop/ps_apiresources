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

namespace PrestaShop\Module\APIResources\ApiPlatform\Provider;

use ApiPlatform\Metadata\Operation;
use PrestaShop\PrestaShop\Core\Domain\Product\SpecificPrice\QueryResult\SpecificPriceList;
use PrestaShopBundle\ApiPlatform\Exception\CQRSQueryNotFoundException;
use PrestaShopBundle\ApiPlatform\NormalizationMapper;
use PrestaShopBundle\ApiPlatform\Provider\QueryProvider;

/**
 * Custom provider for SpecificPriceList that extracts the specificPrices array
 * from the SpecificPriceList object before denormalization
 */
class SpecificPriceListProvider extends QueryProvider
{
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array|object|null
    {
        $CQRSQueryClass = $this->getCQRSQueryClass($operation);
        if (null === $CQRSQueryClass) {
            throw new CQRSQueryNotFoundException(sprintf('Resource %s has no CQRS query defined.', $operation->getClass()));
        }

        $filters = $context['filters'] ?? [];
        $queryParameters = array_merge($uriVariables, $filters, $this->contextParametersProvider->getContextParameters());

        $CQRSQuery = $this->domainSerializer->denormalize($queryParameters, $CQRSQueryClass, null, [NormalizationMapper::NORMALIZATION_MAPPING => $this->getCQRSQueryMapping($operation)]);
        $CQRSQueryResult = $this->queryBus->handle($CQRSQuery);

        // If the result is a SpecificPriceList object, extract the specificPrices array
        if ($CQRSQueryResult instanceof SpecificPriceList) {
            $CQRSQueryResult = $CQRSQueryResult->getSpecificPrices();
        }

        // The result may be null (for DELETE action for example)
        if (null === $CQRSQueryResult) {
            return new ($operation->getClass())();
        }

        $denormalizedResult = $this->denormalizeQueryResult($CQRSQueryResult, $operation);

        // Add productId from uriVariables to each item in the collection
        // This ensures consistency with POST/PATCH responses that include productId
        if (is_array($denormalizedResult) && isset($uriVariables['productId'])) {
            foreach ($denormalizedResult as $key => $item) {
                if (is_object($item)) {
                    $item->productId = (int) $uriVariables['productId'];
                } elseif (is_array($item)) {
                    $denormalizedResult[$key]['productId'] = (int) $uriVariables['productId'];
                }
            }
        }

        return $denormalizedResult;
    }
}
