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

namespace PrestaShop\Module\APIResources\ApiPlatform\Resources\Customer;

use ApiPlatform\Metadata\Parameters;
use ApiPlatform\Metadata\QueryParameter;
use ApiPlatform\Metadata\ApiResource;
use PrestaShop\PrestaShop\Core\Domain\Customer\Exception\CustomerByEmailNotFoundException;
use PrestaShop\PrestaShop\Core\Domain\Customer\Query\GetCustomerForAddressCreation;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSGet;
use Symfony\Component\HttpFoundation\Response;

#[ApiResource(
    operations: [
        new CQRSGet(
            uriTemplate: '/customers/address-creation-info',
            CQRSQuery: GetCustomerForAddressCreation::class,
            scopes: [
                'customer_read',
            ],
            parameters: new Parameters([
                new QueryParameter(
                    key: 'customerEmail',
                    required: true,
                    description: 'Customer email address'
                ),
            ]),
        ),
    ],
    exceptionToStatus: [
        CustomerByEmailNotFoundException::class => Response::HTTP_NOT_FOUND,
    ],
)]
class AddressCreationCustomerInfo
{
    public int $customerId;

    public string $firstName;

    public string $lastName;

    public ?string $company;
}
