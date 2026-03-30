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
use PrestaShop\PrestaShop\Core\Domain\OrderReturn\Command\UpdateOrderReturnStateCommand;
use PrestaShop\PrestaShop\Core\Domain\OrderReturn\Exception\OrderReturnConstraintException;
use PrestaShop\PrestaShop\Core\Domain\OrderReturn\Exception\OrderReturnNotFoundException;
use PrestaShop\PrestaShop\Core\Domain\OrderReturn\Query\GetOrderReturnForEditing;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSGet;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSPartialUpdate;
use Symfony\Component\HttpFoundation\Response;

#[ApiResource(
    operations: [
        new CQRSGet(
            uriTemplate: '/orders/{orderReturnId}',
            requirements: ['orderReturnId' => '\d+'],
            openapiContext: ['summary' => 'Get order return', 'description' => 'Retrieves an order return for editing'],
            CQRSQuery: GetOrderReturnForEditing::class,
            scopes: [
                'order_read',
            ],
        ),
        new CQRSPartialUpdate(
            uriTemplate: '/orders/{orderReturnId}/states',
            requirements: ['orderReturnId' => '\d+'],
            openapiContext: ['summary' => 'Update order return state', 'description' => 'Updates the state of an order return'],
            CQRSCommand: UpdateOrderReturnStateCommand::class,
            CQRSQuery: GetOrderReturnForEditing::class,
            scopes: [
                'order_write',
            ],
        ),
    ],
    exceptionToStatus: [
        OrderReturnNotFoundException::class => Response::HTTP_NOT_FOUND,
        OrderReturnConstraintException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
    ],
)]
class OrderReturn
{
    #[ApiProperty(identifier: true)]
    public int $orderReturnId;

    public int $orderReturnStateId;
}
