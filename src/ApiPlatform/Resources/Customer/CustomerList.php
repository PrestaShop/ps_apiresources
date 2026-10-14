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

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use PrestaShop\Decimal\DecimalNumber;
use PrestaShop\PrestaShop\Core\Search\Filters\CustomerFilters;
use PrestaShopBundle\ApiPlatform\Metadata\PaginatedList;
use PrestaShopBundle\ApiPlatform\Provider\QueryListProvider;

/*
 * Built on the data factory of the customer grid without its decorator, which formats the total spent
 * as a localized price and replaces empty values with "--" for display.
 */
#[ApiResource(
    operations: [
        new PaginatedList(
            uriTemplate: '/customers',
            provider: QueryListProvider::class,
            scopes: ['customer_read'],
            ApiResourceMapping: [
                '[id_customer]' => '[customerId]',
                '[social_title]' => '[socialTitle]',
                '[firstname]' => '[firstName]',
                '[lastname]' => '[lastName]',
                '[company]' => '[companyName]',
                '[total_spent]' => '[totalSpent]',
                '[active]' => '[enabled]',
                '[newsletter]' => '[newsletterSubscribed]',
                '[optin]' => '[partnerOffersSubscribed]',
                '[default_group]' => '[defaultGroupName]',
                '[shop_name]' => '[shopName]',
                '[date_add]' => '[dateAdd]',
                '[connect]' => '[lastVisitDate]',
            ],
            gridDataFactory: 'prestashop.core.grid.data_provider.customer',
            filtersClass: CustomerFilters::class,
            filtersMapping: [
                '[customerId]' => '[id_customer]',
                '[firstName]' => '[firstname]',
                '[lastName]' => '[lastname]',
                '[companyName]' => '[company]',
                '[totalSpent]' => '[total_spent]',
                '[enabled]' => '[active]',
                '[newsletterSubscribed]' => '[newsletter]',
                '[partnerOffersSubscribed]' => '[optin]',
                '[dateAdd]' => '[date_add]',
                '[lastVisitDate]' => '[connect]',
                // The grid filters the social title and the default group by identifier, not by name
                '[genderId]' => '[social_title]',
                '[defaultGroupId]' => '[default_group]',
            ],
        ),
    ],
    normalizationContext: ['skip_null_values' => false],
)]
class CustomerList
{
    #[ApiProperty(identifier: true)]
    public int $customerId;

    public ?string $socialTitle;

    public string $firstName;

    public string $lastName;

    public string $email;

    public ?string $companyName;

    public ?DecimalNumber $totalSpent;

    public bool $enabled;

    public bool $newsletterSubscribed;

    public bool $partnerOffersSubscribed;

    public ?string $defaultGroupName;

    public string $shopName;

    public \DateTimeImmutable $dateAdd;

    public ?\DateTimeImmutable $lastVisitDate;
}
