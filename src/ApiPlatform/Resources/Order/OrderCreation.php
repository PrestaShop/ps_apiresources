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
use PrestaShop\Module\APIResources\ApiPlatform\Serializer\Callbacks;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSCreate;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    operations: [
        new CQRSCreate(
            uriTemplate: '/orders',
            allowEmptyBody: true,
            scopes: ['order_write'],
            CQRSCommand: \PrestaShop\PrestaShop\Core\Domain\Order\Command\AddOrderFromBackOfficeCommand::class,
            CQRSCommandMapping: [
                '[cartId]' => '[cartId]',
                '[employeeId]' => '[employeeId]',
                '[paymentModuleName]' => '[paymentModuleName]',
                '[orderStateId]' => '[orderStateId]',
                '[orderMessage]' => '[orderMessage]',
            ],
            denormalizationContext: [
                'skip_null_values' => false,
                'disable_type_enforcement' => true,
                'allow_extra_attributes' => true,
                'callbacks' => [
                    'cartId' => [Callbacks::class, 'toInt'],
                    'employeeId' => [Callbacks::class, 'toInt'],
                    'orderStateId' => [Callbacks::class, 'toInt'],
                    'orderMessage' => [Callbacks::class, 'toString'],
                    'paymentModuleName' => [Callbacks::class, 'toString'],
                ],
                'default_constructor_arguments' => [
                    \PrestaShop\PrestaShop\Core\Domain\Order\Command\AddOrderFromBackOfficeCommand::class => [
                        'orderMessage' => '',
                        'paymentModuleName' => 'ps_wirepayment',
                    ],
                ],
            ],
            CQRSQuery: \PrestaShop\PrestaShop\Core\Domain\Order\Query\GetOrderForViewing::class,
            CQRSQueryMapping: [
                '[orderId]' => '[orderId]',
            ],
        ),
    ],
    normalizationContext: ['skip_null_values' => false],
    exceptionToStatus: [
        \PrestaShop\PrestaShop\Core\Domain\Order\Exception\OrderException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException::class => Response::HTTP_FORBIDDEN,
        \Symfony\Component\Validator\Exception\ValidationFailedException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        \Symfony\Component\Serializer\Exception\NotNormalizableValueException::class => Response::HTTP_BAD_REQUEST,
    ],
)]
class OrderCreation
{
    #[ApiProperty(identifier: true, writable: false)]
    public int $orderId = 0;

    #[Assert\NotBlank]
    public int $cartId;

    #[Assert\NotBlank]
    public int $employeeId;

    public string $orderMessage = '';

    #[Assert\NotBlank]
    public string $paymentModuleName;

    #[Assert\NotBlank]
    public int $orderStateId;
}
