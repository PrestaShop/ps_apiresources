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
            uriTemplate: '/orders/{orderId}/note',
            requirements: ['orderId' => '\\d+'],
            scopes: ['order_write'],
            CQRSCommand: \PrestaShop\PrestaShop\Core\Domain\Order\Command\SetInternalOrderNoteCommand::class,
            CQRSCommandMapping: [
                '[orderId]' => '[orderId]',
                '[internalNote]' => '[note]',
            ],
            allowEmptyBody: false,
        ),
    ],
    denormalizationContext: [
        'skip_null_values' => false,
        'disable_type_enforcement' => true,
        'callbacks' => [
            'orderId' => [Callbacks::class, 'toInt'],
        ],
    ],
    exceptionToStatus: [
        \PrestaShop\PrestaShop\Core\Domain\Order\Exception\OrderException::class => Response::HTTP_NOT_FOUND,
        \Symfony\Component\Serializer\Exception\NotNormalizableValueException::class => Response::HTTP_NOT_FOUND,
    ],
)]
/**
 * API Resource handling the order note update action.
 */
class OrderNote
{
    /** @var string|null Internal note */
    public ?string $note = null;
}
