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
 * @author    PrestaShop SA and Contributors
 * @copyright Since 2007 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License version 3.0
 */

declare(strict_types=1);

namespace PrestaShop\Module\APIResources\ApiPlatform\Resources\Order;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\ApiProperty;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSGet;
use Symfony\Component\HttpFoundation\Response;

#[ApiResource(
    operations: [
        new CQRSGet(
            uriTemplate: '/order/{orderId}',
            requirements: ['orderId' => '\\d+'],
            scopes: ['order_read'],
            CQRSQuery: \PrestaShop\PrestaShop\Core\Domain\Order\Query\GetOrderForViewing::class,
            CQRSQueryMapping: [
                '[orderId]' => '[orderId]',
                '[reference]' => '[reference]',
                // status id and a best-effort status label
                '[history][currentOrderStatusId]' => '[statusId]',
                '[history][statuses][0][name]' => '[status]',
                '[prices][totalPaid]' => '[totalPaidTaxIncl]',
                '[prices][productsTotal]' => '[totalProductsTaxIncl]',
                '[customer][email]' => '[customerEmail]',
                '[customer][fullName]' => '[customerName]',
                '[createdAt]' => '[dateAdd]',
                // products list
                '[products][products]' => '[items]',
                // Map product item fields
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
    security: "oauth_scope('order_read')",
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
    /** @var int */
    #[ApiProperty(identifier: true)]
    public int $orderId;

    /** @var string */
    public string $reference;

    /** @var string */
    public string $status;

    /** @var int */
    public int $statusId;

    /** @var string */
    public string $currencyIso;

    /** @var string */
    public string $totalPaidTaxIncl;

    /** @var string */
    public string $totalProductsTaxIncl;

    /** @var string */
    public string $customerEmail;

    /** @var string */
    public string $customerName;

    /** @var string ISO 8601 */
    public string $dateAdd;

    /** @var array<int, array{productId:int, productAttributeId:?int, name:string, reference:?string, quantity:int, unitPriceTaxIncl:string}> */
    public array $items = [];
}


