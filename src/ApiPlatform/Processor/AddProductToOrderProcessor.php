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
use PrestaShop\Module\APIResources\ApiPlatform\Resources\Order\OrderProductAddition;
use PrestaShop\PrestaShop\Core\CommandBus\CommandBusInterface;
use PrestaShop\PrestaShop\Core\Domain\Order\Product\Command\AddProductToOrderCommand;

/**
 * AddProductToOrderCommand has a private constructor and only accepts
 * orderInvoiceId / hasFreeShipping through its named constructors
 * withNewInvoice() / toExistingInvoice(). The generic CommandProcessor
 * denormalizes into the constructor, which would silently drop those
 * two fields — so this endpoint uses a dedicated processor that routes
 * to the correct named constructor.
 */
class AddProductToOrderProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly CommandBusInterface $commandBus,
    ) {
    }

    /**
     * @param OrderProductAddition $data
     */
    public function process($data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        $orderId = (int) ($uriVariables['orderId'] ?? $data->orderId);

        if ($data->orderInvoiceId !== null && $data->orderInvoiceId > 0) {
            $command = AddProductToOrderCommand::toExistingInvoice(
                $orderId,
                $data->orderInvoiceId,
                $data->productId,
                $data->combinationId,
                (string) $data->productPriceTaxIncluded,
                (string) $data->productPriceTaxExcluded,
                $data->productQuantity
            );
        } else {
            $command = AddProductToOrderCommand::withNewInvoice(
                $orderId,
                $data->productId,
                $data->combinationId,
                (string) $data->productPriceTaxIncluded,
                (string) $data->productPriceTaxExcluded,
                $data->productQuantity,
                $data->hasFreeShipping
            );
        }

        $this->commandBus->handle($command);

        return null;
    }
}
