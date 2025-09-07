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
 */

declare(strict_types=1);

namespace PrestaShop\Module\APIResources\ApiPlatform\Resources\Order;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use PrestaShop\Module\APIResources\ApiPlatform\Serializer\Callbacks;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSPost;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    operations: [
        new CQRSPost(
            uriTemplate: '/orders/status-bulk',
            output: false,
            CQRSCommand: \PrestaShop\PrestaShop\Core\Domain\Order\Command\BulkChangeOrderStatusCommand::class,
            scopes: ['order_write'],
            CQRSCommandMapping: [
                '[orderIds]' => '[orderIds]',
                '[statusId]' => '[newOrderStatusId]',
            ],
            openapiContext: [
                'summary' => 'Bulk update order status',
            ],
            allowEmptyBody: false,
        ),
    ],
    denormalizationContext: [
        'skip_null_values' => false,
        'disable_type_enforcement' => true,
        'callbacks' => [
            'orderIds' => [Callbacks::class, 'toIntArray'],
            'statusId' => [Callbacks::class, 'toInt'],
        ],
    ],
    exceptionToStatus: [
        \PrestaShop\PrestaShop\Core\Domain\Order\Exception\OrderException::class => Response::HTTP_NOT_FOUND,
        \Symfony\Component\Serializer\Exception\NotNormalizableValueException::class => Response::HTTP_NOT_FOUND,
    ],
)]
class BulkOrderStatus
{
    /**
     * @var int[]
     */
    #[ApiProperty(openapiContext: ['type' => 'array', 'items' => ['type' => 'integer'], 'example' => [1, 2]])]
    #[Assert\Count(min: 1)]
    #[Assert\All([
        new Assert\Type('int'),
        new Assert\Positive(),
    ])]
    public array $orderIds = [];

    /**
     * @var int
     */
    #[Assert\NotBlank]
    #[Assert\Type('int')]
    #[Assert\Positive]
    public int $statusId;
}

