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
 * @author    PrestaShop SA and Contributors
 * @copyright Since 2007 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License version 3.0
 */

declare(strict_types=1);

namespace PrestaShop\Module\APIResources\ApiPlatform\Resources\Order;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Patch;
use PrestaShop\Module\APIResources\ApiPlatform\Resources\Order\State\OrderProcessor;
use Symfony\Component\HttpFoundation\Response;

#[ApiResource(
    operations: [
        new Patch(
            uriTemplate: '/order/{orderId}/tracking',
            requirements: ['orderId' => '\\d+'],
            processor: OrderProcessor::class,
        ),
    ],
    denormalizationContext: ['skip_null_values' => false],
    security: "oauth_scope('order_write')",
    exceptionToStatus: [
        \RuntimeException::class => Response::HTTP_NOT_FOUND,
        \InvalidArgumentException::class => Response::HTTP_BAD_REQUEST,
    ],
)]
/**
 * API Resource handling the order tracking update action.
 */
class OrderTracking
{
    /** @var string|null Tracking number */
    public ?string $number = null;

    /** @var string|null Tracking URL */
    public ?string $url = null;
}


