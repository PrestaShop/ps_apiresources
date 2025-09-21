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
use PrestaShopBundle\ApiPlatform\Metadata\CQRSGet;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Serializer\Annotation\Groups;

#[ApiResource(
    operations: [
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
                '[prices][totalPaid]' => '[totalPaidTaxIncl]',
                '[prices][totalPaidTaxExcluded]' => '[totalPaidTaxExcl]',
                '[prices][productsTotal]' => '[totalProductsTaxIncl]',
                '[prices][productsTotalTaxExcluded]' => '[totalProductsTaxExcl]',
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
    ],
    normalizationContext: [
        'skip_null_values' => false,
        'groups' => ['order:read'],
    ],
    exceptionToStatus: [
        \PrestaShop\PrestaShop\Core\Domain\Order\Exception\OrderNotFoundException::class => Response::HTTP_NOT_FOUND,
        \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException::class => Response::HTTP_FORBIDDEN,
        \RuntimeException::class => Response::HTTP_NOT_FOUND,
        \InvalidArgumentException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
    ],
)]
/**
 * API Resource exposing the order detail.
 */
class Order
{
    #[ApiProperty(identifier: true)]
    #[Groups(['order:read'])]
    public int $orderId = 0;

    #[Groups(['order:read'])]
    public string $reference = '';

    #[Groups(['order:read'])]
    public string $status = '';

    #[Groups(['order:read'])]
    public int $statusId = 0;

    #[Groups(['order:read'])]
    public int $shopId = 0;

    #[Groups(['order:read'])]
    public int $langId = 0;

    #[Groups(['order:read'])]
    public string $currencyIso = '';

    #[Groups(['order:read'])]
    public string $totalPaidTaxIncl = '';

    #[Groups(['order:read'])]
    public string $totalPaidTaxExcl = '';

    #[Groups(['order:read'])]
    public string $totalProductsTaxIncl = '';

    #[Groups(['order:read'])]
    public string $totalProductsTaxExcl = '';
    /**
     * @var array<int, array{rate:string,totalTaxExcl:string,totalTaxIncl:string,taxAmount:string}>
     */
    #[ApiProperty(openapiContext: [
        'type' => 'array',
        'items' => [
            'type' => 'object',
            'properties' => [
                'rate' => ['type' => 'string'],
                'totalTaxExcl' => ['type' => 'string'],
                'totalTaxIncl' => ['type' => 'string'],
                'taxAmount' => ['type' => 'string'],
            ],
        ],
        'example' => [
            [
                'rate' => '20.00',
                'totalTaxExcl' => '100.00',
                'totalTaxIncl' => '120.00',
                'taxAmount' => '20.00',
            ],
        ],
    ])]
    #[Groups(['order:read'])]
    public array $vatBreakdown = [];
    /**
     * @var array{totalTaxExcl:string,totalTaxIncl:string,taxAmount:string}
     */
    #[ApiProperty(openapiContext: [
        'type' => 'object',
        'properties' => [
            'totalTaxExcl' => ['type' => 'string'],
            'totalTaxIncl' => ['type' => 'string'],
            'taxAmount' => ['type' => 'string'],
        ],
        'example' => [
            'totalTaxExcl' => '100.00',
            'totalTaxIncl' => '120.00',
            'taxAmount' => '20.00',
        ],
    ])]
    #[Groups(['order:read'])]
    public array $vatSummary = [];
    #[Groups(['order:read'])]
    public int $customerId = 0;

    #[Groups(['order:read'])]
    public int $deliveryAddressId = 0;

    #[Groups(['order:read'])]
    public int $invoiceAddressId = 0;

    #[Groups(['order:read'])]
    public int $carrierId = 0;

    /** @var string ISO 8601 */
    #[Groups(['order:read'])]
    public string $dateAdd = '';

    /**
     * @var array<int, array{orderDetailId:int, productId:int, productAttributeId:?int, name:string, reference:?string, quantity:int, unitPriceTaxIncl:string}>
     */
    #[Groups(['order:read'])]
    public array $items = [];
}
