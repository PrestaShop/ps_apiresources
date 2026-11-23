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

namespace PrestaShop\Module\APIResources\ApiPlatform\Resources\Employee;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use PrestaShop\PrestaShop\Core\Domain\Employee\Command\BulkDeleteEmployeeCommand;
use PrestaShop\PrestaShop\Core\Domain\Employee\Exception\EmployeeException;
use PrestaShop\PrestaShop\Core\Domain\Employee\Exception\EmployeeNotFoundException;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSDelete;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    operations: [
        new CQRSDelete(
            uriTemplate: '/employees/bulk-delete',
            CQRSCommand: BulkDeleteEmployeeCommand::class,
            scopes: ['employee_write'],
            allowEmptyBody: false,
            openapi: new OpenApiOperation(
                summary: 'Delete several employees.',
                description: 'Deletes the listed employees in order. The first employee that cannot be deleted (unknown: 404; '
                    . 'the only SuperAdmin of the shop: 422) stops the request: the employees listed '
                    . 'before it are deleted, the ones after it are kept. Before PrestaShop 9.2.2 '
                    . '(PrestaShop/PrestaShop#43276), the core reads the employee of the back office context, which an '
                    . 'API client does not have: this operation answers 500 there.',
            ),
        ),
    ],
    exceptionToStatus: [
        // The specific one first: AdminEmployeeException, EmployeeCannotChangeItselfException and
        // InvalidEmployeeIdException extend EmployeeException
        EmployeeNotFoundException::class => Response::HTTP_NOT_FOUND,
        EmployeeException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
    ],
)]
class BulkEmployees
{
    /**
     * Checked one by one: the command casts each id with (int), "3abc" would act on employee 3.
     *
     * @var int[]
     */
    #[ApiProperty(openapiContext: ['example' => [1, 3]])]
    #[Assert\NotBlank]
    #[Assert\All([
        new Assert\Type('integer'),
        new Assert\Positive(),
    ])]
    public array $employeeIds;
}
