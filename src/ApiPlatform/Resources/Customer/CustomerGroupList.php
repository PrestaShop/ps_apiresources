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

use ApiPlatform\Metadata\ApiResource;
use PrestaShop\Decimal\DecimalNumber;
use PrestaShop\PrestaShop\Core\Domain\Customer\Group\Exception\GroupNotFoundException;
use PrestaShopBundle\ApiPlatform\Metadata\PaginatedList;

#[ApiResource(
    operations: [
        new PaginatedList(
            uriTemplate: '/customers/groups',
            gridDataFactory: 'prestashop.core.grid.data.factory.customer_groups',
            ApiResourceMapping: [
                '[id_group]' => '[customerGroupId]',
                '[reduction]' => '[reductionPercent]',
                '[show_prices]' => '[showPrice]',
                '[members]' => '[customers]',
            ],
        ),
    ],
    exceptionToStatus: [GroupNotFoundException::class => 404],
)]
class CustomerGroupList
{
    public int $customerGroupId;

    public string $name;

    public DecimalNumber $reductionPercent;

    public int $customers;

    public bool $showPrice;
}
