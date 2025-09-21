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

use ApiPlatform\Metadata\ApiResource;
use PrestaShop\Module\APIResources\ApiPlatform\Serializer\Callbacks;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSPartialUpdate;
use Symfony\Component\HttpFoundation\Response;

#[ApiResource(
    operations: [
        new CQRSPartialUpdate(
            uriTemplate: '/orders/{orderId}/tracking',
            requirements: ['orderId' => '\\d+'],
            scopes: ['order_write'],
            CQRSCommand: \PrestaShop\PrestaShop\Core\Domain\Order\Command\UpdateOrderShippingDetailsCommand::class,
            // We need currentOrderCarrierId from Query, newCarrierId & tracking from payload
            CQRSQuery: \PrestaShop\PrestaShop\Core\Domain\Order\Query\GetOrderForViewing::class,
            CQRSQueryMapping: [
                '[carrierId]' => '[currentOrderCarrierId]',
            ],
            CQRSCommandMapping: [
                '[orderId]' => '[orderId]',
                '[currentOrderCarrierId]' => '[currentOrderCarrierId]',
                '[carrierId]' => '[newCarrierId]',
                '[number]' => '[number]',
            ],
            allowEmptyBody: false,
            denormalizationContext: [
                'disable_type_enforcement' => true,
                'callbacks' => [
                    'orderId' => [Callbacks::class, 'toInt'],
                    'currentOrderCarrierId' => [Callbacks::class, 'toInt'],
                    'newCarrierId' => [Callbacks::class, 'toInt'],
                    'number' => [Callbacks::class, 'toString'],
                ],
                'default_constructor_arguments' => [
                    \PrestaShop\PrestaShop\Core\Domain\Order\Command\UpdateOrderShippingDetailsCommand::class => [
                        'currentOrderCarrierId' => 0,
                        'newCarrierId' => 0,
                    ],
                ],
            ],
        ),
    ],
    exceptionToStatus: [
        \PrestaShop\PrestaShop\Core\Domain\Order\Exception\OrderNotFoundException::class => Response::HTTP_NOT_FOUND,
        \PrestaShop\PrestaShop\Core\Domain\Order\Exception\OrderException::class => Response::HTTP_NOT_FOUND,
        \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException::class => Response::HTTP_FORBIDDEN,
        \Symfony\Component\Serializer\Exception\NotNormalizableValueException::class => Response::HTTP_NOT_FOUND,
    ],
)]
/**
 * API Resource handling the order tracking update action.
 */
class OrderTracking
{
    /** @var int|null New carrier ID */
    public ?int $newCarrierId = null;

    /** @var string|null Tracking number */
    public ?string $number = null;

    /** @var string|null Tracking URL */
    public ?string $url = null;
}
