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
use PrestaShop\PrestaShop\Adapter\Product\Combination\Repository\CombinationRepository;
use PrestaShop\PrestaShop\Adapter\Product\Stock\Repository\StockAvailableRepository;
use PrestaShop\PrestaShop\Core\CommandBus\CommandBusInterface;
use PrestaShop\PrestaShop\Core\Domain\Product\Combination\ValueObject\CombinationId;
use PrestaShop\PrestaShop\Core\Domain\Shop\ValueObject\ShopId;
use PrestaShopBundle\ApiPlatform\ContextParametersProvider;
use PrestaShopBundle\ApiPlatform\Exception\CQRSCommandNotFoundException;
use PrestaShopBundle\ApiPlatform\NormalizationMapper;
use PrestaShopBundle\ApiPlatform\Processor\CommandProcessor as BaseCommandProcessor;
use PrestaShopBundle\ApiPlatform\Serializer\CQRSApiSerializer;

/**
 * After updating stock, return a minimal payload with current quantity and location
 */
class CombinationStockUpdateProcessor extends BaseCommandProcessor
{
    /**
     * Execute command then return minimal payload { combinationId, quantity, location } regardless of command result.
     */
    public function process($data, Operation $operation, array $uriVariables = [], array $context = [])
    {
        $CQRSCommandClass = $this->getCQRSCommandClass($operation);
        if (null === $CQRSCommandClass || !class_exists($CQRSCommandClass)) {
            throw new CQRSCommandNotFoundException(sprintf('Resource %s has no CQRS command defined.', $operation->getClass()));
        }

        if ($data instanceof $CQRSCommandClass) {
            $command = $data;
        } else {
            $normalizedApiResourceDTO = $this->domainSerializer->normalize($data, null, [NormalizationMapper::NORMALIZATION_MAPPING => $this->getApiResourceMapping($operation)]);
            $commandParameters = array_merge($normalizedApiResourceDTO, $uriVariables, $this->contextParametersProvider->getContextParameters());
            $command = $this->domainSerializer->denormalize($commandParameters, $CQRSCommandClass, null, [NormalizationMapper::NORMALIZATION_MAPPING => $this->getCQRSCommandMapping($operation)]);
        }

        // Run command (ignore result value)
        $this->commandBus->handle($command);

        // Build minimal response
        return $this->denormalizeCommandResult(null, $operation, $uriVariables);
    }

    public function __construct(
        CommandBusInterface $commandBus,
        CQRSApiSerializer $domainSerializer,
        ContextParametersProvider $contextParametersProvider,
        StockAvailableRepository $stockAvailableRepository,
        CombinationRepository $combinationRepository,
    ) {
        parent::__construct($commandBus, $domainSerializer, $contextParametersProvider);
        $this->stockAvailableRepository = $stockAvailableRepository;
        $this->combinationRepository = $combinationRepository;
    }

    private StockAvailableRepository $stockAvailableRepository;
    private CombinationRepository $combinationRepository;

    protected function denormalizeCommandResult(mixed $commandResult, Operation $operation, array $uriVariables): mixed
    {
        // Execute the command as usual (already done upstream), then compute minimal payload
        $combinationId = (int) ($uriVariables['combinationId'] ?? 0);
        $contextParams = $this->contextParametersProvider->getContextParameters();
        $shopId = (int) ($contextParams['shopId'] ?? 0);

        $quantity = null;
        $location = '';
        try {
            if ($combinationId > 0) {
                if ($shopId > 0) {
                    $stockAvailable = $this->stockAvailableRepository->getForCombination(new CombinationId($combinationId), new ShopId($shopId));
                    $quantity = (int) $stockAvailable->quantity;
                    $location = (string) $stockAvailable->location;
                } else {
                    // Fallback: find any shop stock for this combination
                    $productId = $this->combinationRepository->getProductId(new CombinationId($combinationId));
                    $stockIds = $this->stockAvailableRepository->getAllShopsStockIds($productId, new CombinationId($combinationId));
                    if (!empty($stockIds)) {
                        $stockAvailable = $this->stockAvailableRepository->get($stockIds[0]);
                        $quantity = (int) $stockAvailable->quantity;
                        $location = (string) $stockAvailable->location;
                    }
                }
            }
        } catch (\Throwable $e) {
            // Ignore and fallback to minimal payload
        }

        $class = $operation->getClass();
        $dto = new $class();
        // set public properties
        $dto->combinationId = $combinationId;
        if (property_exists($dto, 'quantity')) {
            $dto->quantity = $quantity;
        }
        if (property_exists($dto, 'location')) {
            $dto->location = $location;
        }

        return $dto;
    }
}
