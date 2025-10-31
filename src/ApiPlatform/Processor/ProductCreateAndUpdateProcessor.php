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
use ApiPlatform\State\ProcessorInterface;
use PrestaShop\Module\APIResources\ApiPlatform\Resources\Product\Product as ApiProductResource;
use PrestaShop\PrestaShop\Core\CommandBus\CommandBusInterface;
use PrestaShop\PrestaShop\Core\Domain\Product\Command\UpdateProductCommand;
use PrestaShop\PrestaShop\Core\Domain\Product\Query\GetProductForEditing;
use PrestaShop\PrestaShop\Core\Domain\Shop\ValueObject\ShopConstraint;
use PrestaShopBundle\ApiPlatform\ContextParametersProvider;
use PrestaShopBundle\ApiPlatform\NormalizationMapper;
use PrestaShopBundle\ApiPlatform\Processor\CommandProcessor;
use PrestaShopBundle\ApiPlatform\Serializer\CQRSApiSerializer;
use Symfony\Component\HttpFoundation\Request;

/**
 * Processor for POST /product: chains AddProductCommand then UpdateProductCommand
 * to allow fields to be filled in a single call.
 */
class ProductCreateAndUpdateProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly CommandProcessor $commandProcessor,
        private readonly CommandBusInterface $commandBus,
        private readonly CQRSApiSerializer $serializer,
        private readonly ContextParametersProvider $contextParametersProvider,
    ) {
    }

    public function process($data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        // Run the native flow (AddProductCommand + optional CQRSQuery)
        $result = $this->commandProcessor->process($data, $operation, $uriVariables, $context);

        // Extract product ID from the returned DTO (Product)
        $productId = null;
        if (is_object($result) && property_exists($result, 'productId')) {
            $productId = (int) $result->productId;
        } elseif (is_array($result) && isset($result['productId'])) {
            $productId = (int) $result['productId'];
        }
        if (!$productId) {
            return $result;
        }

        // Denormalize and execute UpdateProductCommand via native serializer (mapping Product::UPDATE_MAPPING)
        $payload = $this->getRequestDataFromContext($context);
        if (!is_array($payload) || empty($payload)) {
            return $result;
        }
        $parameters = array_merge(
            $payload,
            ['productId' => $productId],
            $this->contextParametersProvider->getContextParameters(),
        );
        if (!isset($parameters['shopConstraint'])) {
            $shopId = $this->extractFirstShopId($payload) ?? (int) \Configuration::get('PS_SHOP_DEFAULT');
            $parameters['shopConstraint'] = ShopConstraint::shop($shopId);
        }

        $updateCommand = $this->serializer->denormalize(
            $parameters,
            UpdateProductCommand::class,
            null,
            [NormalizationMapper::NORMALIZATION_MAPPING => ApiProductResource::UPDATE_MAPPING]
        );
        $this->commandBus->handle($updateCommand);

        // Re-fetch updated data (GET) with the same CQRSQuery to return fresh values
        $queryParams = array_merge(
            ['productId' => $productId],
            $this->contextParametersProvider->getContextParameters(),
        );
        if (!isset($queryParams['shopConstraint'])) {
            $queryParams['shopConstraint'] = $parameters['shopConstraint'];
        }
        $cqrsQuery = $this->serializer->denormalize(
            $queryParams,
            GetProductForEditing::class,
            null,
            [NormalizationMapper::NORMALIZATION_MAPPING => ApiProductResource::QUERY_MAPPING]
        );
        $cqrsQueryResult = $this->commandBus->handle($cqrsQuery);

        return $this->serializer->denormalize(
            $this->serializer->normalize($cqrsQueryResult, null, [NormalizationMapper::NORMALIZATION_MAPPING => ApiProductResource::QUERY_MAPPING]),
            $operation->getClass(),
            null,
            [NormalizationMapper::NORMALIZATION_MAPPING => ApiProductResource::QUERY_MAPPING]
        );
    }

    protected function getRequestDataFromContext(array $context): array
    {
        if (isset($context['request']) && $context['request'] instanceof Request) {
            $content = $context['request']->getContent();
            if (!empty($content)) {
                $data = json_decode($content, true);
                if (is_array($data)) {
                    return $data;
                }
            }
        }

        return [];
    }

    protected function extractFirstShopId(array $payload): ?int
    {
        if (!empty($payload['shopIds']) && is_array($payload['shopIds'])) {
            foreach ($payload['shopIds'] as $val) {
                $id = (int) $val;
                if ($id > 0) {
                    return $id;
                }
            }
        }

        return null;
    }
}
