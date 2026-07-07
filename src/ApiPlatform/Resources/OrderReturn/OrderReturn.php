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

namespace PrestaShop\Module\APIResources\ApiPlatform\Resources\OrderReturn;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use PrestaShop\PrestaShop\Core\Domain\OrderReturn\Command\UpdateOrderReturnStateCommand;
use PrestaShop\PrestaShop\Core\Domain\OrderReturn\Exception\OrderReturnConstraintException;
use PrestaShop\PrestaShop\Core\Domain\OrderReturn\Exception\OrderReturnNotFoundException;
use PrestaShop\PrestaShop\Core\Domain\OrderReturn\Query\GetOrderReturnForEditing;
use PrestaShop\PrestaShop\Core\Domain\OrderReturnState\Exception\OrderReturnStateNotFoundException;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSGet;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSPartialUpdate;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    operations: [
        new CQRSGet(
            uriTemplate: '/order-returns/{orderReturnId}',
            requirements: ['orderReturnId' => '\d+'],
            CQRSQuery: GetOrderReturnForEditing::class,
            scopes: ['order_return_read'],
        ),
        new CQRSPartialUpdate(
            uriTemplate: '/order-returns/{orderReturnId}',
            requirements: ['orderReturnId' => '\d+'],
            read: false,
            CQRSCommand: UpdateOrderReturnStateCommand::class,
            CQRSQuery: GetOrderReturnForEditing::class,
            scopes: ['order_return_write'],
        ),
    ],
    normalizationContext: ['skip_null_values' => false],
    exceptionToStatus: [
        OrderReturnNotFoundException::class => Response::HTTP_NOT_FOUND,
        OrderReturnConstraintException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        OrderReturnStateNotFoundException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
    ],
)]
class OrderReturn
{
    #[ApiProperty(identifier: true)]
    public int $orderReturnId;

    public int $customerId;

    public string $customerFirstName;

    public string $customerLastName;

    public int $orderId;

    public string $orderDate;

    #[Assert\NotNull]
    public int $orderReturnStateId;

    public string $question;
}
