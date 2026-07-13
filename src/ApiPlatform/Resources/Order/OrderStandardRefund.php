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
use PrestaShop\PrestaShop\Core\Domain\Order\Command\IssueStandardRefundCommand;
use PrestaShop\PrestaShop\Core\Domain\Order\Exception\OrderException;
use PrestaShop\PrestaShop\Core\Domain\Order\Exception\OrderNotFoundException;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSCreate;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    operations: [
        new CQRSCreate(
            uriTemplate: '/orders/{orderId}/refunds',
            requirements: ['orderId' => '\d+'],
            CQRSCommand: IssueStandardRefundCommand::class,
            scopes: ['order_write'],
        ),
    ],
    exceptionToStatus: [
        OrderNotFoundException::class => Response::HTTP_NOT_FOUND,
        OrderException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
    ],
)]
class OrderStandardRefund
{
    public int $orderId;

    /** Array of {orderDetailId, productQuantity, refundedAmount?}. */
    #[ApiProperty(openapiContext: [
        'type' => 'array',
        'items' => [
            'type' => 'object',
            'properties' => [
                'orderDetailId' => ['type' => 'integer'],
                'productQuantity' => ['type' => 'integer', 'minimum' => 1],
                'refundedAmount' => ['type' => 'string', 'description' => 'Optional per-line refund amount (partial refund); omit for a full-line refund.'],
            ],
            'required' => ['orderDetailId', 'productQuantity'],
        ],
    ])]
    #[Assert\NotBlank]
    public array $orderDetailRefunds;

    #[Assert\NotNull]
    public bool $refundShippingCost;

    #[Assert\NotNull]
    public bool $generateCreditSlip;

    #[Assert\NotNull]
    public bool $generateVoucher;

    #[Assert\NotNull]
    public int $voucherRefundType;
}
