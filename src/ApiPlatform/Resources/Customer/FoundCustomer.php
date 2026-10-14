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
use ApiPlatform\Metadata\Parameters;
use ApiPlatform\Metadata\QueryParameter;
use PrestaShop\Module\APIResources\ApiPlatform\Resources\Customer\Dto\FoundCustomerGroup;
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
            extraProperties: [
                // The query result is indexed by customer id, the response is a list from PrestaShop 9.2.1
                'collectionAsList' => true,
            ],
            parameters: new Parameters([
                new QueryParameter(
                    key: 'phrases',
                    required: true,
                    description: 'Array of search phrases to find customers (matches first name, last name, email, company name and id)'
                ),
            ]),
            openapiContext: [
                'summary' => 'Search customers.',
                'description' => 'Returns the list of customers matching the phrases (before PrestaShop 9.2.1 the list is an object indexed by customer id).',
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
    public int $customerId;

    public string $firstName;

    public string $lastName;

    public string $email;

    public string $fullnameAndEmail;

    public bool $enabled;

    public ?string $companyName;

    #[ApiProperty(openapiContext: ['type' => 'integer', 'example' => 3])]
    public int $defaultGroupId;

    /**
     * @var FoundCustomerGroup[]
     */
    public array $groups;

    public const QUERY_MAPPING = [
        '[phrases]' => '[phrases]',
        '[_context][shopConstraint]' => '[shopConstraint]',
    ];

    public const API_RESOURCE_MAPPING = [
        '[id_customer]' => '[customerId]',
        '[firstname]' => '[firstName]',
        '[lastname]' => '[lastName]',
        '[fullname_and_email]' => '[fullnameAndEmail]',
        '[active]' => '[enabled]',
        '[company]' => '[companyName]',
        '[id_default_group]' => '[defaultGroupId]',
    ];
}
