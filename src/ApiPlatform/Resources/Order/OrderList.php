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
use PrestaShop\Module\APIResources\ApiPlatform\Resources\Order\State\OrderProvider;
use PrestaShopBundle\ApiPlatform\Metadata\PaginatedList;
use Symfony\Component\HttpFoundation\Response;

#[ApiResource(
    operations: [
        new PaginatedList(
            uriTemplate: '/orders',
            provider: OrderProvider::class,
            scopes: ['order_read'],
            openapiContext: [
                'summary' => 'List orders',
                'parameters' => [
                    [
                        'name' => 'date_from',
                        'in' => 'query',
                        'required' => false,
                        'schema' => [
                            'type' => 'string',
                        ],
                    ],
                    [
                        'name' => 'date_to',
                        'in' => 'query',
                        'required' => false,
                        'schema' => [
                            'type' => 'string',
                        ],
                    ],
                    [
                        'name' => 'updated_from',
                        'in' => 'query',
                        'required' => false,
                        'schema' => [
                            'type' => 'string',
                        ],
                    ],
                    [
                        'name' => 'updated_to',
                        'in' => 'query',
                        'required' => false,
                        'schema' => [
                            'type' => 'string',
                        ],
                    ],
                    [
                        'name' => 'status_id',
                        'in' => 'query',
                        'required' => false,
                        'schema' => [
                            'type' => 'int',
                        ],
                    ],
                    [
                        'name' => 'q',
                        'in' => 'query',
                        'required' => false,
                        'schema' => [
                            'type' => 'string',
                        ],
                    ],
                ],
            ],
        ),
        new PaginatedList(
            uriTemplate: '/orders/_write-scope',
            provider: OrderProvider::class,
            scopes: ['order_write'],
            openapiContext: [
                'summary' => '[internal] Orders write scope registration',
                'deprecated' => true,
            ],
        ),
    ],
    normalizationContext: [
        'skip_null_values' => false,
        'groups' => ['order:read'],
    ],
    exceptionToStatus: [
        \InvalidArgumentException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
    ],
)]
/**
 * API Resource exposing the paginated list of orders.
 */
class OrderList
{
    /** @var int */
    #[ApiProperty(identifier: true)]
    public int $orderId;

    /** @var string */
    public string $reference;

    /** @var string */
    public string $status;

    /** @var int */
    public int $statusId;

    /** @var int */
    public int $shopId;

    /** @var int */
    public int $langId;

    /** @var string */
    public string $currencyIso;

    /** @var string */
    public string $totalPaidTaxIncl;

    /** @var string */
    public string $totalProductsTaxIncl;

    /** @var int */
    public int $customerId;

    /** @var string ISO 8601 */
    public string $dateAdd;
}
