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
use PrestaShop\PrestaShop\Core\Domain\Customer\Exception\CustomerNotFoundException;
use PrestaShop\PrestaShop\Core\Domain\Customer\Query\GetCustomerForViewing;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSGet;
use Symfony\Component\HttpFoundation\Response;

#[ApiResource(
    operations: [
        new CQRSGet(
            uriTemplate: '/customers/{customerId}/details',
            requirements: ['customerId' => '\d+'],
            CQRSQuery: GetCustomerForViewing::class,
            scopes: [
                'customer_read',
            ],
            CQRSQueryMapping: self::QUERY_MAPPING,
        ),
    ],
    exceptionToStatus: [
        CustomerNotFoundException::class => Response::HTTP_NOT_FOUND,
    ],
)]
class CustomerDetails
{
    #[ApiProperty(identifier: true, openapiContext: ['type' => 'integer', 'example' => 1])]
    public int $customerId;

    public mixed $personalInformation;

    public mixed $ordersInformation;

    public array $cartsInformation;

    public mixed $productsInformation;

    public array $messagesInformation;

    public array $discountsInformation;

    public array $sentEmailsInformation;

    public array $lastConnectionsInformation;

    public array $groupsInformation;

    public array $addressesInformation;

    public mixed $generalInformation;

    public const QUERY_MAPPING = [
        '[customerId]' => '[customerId]',
    ];
}
