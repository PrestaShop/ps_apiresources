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

namespace PrestaShop\Module\APIResources\ApiPlatform\Resources\Employee;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use PrestaShop\PrestaShop\Core\Domain\Employee\Command\AddEmployeeCommand;
use PrestaShop\PrestaShop\Core\Domain\Employee\Command\DeleteEmployeeCommand;
use PrestaShop\PrestaShop\Core\Domain\Employee\Command\EditEmployeeCommand;
use PrestaShop\PrestaShop\Core\Domain\Employee\Exception\EmployeeNotFoundException;
use PrestaShop\PrestaShop\Core\Domain\Employee\Query\GetEmployeeForEditing;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSCreate;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSDelete;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSGet;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSUpdate;
use Symfony\Component\HttpFoundation\Response;

#[ApiResource(
    operations: [
        new CQRSGet(
            uriTemplate: '/employees/{employeeId}',
            CQRSQuery: GetEmployeeForEditing::class,
            scopes: [
                'employee_read',
            ],
            CQRSQueryMapping: self::QUERY_MAPPING,
        ),
        new CQRSCreate(
            uriTemplate: '/employees',
            CQRSCommand: AddEmployeeCommand::class,
            CQRSQuery: GetEmployeeForEditing::class,
            scopes: [
                'employee_write',
            ],
            CQRSQueryMapping: self::QUERY_MAPPING,
            CQRSCommandMapping: self::COMMAND_MAPPING,
        ),
        new CQRSUpdate(
            uriTemplate: '/employees/{employeeId}',
            CQRSCommand: EditEmployeeCommand::class,
            CQRSQuery: GetEmployeeForEditing::class,
            scopes: [
                'employee_write',
            ],
            CQRSQueryMapping: self::QUERY_MAPPING,
            CQRSCommandMapping: self::COMMAND_MAPPING,
        ),
        new CQRSDelete(
            uriTemplate: '/employees/{employeeId}',
            requirements: ['featureId' => '\d+'],
            CQRSCommand: DeleteEmployeeCommand::class,
            scopes: [
                'employee_write',
            ],
        ),
    ],
    exceptionToStatus: [
        EmployeeNotFoundException::class => Response::HTTP_NOT_FOUND,
    ],
)]
class Employee
{
    public const COMMAND_MAPPING = [
        '[employee_id]' => '[employeeId]',
    ];

    public const QUERY_MAPPING = [
        '[employee_id][value]' => '[employeeId]',
    ];

    #[ApiProperty(identifier: true)]
    public int $employeeId;
    public string $firstName;
    public string $lastName;
    public string $email;
    public string $avatarUrl;
    public int $defaultPageId;
    public int $languageId;
    public bool $enabled;
    public int $profileId;
    public array $shopAssociation;
    public bool $hasEnabledGravatar;
}
