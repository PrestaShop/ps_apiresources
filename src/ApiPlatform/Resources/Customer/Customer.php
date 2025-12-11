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

namespace PrestaShop\Module\APIResources\ApiPlatform\Resources\Customer;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use PrestaShop\PrestaShop\Core\Domain\Customer\Command\AddCustomerCommand;
use PrestaShop\PrestaShop\Core\Domain\Customer\Command\DeleteCustomerCommand;
use PrestaShop\PrestaShop\Core\Domain\Customer\Command\EditCustomerCommand;
use PrestaShop\PrestaShop\Core\Domain\Customer\Exception\CustomerConstraintException;
use PrestaShop\PrestaShop\Core\Domain\Customer\Exception\CustomerException;
use PrestaShop\PrestaShop\Core\Domain\Customer\Exception\CustomerNotFoundException;
use PrestaShop\PrestaShop\Core\Domain\Customer\Exception\DuplicateCustomerEmailException;
use PrestaShop\PrestaShop\Core\Domain\Customer\Query\GetCustomerForEditing;
use PrestaShop\PrestaShop\Core\Domain\Customer\ValueObject\CustomerDeleteMethod;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSCreate;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSDelete;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSGet;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSPartialUpdate;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    operations: [
        new CQRSCreate(
            uriTemplate: '/customers',
            CQRSCommand: AddCustomerCommand::class,
            CQRSQuery: GetCustomerForEditing::class,
            scopes: [
                'customer_write',
            ],
            CQRSQueryMapping: self::QUERY_MAPPING,
            CQRSCommandMapping: self::COMMAND_MAPPING,
            validationContext: ['groups' => ['Default', 'Create']],
        ),
        new CQRSGet(
            uriTemplate: '/customers/{customerId}',
            requirements: ['customerId' => '\d+'],
            CQRSQuery: GetCustomerForEditing::class,
            scopes: [
                'customer_read',
            ],
            CQRSQueryMapping: self::QUERY_MAPPING,
        ),
        new CQRSPartialUpdate(
            uriTemplate: '/customers/{customerId}',
            requirements: ['customerId' => '\d+'],
            read: false,
            CQRSCommand: EditCustomerCommand::class,
            CQRSCommandMapping: self::COMMAND_MAPPING,
            CQRSQuery: GetCustomerForEditing::class,
            CQRSQueryMapping: self::QUERY_MAPPING,
            scopes: [
                'customer_write',
            ],
        ),
        new CQRSDelete(
            uriTemplate: '/customers/{customerId}',
            CQRSCommand: DeleteCustomerCommand::class,
            scopes: [
                'customer_write',
            ],
            CQRSCommandMapping: self::DELETE_COMMAND_MAPPING,
            openapiContext: [
                'requestBody' => [
                    'required' => true,
                    'content' => [
                        'application/json' => [
                            'schema' => [
                                'type' => 'object',
                                'required' => ['deleteMethod'],
                                'properties' => [
                                    'deleteMethod' => [
                                        'type' => 'string',
                                        'enum' => [
                                            CustomerDeleteMethod::ALLOW_CUSTOMER_REGISTRATION,
                                            CustomerDeleteMethod::DENY_CUSTOMER_REGISTRATION,
                                        ],
                                        'description' => 'Method to use for customer deletion',
                                        'example' => CustomerDeleteMethod::ALLOW_CUSTOMER_REGISTRATION,
                                    ],
                                ],
                            ],
                            'example' => [
                                'deleteMethod' => 'allow_registration_after',
                            ],
                        ],
                    ],
                    'description' => 'Request body specifying the deletion method',
                ],
            ],
        ),
    ],
    normalizationContext: ['skip_null_values' => false],
    exceptionToStatus: [
        CustomerConstraintException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        CustomerNotFoundException::class => Response::HTTP_NOT_FOUND,
        DuplicateCustomerEmailException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        CustomerException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
    ],
)]
class Customer
{
    #[ApiProperty(identifier: true, openapiContext: ['type' => 'integer', 'example' => 1])]
    public int $customerId;

    #[Assert\NotBlank(groups: ['Create'])]
    public string $firstName;

    #[Assert\NotBlank(groups: ['Create'])]
    public string $lastName;

    #[Assert\NotBlank(groups: ['Create'])]
    #[Assert\Email(mode: Assert\Email::VALIDATION_MODE_STRICT)]
    public string $email;

    #[Assert\NotBlank(groups: ['Create'])]
    public string $password;

    #[Assert\NotBlank(groups: ['Create'])]
    #[ApiProperty(openapiContext: ['type' => 'integer', 'example' => 3])]
    public int $defaultGroupId;

    #[Assert\NotBlank(groups: ['Create'])]
    #[ApiProperty(openapiContext: ['type' => 'array', 'items' => ['type' => 'integer'], 'example' => [1, 3]])]
    public array $groupIds;

    #[ApiProperty(openapiContext: ['type' => 'integer', 'example' => 1])]
    public ?int $genderId;

    #[ApiProperty(openapiContext: ['type' => 'boolean', 'example' => true])]
    public bool $enabled;

    #[ApiProperty(openapiContext: ['type' => 'boolean', 'example' => false])]
    public bool $newsletterSubscribed;

    #[ApiProperty(openapiContext: ['type' => 'boolean', 'example' => false])]
    public bool $partnerOffersSubscribed;

    public ?string $birthday;

    public ?string $companyName;

    public ?string $siretCode;

    public ?string $apeCode;

    public ?string $website;

    #[ApiProperty(openapiContext: ['type' => 'number', 'format' => 'float', 'example' => 1000.5])]
    public ?float $allowedOutstandingAmount;

    #[ApiProperty(openapiContext: ['type' => 'integer', 'example' => 30])]
    public ?int $maxPaymentDays;

    #[ApiProperty(openapiContext: ['type' => 'integer', 'example' => 1])]
    public ?int $riskId;

    #[ApiProperty(openapiContext: ['type' => 'boolean', 'example' => false])]
    public bool $guest;

    public ?string $deleteMethod;

    public const QUERY_MAPPING = [
        '[id]' => '[customerId]',
    ];

    public const COMMAND_MAPPING = [
        '[_context][shopId]' => '[shopId]',
        '[partnerOffersSubscribed]' => '[isPartnerOffersSubscribed]',
        '[guest]' => '[isGuest]',
        '[enabled]' => '[isEnabled]',
    ];

    public const DELETE_COMMAND_MAPPING = [
        '[deleteMethod]' => '[deleteMethod]',
    ];
}
