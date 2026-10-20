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
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use PrestaShop\PrestaShop\Core\Domain\OrderReturn\Command\DeleteProductFromOrderReturnCommand;
use PrestaShop\PrestaShop\Core\Domain\OrderReturn\Exception\CannotDeleteLastProductFromOrderReturnException;
use PrestaShop\PrestaShop\Core\Domain\OrderReturn\Exception\OrderReturnConstraintException;
use PrestaShop\PrestaShop\Core\Domain\OrderReturn\Exception\OrderReturnNotFoundException;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSDelete;
use Symfony\Component\HttpFoundation\Response;

#[ApiResource(
    operations: [
        new CQRSDelete(
            uriTemplate: '/order-returns/{orderReturnId}/products/{orderDetailId}',
            requirements: ['orderReturnId' => '\d+', 'orderDetailId' => '\d+'],
            extraProperties: [
                'minVersion' => '9.2.0',
            ],
            CQRSCommand: DeleteProductFromOrderReturnCommand::class,
            scopes: ['order_return_write'],
            // A query parameter does not reach the command (only the URI variables and the body do): this
            // operation removes the classic line of the order detail, a customized line goes through the bulk delete
            openapi: new OpenApiOperation(
                summary: 'Remove a product line from an order return.',
                description: 'Removes the line of the order detail that has no customization. To remove a customized '
                    . 'line, use DELETE /order-returns/{orderReturnId}/products/bulk-delete with its customizationId. '
                    . 'An order return keeps at least one line (422).',
            ),
        ),
    ],
    exceptionToStatus: [
        OrderReturnNotFoundException::class => Response::HTTP_NOT_FOUND,
        OrderReturnConstraintException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        // A merchandise return must keep at least one product: a client error, not a 500
        CannotDeleteLastProductFromOrderReturnException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
    ],
)]
class OrderReturnProduct
{
    #[ApiProperty(identifier: true)]
    public int $orderReturnId;

    #[ApiProperty(identifier: true)]
    public int $orderDetailId;
}
