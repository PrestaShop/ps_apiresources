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
use PrestaShop\PrestaShop\Core\Domain\Order\Command\ResendOrderEmailCommand;
use PrestaShop\PrestaShop\Core\Domain\Order\Command\SendProcessOrderEmailCommand;
use PrestaShop\PrestaShop\Core\Domain\Order\Exception\OrderConstraintException;
use PrestaShop\PrestaShop\Core\Domain\Order\Exception\OrderEmailSendException;
use PrestaShop\PrestaShop\Core\Domain\Order\Exception\OrderNotFoundException;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSCreate;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    operations: [
        new CQRSCreate(
            uriTemplate: '/orders/{orderId}/resend-emails',
            requirements: ['orderId' => '\d+'],
            output: false,
            CQRSCommand: ResendOrderEmailCommand::class,
            scopes: ['order_write'],
        ),
        new CQRSCreate(
            uriTemplate: '/orders/{cartId}/send-process-order-emails',
            requirements: ['cartId' => '\d+'],
            output: false,
            CQRSCommand: SendProcessOrderEmailCommand::class,
            scopes: ['order_write'],
        ),
    ],
    exceptionToStatus: [
        OrderNotFoundException::class => Response::HTTP_NOT_FOUND,
        OrderConstraintException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        OrderEmailSendException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
    ],
)]
class OrderEmail
{
    #[ApiProperty(identifier: true)]
    public int $orderId;

    public int $cartId;

    #[Assert\NotBlank]
    public int $orderStatusId;

    #[Assert\NotBlank]
    public int $orderHistoryId;
}
