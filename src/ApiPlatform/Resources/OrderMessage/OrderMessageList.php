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

namespace PrestaShop\Module\APIResources\ApiPlatform\Resources\OrderMessage;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use PrestaShop\PrestaShop\Core\Search\Filters\OrderMessageFilters;
use PrestaShopBundle\ApiPlatform\Metadata\PaginatedList;

#[ApiResource(
    operations: [
        new PaginatedList(
            uriTemplate: '/order-messages',
            scopes: [
                'order_message_read',
            ],
            ApiResourceMapping: self::MAPPING,
            gridDataFactory: 'prestashop.core.grid.data.factory.order_message',
            filtersClass: OrderMessageFilters::class,
            filtersMapping: [
                '[orderMessageId]' => '[id_order_message]',
            ],
        ),
    ]
)]
class OrderMessageList
{
    #[ApiProperty(identifier: true)]
    public int $orderMessageId;

    public string $name;

    public string $message;

    public const MAPPING = [
        '[id_order_message]' => '[orderMessageId]',
    ];
}
