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
use PrestaShop\PrestaShop\Core\Domain\Employee\Exception\EmployeeException;
use PrestaShop\PrestaShop\Core\Domain\Employee\Exception\EmployeeNotFoundException;
use PrestaShop\PrestaShop\Core\Domain\Employee\Query\GetEmployeeForEditing;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSCreate;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSDelete;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSGet;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSPartialUpdate;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    operations: [
        new CQRSGet(
            uriTemplate: '/employees/{employeeId}',
            requirements: ['employeeId' => '\d+'],
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
            validationContext: ['groups' => ['Default', 'Create']],
        ),
        new CQRSPartialUpdate(
            uriTemplate: '/employees/{employeeId}',
            requirements: ['employeeId' => '\d+'],
            read: false,
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
            requirements: ['employeeId' => '\d+'],
            CQRSCommand: DeleteEmployeeCommand::class,
            scopes: [
                'employee_write',
            ],
        ),
    ],
    exceptionToStatus: [
        EmployeeException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        EmployeeNotFoundException::class => Response::HTTP_NOT_FOUND,
    ],
)]
class Employee
{
    public const COMMAND_MAPPING = [
        '[employee_id]' => '[employeeId]',
        '[enabled]' => '[active]',
    ];

    public const QUERY_MAPPING = [
        '[employee_id][value]' => '[employeeId]',
        '[active]' => '[enabled]',
    ];

    #[ApiProperty(identifier: true, openapiContext: ['type' => 'integer', 'example' => 1])]
    public int $employeeId;

    #[Assert\NotBlank(groups: ['Create'])]
    public string $firstName;

    #[Assert\NotBlank(groups: ['Create'])]
    public string $lastName;

    #[Assert\NotBlank(groups: ['Create'])]
    #[Assert\Email(mode: Assert\Email::VALIDATION_MODE_STRICT)]
    public string $email;

    /**
     * Write only: GetEmployeeForEditing never returns a password, and this resource is also
     * served by the GET and the list, so the property must never be normalized back out.
     */
    #[Assert\NotBlank(groups: ['Create'])]
    #[ApiProperty(readable: false)]
    public string $password;

    #[Assert\NotBlank(groups: ['Create'])]
    #[ApiProperty(openapiContext: ['type' => 'integer', 'example' => 1])]
    public int $defaultPageId;

    #[Assert\NotBlank(groups: ['Create'])]
    #[ApiProperty(openapiContext: ['type' => 'integer', 'example' => 1])]
    public int $languageId;

    #[ApiProperty(openapiContext: ['type' => 'boolean', 'example' => true])]
    public bool $enabled;

    #[Assert\NotBlank(groups: ['Create'])]
    #[ApiProperty(openapiContext: ['type' => 'integer', 'example' => 1])]
    public int $profileId;

    #[ApiProperty(openapiContext: ['type' => 'array', 'items' => ['type' => 'integer'], 'example' => [1]])]
    public array $shopAssociation;

    #[ApiProperty(openapiContext: ['type' => 'boolean', 'example' => false])]
    public bool $hasEnabledGravatar;

    public ?string $avatarUrl = null;
}
