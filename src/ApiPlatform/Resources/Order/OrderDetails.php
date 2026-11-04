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

namespace PrestaShop\Module\APIResources\ApiPlatform\Resources\Order;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use PrestaShop\PrestaShop\Core\Domain\Order\Exception\OrderNotFoundException;
use PrestaShop\PrestaShop\Core\Domain\Order\Query\GetOrderForViewing;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSGet;
use Symfony\Component\HttpFoundation\Response;

#[ApiResource(
    operations: [
        new CQRSGet(
            uriTemplate: '/orders/{orderId}/details',
            requirements: ['orderId' => '\d+'],
            CQRSQuery: GetOrderForViewing::class,
            scopes: [
                'order_read',
            ],
            CQRSQueryMapping: self::QUERY_MAPPING,
        ),
    ],
    exceptionToStatus: [
        OrderNotFoundException::class => Response::HTTP_NOT_FOUND,
    ],
)]
class OrderDetails
{
    #[ApiProperty(identifier: true, openapiContext: ['type' => 'integer', 'example' => 1])]
    public int $orderId;

    public int $cartId;

    public int $currencyId;

    public int $carrierId;

    public string $carrierName;

    public int $shopId;

    public string $reference;

    public bool $valid;

    public ?array $customer;

    public array $shippingAddress;

    public array $invoiceAddress;

    public array $products;

    public string $taxMethod;

    public array $history;

    public array $documents;

    public array $shipping;

    public array $returns;

    public array $payments;

    public array $messages;

    public bool $delivered;

    public bool $shipped;

    public array $prices;

    public bool $taxIncluded;

    public array $discounts;

    public array $linkedOrders;

    public \DateTimeImmutable $createdAt;

    public bool $virtual;

    public bool $invoiceManagementIsEnabled;

    public array $sources;

    public bool $refundable;

    public string $shippingAddressFormatted;

    public string $invoiceAddressFormatted;

    public string $note;

    public string $paymentName;

    public string $paymentModule;

    public const QUERY_MAPPING = [
        // Transforms the url orderId parameter into the $orderId parameter for GetOrderForViewing
        '[orderId]' => '[orderId]',
        // Transforms the query result OrderForViewing::getId into the API resource orderId
        '[id]' => '[orderId]',
    ];
}
