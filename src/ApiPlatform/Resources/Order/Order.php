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
                '[createdAt]' => '[dateAdd]',
                // products list
                '[products][products]' => '[items]',
                // Map product item fields
                '[products][products][][orderDetailId]' => '[items][][orderDetailId]',
                '[products][products][][productId]' => '[items][][productId]',
                '[products][products][][productAttributeId]' => '[items][][productAttributeId]',
                '[products][products][][name]' => '[items][][name]',
                '[products][products][][reference]' => '[items][][reference]',
                '[products][products][][quantity]' => '[items][][quantity]',
                '[products][products][][priceTaxIncluded]' => '[items][][unitPriceTaxIncl]',
            ],
        ),
    ],
    normalizationContext: ['skip_null_values' => false],
    exceptionToStatus: [
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
    public int $orderId;
    public string $reference;
    public string $status;
    public int $statusId;
    public int $shopId;
    public int $langId;
    public string $currencyIso;
    public string $totalPaidTaxIncl;
    public string $totalPaidTaxExcl;
    public string $totalProductsTaxIncl;
    public string $totalProductsTaxExcl;
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
     * VAT breakdown per rate.
     *
     * @var array<int, array{vatRate:string, taxableAmount:string, vatAmount:string}>
     */
    #[ApiProperty]
    public array $vatBreakdown = [];
    /**
     * VAT summary totals.
     *
     * @var array{totalTaxableAmount:string, totalVatAmount:string}
     */
    #[ApiProperty]
    public array $vatSummary = [];
    public int $customerId;
    public int $deliveryAddressId;
    public int $invoiceAddressId;

    /** @var string ISO 8601 */
    public string $dateAdd;

    /**
     * @var array<int, array{orderDetailId:int, productId:int, productAttributeId:?int, name:string, reference:?string, quantity:int, unitPriceTaxIncl:string}>
     */
    public array $items = [];
}
