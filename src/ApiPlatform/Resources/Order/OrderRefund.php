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
use PrestaShop\PrestaShop\Core\Domain\Order\Command\IssuePartialRefundCommand;
use PrestaShop\PrestaShop\Core\Domain\Order\Command\IssueReturnProductCommand;
use PrestaShop\PrestaShop\Core\Domain\Order\Command\IssueStandardRefundCommand;
use PrestaShop\PrestaShop\Core\Domain\Order\Exception\InvalidAmountException;
use PrestaShop\PrestaShop\Core\Domain\Order\Exception\InvalidRefundException;
use PrestaShop\PrestaShop\Core\Domain\Order\Exception\OrderConstraintException;
use PrestaShop\PrestaShop\Core\Domain\Order\Exception\OrderNotFoundException;
use PrestaShop\PrestaShop\Core\Domain\Order\Exception\ReturnProductDisabledException;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSCreate;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    operations: [
        new CQRSCreate(
            uriTemplate: '/orders/{orderId}/refunds/standards',
            requirements: ['orderId' => '\d+'],
            output: false,
            CQRSCommand: IssueStandardRefundCommand::class,
            scopes: ['order_write'],
        ),
        new CQRSCreate(
            uriTemplate: '/orders/{orderId}/refunds/partials',
            requirements: ['orderId' => '\d+'],
            output: false,
            CQRSCommand: IssuePartialRefundCommand::class,
            scopes: ['order_write'],
        ),
        new CQRSCreate(
            uriTemplate: '/orders/{orderId}/refunds/returns',
            requirements: ['orderId' => '\d+'],
            output: false,
            CQRSCommand: IssueReturnProductCommand::class,
            scopes: ['order_write'],
        ),
    ],
    exceptionToStatus: [
        OrderNotFoundException::class => Response::HTTP_NOT_FOUND,
        OrderConstraintException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        InvalidRefundException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        InvalidAmountException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        ReturnProductDisabledException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
    ],
)]
class OrderRefund
{
    #[ApiProperty(identifier: true)]
    public int $orderId;

    #[Assert\NotBlank]
    public array $orderDetailRefunds;

    public bool $refundShippingCost = false;

    public string $shippingCostRefundAmount = '0';

    public bool $restockRefundedProducts = false;

    public bool $generateCreditSlip = false;

    public bool $generateVoucher = false;

    public int $voucherRefundType = 0;

    public ?string $voucherRefundAmount = null;
}
