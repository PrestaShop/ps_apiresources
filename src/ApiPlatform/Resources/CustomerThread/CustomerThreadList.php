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

namespace PrestaShop\Module\APIResources\ApiPlatform\Resources\CustomerThread;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use PrestaShop\PrestaShop\Core\Search\Filters\CustomerThreadFilter;
use PrestaShopBundle\ApiPlatform\Metadata\PaginatedList;
use PrestaShopBundle\ApiPlatform\Provider\QueryListProvider;

/**
 * Built on the data factory of the customer thread grid without its decorator, which only adds the display
 * color of the status. The thread token, selected by the grid query, is deliberately not exposed: it grants
 * access to the thread from the front office.
 */
#[ApiResource(
    operations: [
        new PaginatedList(
            uriTemplate: '/customer-threads',
            provider: QueryListProvider::class,
            scopes: ['customer_service_read'],
            ApiResourceMapping: [
                '[id_customer_thread]' => '[customerThreadId]',
                '[id_customer]' => '[customerId]',
                '[id_order]' => '[orderId]',
                '[id_product]' => '[productId]',
                '[id_contact]' => '[contactId]',
                '[contact]' => '[contactName]',
                '[id_shop]' => '[shopId]',
                '[id_lang]' => '[languageId]',
                '[langName]' => '[languageName]',
                '[message]' => '[lastMessage]',
                '[employee]' => '[lastEmployee]',
                '[date_add]' => '[dateAdd]',
                '[date_upd]' => '[dateUpd]',
            ],
            gridDataFactory: 'prestashop.core.grid.data.factory.customer_thread',
            filtersClass: CustomerThreadFilter::class,
            filtersMapping: [
                '[customerThreadId]' => '[id_customer_thread]',
                '[languageName]' => '[langName]',
                '[lastMessage]' => '[message]',
                '[lastEmployee]' => '[employee]',
                // The grid filters the contact and the shop by identifier, not by name
                '[contactId]' => '[contact]',
                '[shopId]' => '[shopName]',
            ],
        ),
    ],
    normalizationContext: ['skip_null_values' => false],
)]
class CustomerThreadList
{
    #[ApiProperty(identifier: true)]
    public int $customerThreadId;

    // 0 for a message sent by a visitor without account
    public int $customerId;

    // Full name of the customer, null for a visitor without account
    public ?string $customer;

    public string $email;

    public int $orderId;

    public int $productId;

    public int $contactId;

    public ?string $contactName;

    public int $shopId;

    public string $shopName;

    public int $languageId;

    public string $languageName;

    // "open", "closed", "pending1" or "pending2"
    public string $status;

    public ?string $lastMessage;

    // Initial and last name of the last employee who answered, null when no employee answered yet
    public ?string $lastEmployee;

    public \DateTimeImmutable $dateAdd;

    public \DateTimeImmutable $dateUpd;
}
