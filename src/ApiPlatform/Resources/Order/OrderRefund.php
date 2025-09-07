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
use PrestaShop\Module\APIResources\ApiPlatform\Serializer\Callbacks;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSPost;
use Symfony\Component\HttpFoundation\Response;

#[ApiResource(
    operations: [
        new CQRSPost(
            uriTemplate: '/orders/{orderId}/refunds',
            requirements: ['orderId' => '\\d+'],
            scopes: ['order_write'],
            CQRSCommand: \PrestaShop\PrestaShop\Core\Domain\Order\Command\IssueStandardRefundCommand::class,
            CQRSCommandMapping: [
                '[orderId]' => '[orderId]',
                '[orderDetailRefunds]' => '[orderDetailRefunds]',
                '[refundShippingCost]' => '[refundShippingCost]',
                '[generateCreditSlip]' => '[generateCreditSlip]',
                '[generateVoucher]' => '[generateVoucher]',
                '[voucherRefundType]' => '[voucherRefundType]',
            ],
            openapiContext: [
                'summary' => 'Issue standard refund for an order',
            ],
            allowEmptyBody: false,
        ),
    ],
    denormalizationContext: [
        'skip_null_values' => false,
        'disable_type_enforcement' => true,
        'callbacks' => [
            'orderId' => [Callbacks::class, 'toInt'],
            'orderDetailRefunds' => [Callbacks::class, 'toOrderDetailRefunds'],
            'voucherRefundType' => [Callbacks::class, 'toInt'],
        ],
    ],
    exceptionToStatus: [
        \PrestaShop\PrestaShop\Core\Domain\Order\Exception\OrderNotFoundException::class => Response::HTTP_NOT_FOUND,
        \PrestaShop\PrestaShop\Core\Domain\Order\Exception\OrderException::class => Response::HTTP_NOT_FOUND,
        \PrestaShop\PrestaShop\Core\Domain\Order\Exception\InvalidCancelProductException::class => Response::HTTP_BAD_REQUEST,
        \Symfony\Component\Serializer\Exception\NotNormalizableValueException::class => Response::HTTP_NOT_FOUND,
    ],
)]
/**
 * API Resource handling standard order refunds.
 */
class OrderRefund
{
    /**
     * @var array<int,int>|null Map of order detail identifiers to quantity to refund
     */
    public ?array $orderDetailRefunds = null;

    /**
     * @var bool|null Whether the shipping cost should be refunded
     */
    public ?bool $refundShippingCost = null;

    /**
     * @var bool|null Whether a credit slip should be generated
     */
    public ?bool $generateCreditSlip = null;

    /**
     * @var bool|null Whether a voucher should be generated
     */
    public ?bool $generateVoucher = null;

    /**
     * @var int|null Voucher refund type
     */
    public ?int $voucherRefundType = null;
}
