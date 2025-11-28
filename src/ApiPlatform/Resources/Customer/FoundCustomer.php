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
use ApiPlatform\Metadata\QueryParameter;
use PrestaShop\PrestaShop\Core\Domain\Customer\Exception\CustomerException;
use PrestaShop\PrestaShop\Core\Domain\Customer\Query\SearchCustomers;
use PrestaShop\PrestaShop\Core\Domain\Shop\Exception\InvalidShopConstraintException;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSGetCollection;
use Symfony\Component\HttpFoundation\Response;

#[ApiResource(
    operations: [
        new CQRSGetCollection(
            uriTemplate: '/customers/search',
            scopes: [
                'customer_read',
            ],
            CQRSQuery: SearchCustomers::class,
            CQRSQueryMapping: self::QUERY_MAPPING,
            ApiResourceMapping: self::API_RESOURCE_MAPPING,
            parameters: [
                new QueryParameter(
                    key: 'phrases',
                    required: true,
                    description: 'Array of search phrases to find customers (matches first name, last name, email, company name and id)'
                ),
            ],
            openapiContext: [
                'parameters' => [
                    [
                        'name' => 'phrases',
                        'in' => 'query',
                        'required' => true,
                        'schema' => [
                            'type' => 'array',
                            'items' => [
                                'type' => 'string',
                            ],
                        ],
                        'description' => 'Array of search phrases to find customers (matches first name, last name, email, company name and id)',
                        'style' => 'form',
                        'explode' => true,
                    ],
                ],
            ],
        ),
    ],
    exceptionToStatus: [
        CustomerException::class => Response::HTTP_BAD_REQUEST,
        InvalidShopConstraintException::class => Response::HTTP_BAD_REQUEST,
    ],
)]
class FoundCustomer
{
    #[ApiProperty(identifier: true, openapiContext: ['type' => 'integer', 'example' => 1])]
    public int $idCustomer;

    public string $firstname;

    public string $lastname;

    public string $email;

    public string $fullnameAndEmail;

    #[ApiProperty(openapiContext: ['type' => 'integer', 'example' => 1])]
    public int $active;

    public ?string $company;

    #[ApiProperty(openapiContext: ['type' => 'integer', 'example' => 3])]
    public int $idDefaultGroup;

    #[ApiProperty(openapiContext: ['type' => 'array', 'items' => ['type' => 'integer'], 'example' => [1, 3]])]
    public array $groups;

    public const QUERY_MAPPING = [
        '[phrases]' => '[phrases]',
        '[_context][shopConstraint]' => '[shopConstraint]',
    ];

    public const API_RESOURCE_MAPPING = [
        '[id_customer]' => '[idCustomer]',
        '[fullname_and_email]' => '[fullnameAndEmail]',
        '[id_default_group]' => '[idDefaultGroup]',
    ];
}
