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
use ApiPlatform\State\ProviderInterface;
use PrestaShopBundle\ApiPlatform\Exception\CQRSQueryNotFoundException;
use PrestaShopBundle\ApiPlatform\NormalizationMapper;
use PrestaShopBundle\ApiPlatform\Provider\QueryProvider;
use PrestaShopBundle\ApiPlatform\QueryResultSerializerTrait;
use Symfony\Component\Serializer\Exception\ExceptionInterface;

/**
 * Same as core QueryProvider, but we inject uri_variables into the serializer context
 * during denormalization so we can map the productId from the route.
 */
class SearchProductCombinationsProvider extends QueryProvider implements ProviderInterface
{
    use QueryResultSerializerTrait;

    // Use parent constructor via autowiring

    /**
     * @throws ExceptionInterface
     * @throws \ReflectionException
     */
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
        if (null === $CQRSQueryResult) {
            return new ($operation->getClass())();
        }

        // Normalize QueryResult first (apply CQRSQueryMapping if needed)
        $normalizedQueryResult = $this->domainSerializer->normalize($CQRSQueryResult, null, [NormalizationMapper::NORMALIZATION_MAPPING => $this->getCQRSQueryMapping($operation)]);

        // Denormalize into ApiResource DTO, but inject uriVariables so ApiResourceMapping can copy productId
        return $this->domainSerializer->denormalize(
            $normalizedQueryResult,
            $operation->getClass(),
            null,
            [
                NormalizationMapper::NORMALIZATION_MAPPING => $this->getApiResourceMapping($operation),
                'uri_variables' => $uriVariables,
                'operation' => $operation,
            ]
        );
    }
}
