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

namespace PrestaShop\Module\APIResources\ApiPlatform\Resources\CustomerThread;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use PrestaShop\PrestaShop\Core\Domain\CustomerService\Command\ReplyToCustomerThreadCommand;
use PrestaShop\PrestaShop\Core\Domain\CustomerService\Exception\CustomerServiceException;
use PrestaShop\PrestaShop\Core\Domain\CustomerService\Exception\CustomerThreadNotFoundException;
use PrestaShop\PrestaShop\Core\Domain\Employee\Exception\EmployeeNotFoundException;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSUpdate;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    operations: [
        new CQRSUpdate(
            uriTemplate: '/customer-threads/{customerThreadId}/messages',
            requirements: ['customerThreadId' => '\d+'],
            read: false,
            validationContext: ['groups' => ['Default', 'Reply']],
            output: false,
            CQRSCommand: ReplyToCustomerThreadCommand::class,
            scopes: [
                'customer_service_write',
            ],
        ),
    ],
    exceptionToStatus: [
        CustomerThreadNotFoundException::class => Response::HTTP_NOT_FOUND,
        EmployeeNotFoundException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        CustomerServiceException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
    ],
)]
class CustomerThreadReply
{
    #[ApiProperty(identifier: true)]
    public int $customerThreadId;

    #[Assert\NotBlank(groups: ['Reply'])]
    public string $replyMessage;

    /**
     * The employee the reply is attributed to: the Admin API has no logged-in employee.
     *
     * Passed to ReplyToCustomerThreadCommand by the cores that ship PrestaShop/PrestaShop#43302,
     * which also refuse an unknown employee. Without it, the core attributes the reply to the
     * context employee, which an API client does not have.
     */
    #[Assert\NotNull(groups: ['Reply'])]
    #[Assert\Positive(groups: ['Reply'])]
    public ?int $employeeId = null;
}
