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
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use PrestaShop\PrestaShop\Core\Domain\Employee\Command\DeleteEmployeeCommand;
use PrestaShop\PrestaShop\Core\Domain\Employee\Command\EditEmployeeCommand;
use PrestaShop\PrestaShop\Core\Domain\Employee\Exception\EmployeeException;
use PrestaShop\PrestaShop\Core\Domain\Employee\Exception\EmployeeNotFoundException;
use PrestaShop\PrestaShop\Core\Domain\Employee\Query\GetEmployeeForEditing;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSDelete;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSGet;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSPartialUpdate;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * The employee resource has no create operation, and no password property, on purpose.
 *
 * `AddEmployeeCommand::__construct()` and `EditEmployeeCommand::setPlainPassword()` both build
 * the `Password` value object themselves and therefore require `$minLength`, `$maxLength` and
 * `$minScore` — the shop's password policy, which the container injects into
 * `EmployeeFormDataHandler` from `PasswordPolicyConfiguration`. The CQRS normalizer builds the
 * command from the request payload alone, so those three bounds could only reach it by being
 * declared as writable properties, which would let a client relax the policy on the way in.
 *
 * Creating an employee, and setting a password, therefore need a core change first: either the
 * handler builds the `Password` from the injected policy, or the policy joins the parameters
 * `ContextParametersProvider` already exposes under `_context`.
 */
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
            openapi: new OpenApiOperation(
                summary: 'Update an employee.',
                description: 'Updates the fields sent and returns the updated employee. Before PrestaShop 9.2.2 '
                    . '(PrestaShop/PrestaShop#43276), the core overwrites every field with the value of the request: '
                    . 'send the whole representation (firstName, lastName, email, defaultPageId, languageId, profileId, '
                    . 'shopAssociation, hasEnabledGravatar, enabled), a request without the email answers 500, and an '
                    . 'unknown employeeId answers 500 or 422 instead of 404.',
            ),
        ),
        new CQRSDelete(
            uriTemplate: '/employees/{employeeId}',
            requirements: ['employeeId' => '\d+'],
            CQRSCommand: DeleteEmployeeCommand::class,
            scopes: [
                'employee_write',
            ],
            openapi: new OpenApiOperation(
                summary: 'Delete an employee.',
                description: 'Deletes the employee. The only SuperAdmin of the shop cannot be deleted (422). Before PrestaShop 9.2.2 '
                    . '(PrestaShop/PrestaShop#43276), the core reads the employee of the back office context, which an '
                    . 'API client does not have: this operation answers 500 there.',
            ),
        ),
    ],
    exceptionToStatus: [
        // The specific one first: ApiPlatform keeps the first entry the exception is an
        // instance of, and EmployeeNotFoundException extends EmployeeException
        EmployeeNotFoundException::class => Response::HTTP_NOT_FOUND,
        EmployeeException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
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
        // EditableEmployee::hasEnabledGravatar() is read as "enabledGravatar"
        '[enabledGravatar]' => '[hasEnabledGravatar]',
    ];

    #[ApiProperty(identifier: true)]
    public int $employeeId;

    public string $firstName;

    public string $lastName;

    #[Assert\Email(mode: Assert\Email::VALIDATION_MODE_STRICT)]
    public string $email;

    #[ApiProperty(openapiContext: ['description' => 'Back office page (tab) id the employee lands on.', 'example' => 1])]
    public int $defaultPageId;

    #[ApiProperty(openapiContext: ['example' => 1])]
    public int $languageId;

    public bool $enabled;

    #[ApiProperty(openapiContext: ['example' => 1])]
    public int $profileId;

    /**
     * @var int[]
     */
    #[ApiProperty(openapiContext: ['description' => 'Ids of the shops the employee can access.', 'example' => [1]])]
    public array $shopAssociation;

    public bool $hasEnabledGravatar;

    public ?string $avatarUrl = null;
}
