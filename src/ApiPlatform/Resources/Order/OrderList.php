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
use PrestaShop\PrestaShop\Core\Search\Filters\OrderFilters;
use PrestaShopBundle\ApiPlatform\Metadata\PaginatedList;
use PrestaShopBundle\ApiPlatform\Provider\QueryListProvider;
use Symfony\Component\HttpFoundation\Response;

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
            openapiContext: [
                'summary' => 'List orders',
                'description' => 'Retrieve a paginated list of orders with filtering capabilities.',
                'parameters' => [
                    [
                        'name' => 'dateFrom',
                        'in' => 'query',
                        'required' => false,
                        'description' => 'Filter orders created after this date (YYYY-MM-DD format)',
                        'schema' => [
                            'type' => 'string',
                            'format' => 'date',
                        ],
                    ],
                    [
                        'name' => 'dateTo',
                        'in' => 'query',
                        'required' => false,
                        'description' => 'Filter orders created before this date (YYYY-MM-DD format)',
                        'schema' => [
                            'type' => 'string',
                            'format' => 'date',
                        ],
                    ],
                    [
                        'name' => 'updatedFrom',
                        'in' => 'query',
                        'required' => false,
                        'description' => 'Filter orders updated after this date (YYYY-MM-DD format)',
                        'schema' => [
                            'type' => 'string',
                            'format' => 'date',
                        ],
                    ],
                    [
                        'name' => 'updatedTo',
                        'in' => 'query',
                        'required' => false,
                        'description' => 'Filter orders updated before this date (YYYY-MM-DD format)',
                        'schema' => [
                            'type' => 'string',
                            'format' => 'date',
                        ],
                    ],
                    [
                        'name' => 'statusId',
                        'in' => 'query',
                        'required' => false,
                        'description' => 'Filter orders by status ID',
                        'schema' => [
                            'type' => 'integer',
                            'minimum' => 1,
                        ],
                    ],
                    [
                        'name' => 'q',
                        'in' => 'query',
                        'required' => false,
                        'description' => 'Search term for order reference or customer information',
                        'schema' => [
                            'type' => 'string',
                            'maxLength' => 255,
                        ],
                    ],
                ],
            ],
        ),
    ],
    exceptionToStatus: [
        \PrestaShop\PrestaShop\Core\Domain\Order\Exception\OrderNotFoundException::class => Response::HTTP_NOT_FOUND,
        \PrestaShop\PrestaShop\Core\Domain\Order\Exception\OrderException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException::class => Response::HTTP_FORBIDDEN,
    ],
)]
class OrderList
{
    #[ApiProperty(identifier: true)]
    public int $orderId;

    public string $reference;

    public string $status;

    public int $statusId;

    public int $shopId;

    public int $langId;

    public int $customerId;

    public string $currencyIso;

    public string $dateAdd;

    public float $totalPaidTaxIncl;

    public float $totalProductsTaxIncl;
}
