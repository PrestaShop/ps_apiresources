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
use PrestaShop\PrestaShop\Core\Domain\Employee\Command\DeleteEmployeeCommand;
use PrestaShop\PrestaShop\Core\Domain\Employee\Command\EditEmployeeCommand;
use PrestaShop\PrestaShop\Core\Domain\Employee\Command\ToggleEmployeeStatusCommand;
use PrestaShop\PrestaShop\Core\Domain\Employee\Exception\CannotDeleteEmployeeException;
use PrestaShop\PrestaShop\Core\Domain\Employee\Exception\EmailAlreadyUsedException;
use PrestaShop\PrestaShop\Core\Domain\Employee\Exception\EmployeeCannotChangeItselfException;
use PrestaShop\PrestaShop\Core\Domain\Employee\Exception\EmployeeConstraintException;
use PrestaShop\PrestaShop\Core\Domain\Employee\Exception\EmployeeNotFoundException;
use PrestaShop\PrestaShop\Core\Domain\Employee\Exception\InvalidProfileException;
use PrestaShop\PrestaShop\Core\Domain\Employee\Exception\MissingShopAssociationException;
use PrestaShop\PrestaShop\Core\Domain\Employee\Query\GetEmployeeForEditing;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSDelete;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSGet;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSPartialUpdate;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSUpdate;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    operations: [
        new CQRSGet(
            uriTemplate: '/employees/{employeeId}',
            requirements: ['employeeId' => '\d+'],
            CQRSQuery: GetEmployeeForEditing::class,
            scopes: ['employee_read'],
            CQRSQueryMapping: self::QUERY_MAPPING,
        ),
        new CQRSPartialUpdate(
            uriTemplate: '/employees/{employeeId}',
            requirements: ['employeeId' => '\d+'],
            CQRSCommand: EditEmployeeCommand::class,
            CQRSQuery: GetEmployeeForEditing::class,
            scopes: ['employee_write'],
            CQRSQueryMapping: self::QUERY_MAPPING,
            CQRSCommandMapping: self::EDIT_COMMAND_MAPPING,
        ),
        new CQRSDelete(
            uriTemplate: '/employees/{employeeId}',
            requirements: ['employeeId' => '\d+'],
            CQRSCommand: DeleteEmployeeCommand::class,
            scopes: ['employee_write'],
        ),
        new CQRSUpdate(
            uriTemplate: '/employees/{employeeId}/toggle-status',
            requirements: ['employeeId' => '\d+'],
            output: false,
            CQRSCommand: ToggleEmployeeStatusCommand::class,
            scopes: ['employee_write'],
        ),
    ],
    exceptionToStatus: [
        EmployeeConstraintException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        EmployeeNotFoundException::class => Response::HTTP_NOT_FOUND,
        CannotDeleteEmployeeException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        EmailAlreadyUsedException::class => Response::HTTP_CONFLICT,
        InvalidProfileException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        MissingShopAssociationException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        EmployeeCannotChangeItselfException::class => Response::HTTP_FORBIDDEN,
    ],
)]
class Employee
{
    #[ApiProperty(identifier: true)]
    public int $employeeId;

    #[Assert\NotBlank]
    public string $firstName;

    #[Assert\NotBlank]
    public string $lastName;

    #[Assert\Email]
    public string $email;

    public int $defaultPageId;

    public int $languageId;

    public bool $enabled;

    public int $profileId;

    /**
     * @var int[]
     */
    public array $shopIds;

    public string $avatarUrl;

    public bool $hasEnabledGravatar;

    public const QUERY_MAPPING = [
        '[isActive]' => '[active]',
        '[shopAssociation]' => '[shopIds]',
    ];

    public const EDIT_COMMAND_MAPPING = [
        '[shopIds]' => '[shopAssociation]',
    ];
}
