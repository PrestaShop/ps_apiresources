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
use PrestaShop\PrestaShop\Core\Domain\Order\Exception\OrderException;
use PrestaShop\PrestaShop\Core\Domain\Order\Exception\OrderNotFoundException;
use PrestaShop\PrestaShop\Core\Domain\Order\Product\Command\DeleteProductFromOrderCommand;
use PrestaShop\PrestaShop\Core\Domain\Order\Product\Command\UpdateProductInOrderCommand;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSDelete;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSUpdate;
use Symfony\Component\HttpFoundation\Response;

#[ApiResource(
    operations: [
        new CQRSUpdate(
            uriTemplate: '/orders/{orderId}/products/{orderDetailId}',
            requirements: ['orderId' => '\d+', 'orderDetailId' => '\d+'],
            output: false,
            CQRSCommand: UpdateProductInOrderCommand::class,
            scopes: ['order_write'],
        ),
        new CQRSDelete(
            uriTemplate: '/orders/{orderId}/products/{orderDetailId}',
            requirements: ['orderId' => '\d+', 'orderDetailId' => '\d+'],
            output: false,
            CQRSCommand: DeleteProductFromOrderCommand::class,
            scopes: ['order_write'],
        ),
    ],
    exceptionToStatus: [
        OrderNotFoundException::class => Response::HTTP_NOT_FOUND,
        OrderException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
    ],
)]
class OrderProduct
{
    #[ApiProperty(identifier: true)]
    public int $orderId;

    #[ApiProperty(identifier: true)]
    public int $orderDetailId;

    public string $priceTaxIncluded;

    public string $priceTaxExcluded;

    public int $quantity;

    public ?int $orderInvoiceId;
}
