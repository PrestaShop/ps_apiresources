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

namespace PrestaShop\Module\APIResources\ApiPlatform\Resources\CustomerSession;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use PrestaShop\PrestaShop\Core\Search\Filters\Security\Session\CustomerFilters;
use PrestaShopBundle\ApiPlatform\Metadata\PaginatedList;

#[ApiResource(
    operations: [
        new PaginatedList(
            uriTemplate: '/customer-sessions',
            scopes: [
                'customer_session_read',
            ],
            ApiResourceMapping: self::MAPPING,
            gridDataFactory: 'prestashop.core.grid.data_factory.security.session.customer',
            filtersClass: CustomerFilters::class,
            filtersMapping: [
                '[sessionId]' => '[id_customer_session]',
            ],
        ),
    ]
)]
class CustomerSessionList
{
    #[ApiProperty(identifier: true)]
    public int $sessionId;

    public int $customerId;

    public string $firstname;

    public string $lastname;

    public string $email;

    public const MAPPING = [
        '[id_customer_session]' => '[sessionId]',
        '[id_customer]' => '[customerId]',
    ];
}
