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

namespace PrestaShop\Module\APIResources\ApiPlatform\Processor;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use PrestaShop\Module\APIResources\ApiPlatform\Resources\Customer\BulkCustomers;
use PrestaShop\PrestaShop\Core\Domain\Customer\Command\BulkDeleteCustomerCommand;
use PrestaShop\PrestaShop\Core\Domain\Customer\ValueObject\CustomerDeleteMethod;
use PrestaShopBundle\ApiPlatform\Processor\CommandProcessor;

/**
 * Custom processor for BulkCustomers DELETE operation that adds default deleteMethod if not provided
 */
class BulkCustomersDeleteProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly CommandProcessor $commandProcessor,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        $CQRSCommandClass = $operation->getExtraProperties()['CQRSCommand'] ?? null;
        if ($CQRSCommandClass === BulkDeleteCustomerCommand::class) {
            // If deleteMethod is not provided, add default value
            if ($data instanceof BulkCustomers) {
                if ($data->deleteMethod === null || $data->deleteMethod === '') {
                    $data->deleteMethod = CustomerDeleteMethod::ALLOW_CUSTOMER_REGISTRATION;
                }
            } elseif (is_array($data)) {
                if (!isset($data['deleteMethod']) || $data['deleteMethod'] === null || $data['deleteMethod'] === '') {
                    $data['deleteMethod'] = CustomerDeleteMethod::ALLOW_CUSTOMER_REGISTRATION;
                }
            }
        }

        return $this->commandProcessor->process($data, $operation, $uriVariables, $context);
    }
}
