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

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Parameters;
use ApiPlatform\Metadata\QueryParameter;
use PrestaShop\PrestaShop\Core\Domain\OrderReturn\Command\DeleteProductFromOrderReturnCommand;
use PrestaShop\PrestaShop\Core\Domain\OrderReturn\Exception\OrderReturnConstraintException;
use PrestaShop\PrestaShop\Core\Domain\OrderReturn\Exception\OrderReturnNotFoundException;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSDelete;
use Symfony\Component\HttpFoundation\Response;

#[ApiResource(
    operations: [
        new CQRSDelete(
            uriTemplate: '/order-returns/{orderReturnId}/products/{orderDetailId}',
            requirements: ['orderReturnId' => '\d+', 'orderDetailId' => '\d+'],
            CQRSCommand: DeleteProductFromOrderReturnCommand::class,
            scopes: ['order_return_write'],
            parameters: new Parameters([
                new QueryParameter(
                    key: 'customizationId',
                    required: false,
                    description: 'Customization id when removing a customized line; defaults to 0'
                ),
            ]),
            openapiContext: [
                'parameters' => [
                    ['name' => 'customizationId', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'integer', 'default' => 0]],
                ],
            ],
        ),
    ],
    exceptionToStatus: [
        OrderReturnNotFoundException::class => Response::HTTP_NOT_FOUND,
        OrderReturnConstraintException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
    ],
)]
class OrderReturnProduct
{
    public int $orderReturnId;

    public int $orderDetailId;

    public int $customizationId = 0;
}
