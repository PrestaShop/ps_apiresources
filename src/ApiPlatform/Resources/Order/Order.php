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
use PrestaShop\Module\APIResources\ApiPlatform\Serializer\Callbacks;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSCreate;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSGet;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSPartialUpdate;
use PrestaShopBundle\ApiPlatform\Metadata\PaginatedList;
use PrestaShopBundle\ApiPlatform\Provider\QueryListProvider;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

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
                // Status information
                '[osname]' => '[status]',
                '[id_lang]' => '[langId]',
                '[iso_code]' => '[currencyIso]',
                '[total_paid_tax_incl]' => '[totalPaidTaxIncl:float]',
                '[total_products_wt]' => '[totalProductsTaxIncl:float]',
            ],
            gridDataFactory: 'prestashop.core.grid.data.factory.order_decorator',
            filtersClass: \PrestaShop\PrestaShop\Core\Search\Filters\OrderFilters::class,
            normalizationContext: [
                'callbacks' => [
                    'totalPaidTaxIncl' => [Callbacks::class, 'toFloat'],
                    'totalProductsTaxIncl' => [Callbacks::class, 'toFloat'],
                ],
            ],
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
        new CQRSGet(
            uriTemplate: '/orders/{orderId}',
            requirements: ['orderId' => '\d+'],
            scopes: ['order_read'],
            CQRSQuery: \PrestaShop\PrestaShop\Core\Domain\Order\Query\GetOrderForViewing::class,
            CQRSQueryMapping: [
                '[orderId]' => '[orderId]',
                '[reference]' => '[reference]',
                // status id and a best-effort status label
                '[history][currentOrderStatusId]' => '[statusId]',
                '[history][statuses][0][name]' => '[status]',
                '[prices][totalPaid]' => '[totalPaidTaxIncl:float]',
                '[prices][totalPaidTaxExcluded]' => '[totalPaidTaxExcl:float]',
                '[prices][productsTotal]' => '[totalProductsTaxIncl:float]',
                '[prices][productsTotalTaxExcluded]' => '[totalProductsTaxExcl:float]',
                '[prices][vatBreakdown]' => '[vatBreakdown]',
                '[prices][vatSummary]' => '[vatSummary]',
                '[taxes][breakdown]' => '[vatBreakdown]',
                '[taxes][summary]' => '[vatSummary]',
                '[shopId]' => '[shopId]',
                '[customer][languageId]' => '[langId]',
                '[customer][id]' => '[customerId]',
                '[shippingAddress][addressId]' => '[deliveryAddressId]',
                '[invoiceAddress][addressId]' => '[invoiceAddressId]',
                '[shipping][carrierId]' => '[carrierId]',
                '[createdAt]' => '[dateAdd]',
                // products list mapping - accessing the products collection directly
                '[products]' => '[items]',
            ],
        ),
        new CQRSCreate(
            uriTemplate: '/orders',
            allowEmptyBody: true,
            scopes: ['order_write'],
            CQRSCommand: \PrestaShop\PrestaShop\Core\Domain\Order\Command\AddOrderFromBackOfficeCommand::class,
            CQRSQuery: \PrestaShop\PrestaShop\Core\Domain\Order\Query\GetOrderForViewing::class,
            CQRSCommandMapping: [
                '[cartId]' => '[cartId]',
                '[employeeId]' => '[employeeId]',
                '[paymentModuleName]' => '[paymentModuleName]',
                '[orderStateId]' => '[orderStateId]',
                '[orderMessage]' => '[orderMessage]',
            ],
            CQRSQueryMapping: [
                '[orderId]' => '[orderId]',
                '[reference]' => '[reference]',
                '[history][currentOrderStatusId]' => '[statusId]',
                '[history][statuses][0][name]' => '[status]',
                '[shopId]' => '[shopId]',
                '[customer][id]' => '[customerId]',
                '[createdAt]' => '[dateAdd]',
            ],
        ),
        new CQRSPartialUpdate(
            uriTemplate: '/orders/{orderId}',
            requirements: ['orderId' => '\d+'],
            scopes: ['order_write'],
            CQRSCommand: \PrestaShop\PrestaShop\Core\Domain\Order\Command\UpdateOrderStatusCommand::class,
            CQRSQuery: \PrestaShop\PrestaShop\Core\Domain\Order\Query\GetOrderForViewing::class,
            CQRSCommandMapping: [
                '[statusId]' => '[newOrderStatusId]',
            ],
            denormalizationContext: [
                'disable_type_enforcement' => true,
                'callbacks' => [
                    'orderId' => [Callbacks::class, 'toInt'],
                    'statusId' => [Callbacks::class, 'toInt'],
                ],
                'default_constructor_arguments' => [
                    \PrestaShop\PrestaShop\Core\Domain\Order\Command\UpdateOrderStatusCommand::class => [
                        'orderId' => 0,
                        'newOrderStatusId' => 0,
                    ],
                ],
            ],
            CQRSQueryMapping: [
                '[orderId]' => '[orderId]',
                '[reference]' => '[reference]',
                '[history][currentOrderStatusId]' => '[statusId]',
                '[history][statuses][0][name]' => '[status]',
                '[shopId]' => '[shopId]',
                '[customer][id]' => '[customerId]',
                '[createdAt]' => '[dateAdd]',
            ],
            openapiContext: [
                'summary' => 'Update order status',
                'description' => 'Update the status of an order by providing statusId',
            ],
        ),
    ],
    exceptionToStatus: [
        \PrestaShop\PrestaShop\Core\Domain\Order\Exception\OrderNotFoundException::class => Response::HTTP_NOT_FOUND,
        \PrestaShop\PrestaShop\Core\Domain\Order\Exception\OrderException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException::class => Response::HTTP_FORBIDDEN,
        \Symfony\Component\Validator\Exception\ValidationFailedException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        \Symfony\Component\Serializer\Exception\NotNormalizableValueException::class => Response::HTTP_BAD_REQUEST,
        \RuntimeException::class => Response::HTTP_NOT_FOUND,
    ],
)]
class Order
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

    public int $deliveryAddressId = 0;

    public int $invoiceAddressId = 0;

    public int $carrierId = 0;

    public string $dateAdd = '';

    public array $vatBreakdown = [];

    public array $vatSummary = [];

    public float $totalPaidTaxIncl = 0.0;

    public float $totalPaidTaxExcl = 0.0;

    public float $totalProductsTaxIncl = 0.0;

    public float $totalProductsTaxExcl = 0.0;

    public array $items = [];

    // Fields for order creation
    public int $cartId = 0;

    public int $employeeId = 0;

    public string $orderMessage = '';

    public string $paymentModuleName = '';

    public int $orderStateId = 0;

    #[Assert\Callback]
    public function validate(ExecutionContextInterface $context): void
    {
        // Custom validation logic if needed
    }
}
