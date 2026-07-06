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
use PrestaShop\PrestaShop\Core\Domain\Order\Command\IssuePartialRefundCommand;
use PrestaShop\PrestaShop\Core\Domain\Order\Exception\InvalidOrderStateException;
use PrestaShop\PrestaShop\Core\Domain\Order\Exception\OrderException;
use PrestaShop\PrestaShop\Core\Domain\Order\Exception\OrderNotFoundException;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSUpdate;
use Symfony\Component\HttpFoundation\Response;

#[ApiResource(
    operations: [
        new CQRSUpdate(
            uriTemplate: '/orders/{orderId}/partial-refunds',
            requirements: ['orderId' => '\d+'],
            read: false,
            output: false,
            CQRSCommand: IssuePartialRefundCommand::class,
            scopes: [
                'order_write',
            ],
        ),
    ],
    exceptionToStatus: [
        OrderNotFoundException::class => Response::HTTP_NOT_FOUND,
        InvalidOrderStateException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        OrderException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
    ],
)]
class OrderPartialRefund
{
    #[ApiProperty(identifier: true)]
    public int $orderId;

    /**
     * Refunds keyed by order detail id, each with a "quantity" and "amount".
     */
    public array $orderDetailRefunds;

    public string $shippingCostRefundAmount;

    public bool $restockRefundedProducts;

    public bool $generateCreditSlip;

    public bool $generateVoucher;

    public int $voucherRefundType;

    public ?string $voucherRefundAmount;
}
