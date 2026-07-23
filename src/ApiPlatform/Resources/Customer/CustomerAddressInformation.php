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
use PrestaShop\PrestaShop\Core\Domain\Customer\Exception\CustomerByEmailNotFoundException;
use PrestaShop\PrestaShop\Core\Domain\Customer\Query\GetCustomerForAddressCreation;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSGet;
use Symfony\Component\HttpFoundation\Response;

#[ApiResource(
    operations: [
        new CQRSGet(
            uriTemplate: '/customers/address-informations',
            CQRSQuery: GetCustomerForAddressCreation::class,
            scopes: [
                'customer_read',
            ],
            CQRSQueryMapping: self::QUERY_MAPPING,
            parameters: new Parameters([
                new QueryParameter(
                    key: 'email',
                    required: true,
                    description: 'Email of the customer whose information is fetched to pre-fill an address (matches the first customer found for that email)'
                ),
            ]),
            openapiContext: [
                'parameters' => [
                    [
                        'name' => 'email',
                        'in' => 'query',
                        'required' => true,
                        'schema' => [
                            'type' => 'string',
                        ],
                        'description' => 'Email of the customer whose information is fetched to pre-fill an address',
                    ],
                ],
            ],
        ),
    ],
    normalizationContext: ['skip_null_values' => false],
    exceptionToStatus: [
        CustomerByEmailNotFoundException::class => Response::HTTP_NOT_FOUND,
    ],
)]
class CustomerAddressInformation
{
    #[ApiProperty(identifier: true, openapiContext: ['type' => 'integer', 'example' => 1])]
    public int $customerId;

    public string $firstName;

    public string $lastName;

    public ?string $company;

    public const QUERY_MAPPING = [
        '[email]' => '[customerEmail]',
    ];
}
