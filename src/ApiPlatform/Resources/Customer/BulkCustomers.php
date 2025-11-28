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
use PrestaShop\Module\APIResources\ApiPlatform\Processor\BulkCustomersDeleteProcessor;
use PrestaShop\PrestaShop\Core\Domain\Customer\Command\BulkDeleteCustomerCommand;
use PrestaShop\PrestaShop\Core\Domain\Customer\Command\BulkDisableCustomerCommand;
use PrestaShop\PrestaShop\Core\Domain\Customer\Command\BulkEnableCustomerCommand;
use PrestaShop\PrestaShop\Core\Domain\Customer\Exception\CustomerConstraintException;
use PrestaShop\PrestaShop\Core\Domain\Customer\Exception\CustomerNotFoundException;
use PrestaShop\PrestaShop\Core\Domain\Customer\ValueObject\CustomerDeleteMethod;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSDelete;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSUpdate;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    operations: [
        new CQRSDelete(
            uriTemplate: '/customers/bulk-delete',
            CQRSCommand: BulkDeleteCustomerCommand::class,
            scopes: [
                'customer_write',
            ],
            CQRSCommandMapping: self::COMMAND_MAPPING,
            validationContext: ['groups' => ['Default', 'BulkDelete']],
            processor: BulkCustomersDeleteProcessor::class,
            allowEmptyBody: false,
            openapiContext: [
                'requestBody' => [
                    'required' => true,
                    'content' => [
                        'application/json' => [
                            'schema' => [
                                'type' => 'object',
                                'required' => ['customerIds'],
                                'properties' => [
                                    'customerIds' => [
                                        'type' => 'array',
                                        'items' => ['type' => 'integer'],
                                    ],
                                    'deleteMethod' => [
                                        'type' => 'string',
                                        'enum' => [
                                            CustomerDeleteMethod::ALLOW_CUSTOMER_REGISTRATION,
                                            CustomerDeleteMethod::DENY_CUSTOMER_REGISTRATION,
                                        ],
                                        'description' => 'Method to use for customer deletion. Default: allow_registration_after',
                                    ],
                                ],
                            ],
                            'example' => [
                                'customerIds' => [1, 2, 3],
                                'deleteMethod' => 'allow_registration_after',
                            ],
                        ],
                    ],
                ],
            ],
        ),
        new CQRSUpdate(
            uriTemplate: '/customers/bulk-disable',
            // No output 204 code
            output: false,
            CQRSCommand: BulkDisableCustomerCommand::class,
            scopes: [
                'customer_write',
            ],
            CQRSCommandMapping: self::ENABLE_DISABLE_COMMAND_MAPPING,
            validationContext: ['groups' => ['Default', 'BulkDisable']],
            openapiContext: [
                'requestBody' => [
                    'required' => true,
                    'content' => [
                        'application/json' => [
                            'schema' => [
                                'type' => 'object',
                                'required' => ['customerIds'],
                                'properties' => [
                                    'customerIds' => [
                                        'type' => 'array',
                                        'items' => ['type' => 'integer'],
                                    ],
                                ],
                            ],
                            'example' => [
                                'customerIds' => [1, 2, 3],
                            ],
                        ],
                    ],
                ],
            ],
        ),
        new CQRSUpdate(
            uriTemplate: '/customers/bulk-enable',
            // No output 204 code
            output: false,
            CQRSCommand: BulkEnableCustomerCommand::class,
            scopes: [
                'customer_write',
            ],
            CQRSCommandMapping: self::ENABLE_DISABLE_COMMAND_MAPPING,
            validationContext: ['groups' => ['Default', 'BulkEnable']],
            openapiContext: [
                'requestBody' => [
                    'required' => true,
                    'content' => [
                        'application/json' => [
                            'schema' => [
                                'type' => 'object',
                                'required' => ['customerIds'],
                                'properties' => [
                                    'customerIds' => [
                                        'type' => 'array',
                                        'items' => ['type' => 'integer'],
                                    ],
                                ],
                            ],
                            'example' => [
                                'customerIds' => [1, 2, 3],
                            ],
                        ],
                    ],
                ],
            ],
        ),
    ],
    exceptionToStatus: [
        CustomerConstraintException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        CustomerNotFoundException::class => Response::HTTP_NOT_FOUND,
    ],
)]
class BulkCustomers
{
    /**
     * @var int[]
     */
    #[ApiProperty(openapiContext: ['type' => 'array', 'items' => ['type' => 'integer'], 'example' => [1, 2, 3]])]
    #[Assert\NotBlank(groups: ['BulkDelete', 'BulkEnable', 'BulkDisable'])]
    public array $customerIds;

    #[ApiProperty(openapiContext: [
        'type' => 'string',
        'enum' => [
            CustomerDeleteMethod::ALLOW_CUSTOMER_REGISTRATION,
            CustomerDeleteMethod::DENY_CUSTOMER_REGISTRATION,
        ],
        'example' => CustomerDeleteMethod::ALLOW_CUSTOMER_REGISTRATION,
    ])]
    #[Assert\Choice(
        choices: [
            CustomerDeleteMethod::ALLOW_CUSTOMER_REGISTRATION,
            CustomerDeleteMethod::DENY_CUSTOMER_REGISTRATION,
        ],
        message: 'The delete method must be either "allow_registration_after" or "deny_registration_after".',
        groups: ['BulkDelete']
    )]
    public ?string $deleteMethod;

    public const COMMAND_MAPPING = [
        '[customerIds]' => '[customerIds]',
        '[deleteMethod]' => '[deleteMethod]',
    ];

    public const ENABLE_DISABLE_COMMAND_MAPPING = [
        '[customerIds]' => '[customerIds]',
    ];
}
