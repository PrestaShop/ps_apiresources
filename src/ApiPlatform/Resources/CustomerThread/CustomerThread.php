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

namespace PrestaShop\Module\APIResources\ApiPlatform\Resources\CustomerThread;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use PrestaShop\PrestaShop\Core\Domain\CustomerService\Command\DeleteCustomerThreadCommand;
use PrestaShop\PrestaShop\Core\Domain\CustomerService\Command\UpdateCustomerThreadStatusCommand;
use PrestaShop\PrestaShop\Core\Domain\CustomerService\Exception\CustomerServiceException;
use PrestaShop\PrestaShop\Core\Domain\CustomerService\Exception\CustomerThreadNotFoundException;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSDelete;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSUpdate;
use Symfony\Component\HttpFoundation\Response;

#[ApiResource(
    operations: [
        new CQRSUpdate(
            uriTemplate: '/customer-threads/{customerThreadId}/set-status',
            requirements: ['customerThreadId' => '\d+'],
            output: false,
            CQRSCommand: UpdateCustomerThreadStatusCommand::class,
            CQRSCommandMapping: self::COMMAND_MAPPING,
            scopes: ['customer_service_write'],
        ),
        new CQRSDelete(
            uriTemplate: '/customer-threads/{customerThreadId}',
            requirements: ['customerThreadId' => '\d+'],
            output: false,
            CQRSCommand: DeleteCustomerThreadCommand::class,
            scopes: ['customer_service_write'],
        ),
    ],
    exceptionToStatus: [
        CustomerThreadNotFoundException::class => Response::HTTP_NOT_FOUND,
        CustomerServiceException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
    ],
)]
class CustomerThread
{
    #[ApiProperty(identifier: true)]
    public int $customerThreadId;

    /**
     * One of open, closed, pending1, pending2.
     */
    public string $status;

    /**
     * UpdateCustomerThreadStatusCommand expects a $newCustomerThreadStatus constructor argument.
     */
    public const COMMAND_MAPPING = [
        '[status]' => '[newCustomerThreadStatus]',
    ];
}
