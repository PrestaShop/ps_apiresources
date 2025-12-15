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

namespace PrestaShop\Module\APIResources\ApiPlatform\Resources\Discount;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use PrestaShop\PrestaShop\Core\Domain\Discount\Exception\DiscountNotFoundException;
use PrestaShop\PrestaShop\Core\Search\Filters\DiscountFilters;
use PrestaShopBundle\ApiPlatform\Metadata\PaginatedList;
use PrestaShopBundle\ApiPlatform\Provider\QueryListProvider;
use Symfony\Component\HttpFoundation\Response;

#[ApiResource(
    operations: [
        new PaginatedList(
            uriTemplate: '/discounts',
            provider: QueryListProvider::class,
            scopes: ['discount_read'],
            ApiResourceMapping: [
                '[id_discount]' => '[discountId]',
                '[active]' => '[enabled]',
                '[discount_type]' => '[type]',
            ],
            gridDataFactory: 'prestashop.core.grid.data.factory.discount',
            filtersClass: DiscountFilters::class,
        ),
    ],
    exceptionToStatus: [
        DiscountNotFoundException::class => Response::HTTP_NOT_FOUND,
    ],
)]
class DiscountList
{
    #[ApiProperty(identifier: true)]
    public int $discountId;
    public string $type;
    public string $name;
    public bool $enabled;
    public string $code;
}
