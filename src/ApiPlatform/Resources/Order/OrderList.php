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

namespace PrestaShop\Module\APIResources\ApiPlatform\Resources\Order;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use PrestaShop\PrestaShop\Core\Domain\Order\Exception\OrderException;
use PrestaShop\PrestaShop\Core\Domain\Order\Exception\OrderNotFoundException;
use PrestaShop\PrestaShop\Core\Search\Filters\OrderFilters;
use PrestaShopBundle\ApiPlatform\Metadata\PaginatedList;
use PrestaShopBundle\ApiPlatform\Provider\QueryListProvider;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

#[ApiResource(
    operations: [
        new PaginatedList(
            uriTemplate: '/orders',
            provider: QueryListProvider::class,
            scopes: ['order_read'],
            ApiResourceMapping: [
                '[id_order]' => '[orderId]',
                '[reference]' => '[reference]',
                '[id_shop]' => '[shopId]',
                '[id_customer]' => '[customerId]',
                '[current_state]' => '[statusId]',
                '[date_add]' => '[dateAdd]',
                '[osname]' => '[status]',
                '[id_lang]' => '[langId]',
                '[iso_code]' => '[currencyIso]',
                '[total_paid_tax_incl]' => '[totalPaidTaxIncl:float]',
                '[total_products_wt]' => '[totalProductsTaxIncl:float]',
            ],
            gridDataFactory: 'prestashop.core.grid.data.factory.order_decorator',
            filtersClass: OrderFilters::class,
            filtersMapping: [
                '[orderId]' => '[id_order]',
                '[reference]' => '[reference]',
                '[customerId]' => '[id_customer]',
                '[statusId]' => '[current_state]',
                '[dateFrom]' => '[date_add_from]',
                '[dateTo]' => '[date_add_to]',
                '[updatedFrom]' => '[date_upd_from]',
                '[updatedTo]' => '[date_upd_to]',
            ],
        ),
    ],
    exceptionToStatus: [
        OrderNotFoundException::class => Response::HTTP_NOT_FOUND,
        OrderException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        AccessDeniedHttpException::class => Response::HTTP_FORBIDDEN,
    ],
)]
class OrderList
{
    #[ApiProperty(identifier: true)]
    public int $orderId = 0;

    public string $reference = '';

    public string $status = '';

    public int $statusId = 0;

    public int $shopId = 0;

    public int $langId = 0;

    public int $customerId = 0;

    public string $currencyIso = '';

    public string $dateAdd = '';

    public float $totalPaidTaxIncl = 0.0;

    public float $totalProductsTaxIncl = 0.0;
}
