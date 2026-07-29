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
            parameters: new Parameters([
                new QueryParameter(
                    key: 'phrases',
                    required: true,
                    description: 'Array of search phrases to find customers (matches first name, last name, email, company name and id)'
                ),
            ]),
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
    public int $customerId;

    public string $firstName;

    public string $lastName;

    public string $email;

    public string $fullnameAndEmail;

    // POST /customers calls this "enabled" and returns a real boolean. Renaming it here needs the
    // tiny-int to bool cast, which CQRSApiSerializer only applies when CAST_BOOL is in the context -
    // and that is set by QueryListProvider only, not by the QueryProvider serving this collection.
    // Typing this bool without that raises "The type of the enabled attribute must be bool, integer
    // given". Left as active until the cast is reachable from a CQRSGetCollection.
    #[ApiProperty(openapiContext: ['type' => 'integer', 'example' => 1])]
    public int $active;

    public ?string $company;

    #[ApiProperty(openapiContext: ['type' => 'integer', 'example' => 3])]
    public int $defaultGroupId;

    #[ApiProperty(openapiContext: ['type' => 'array', 'items' => ['type' => 'integer'], 'example' => [1, 3]])]
    public array $groups;

    public const QUERY_MAPPING = [
        '[phrases]' => '[phrases]',
        '[_context][shopConstraint]' => '[shopConstraint]',
    ];

    // The CQRS result carries the legacy column names. Everything that was not mapped reached the
    // response untouched, which is why this endpoint answered with firstname/lastname/active/id_*
    // while POST /customers uses firstName/lastName/enabled/customerId.
    public const API_RESOURCE_MAPPING = [
        '[id_customer]' => '[customerId]',
        '[fullname_and_email]' => '[fullnameAndEmail]',
        '[id_default_group]' => '[defaultGroupId]',
        '[firstname]' => '[firstName]',
        '[lastname]' => '[lastName]',
    ];
}
