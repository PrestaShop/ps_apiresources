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
use PrestaShop\PrestaShop\Core\Domain\Employee\Exception\EmployeeNotFoundException;
use PrestaShop\PrestaShop\Core\Search\Filters\EmployeeFilters;
use PrestaShopBundle\ApiPlatform\Metadata\PaginatedList;
use Symfony\Component\HttpFoundation\Response;

#[ApiResource(
    operations: [
        new PaginatedList(
            uriTemplate: '/employees',
            scopes: ['employee_read'],
            ApiResourceMapping: [
                '[id_employee]' => '[employeeId]',
            ],
            gridDataFactory: 'prestashop.core.grid.data.factory.employee',
            filtersClass: EmployeeFilters::class,
            filtersMapping: [
                '[employeeId]' => '[employee_id]',
            ]
        ),
    ],
    exceptionToStatus: [
        EmployeeNotFoundException::class => Response::HTTP_NOT_FOUND,
    ],
)]
class EmployeeList
{
    #[ApiProperty(identifier: true)]
    public int $employeeId;
}
