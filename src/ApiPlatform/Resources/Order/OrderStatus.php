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
use PrestaShop\PrestaShop\Core\CommandBus\CommandBusInterface;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSPartialUpdate;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    operations: [
        new CQRSPartialUpdate(
            uriTemplate: '/orders/{orderId}/status',
            requirements: ['orderId' => '\\d+'],
            scopes: ['order_write'],
            CQRSCommand: \PrestaShop\PrestaShop\Core\Domain\Order\Command\UpdateOrderStatusCommand::class,
            CQRSCommandMapping: [
                '[orderId]' => '[orderId]',
                '[statusId]' => '[newOrderStatusId]',
            ],
            allowEmptyBody: false,
        ),
    ],
    denormalizationContext: [
        'skip_null_values' => false,
        'disable_type_enforcement' => true,
        'callbacks' => [
            'orderId' => [Callbacks::class, 'toInt'],
            'statusId' => [Callbacks::class, 'toInt'],
        ],
    ],
    exceptionToStatus: [
        \PrestaShop\PrestaShop\Core\Domain\Order\Exception\OrderException::class => Response::HTTP_NOT_FOUND,
        \Symfony\Component\Serializer\Exception\NotNormalizableValueException::class => Response::HTTP_NOT_FOUND,
    ],
)]
/**
 * API Resource handling the order status update action.
 */
#[Assert\Expression(
    'this.statusId !== null || this.statusCode !== null',
    message: 'Either statusId or statusCode must be provided'
)]
class OrderStatus
{
    /** @var int|null Target order status ID */
    public ?int $statusId = null;

    /** @var string|null Optional business status code */
    public ?string $statusCode = null;

    public function __construct(
        private CommandBusInterface $queryBus,
        ?int $statusId = null,
        ?string $statusCode = null,
    ) {
        if (null !== $statusCode) {
            $this->statusId = $this->resolveStatusId($statusCode);
            $this->statusCode = $statusCode;
        } else {
            $this->statusId = $statusId;
            $this->statusCode = $statusCode;
        }

        if (null === $this->statusId) {
            throw new \InvalidArgumentException('Either statusId or statusCode must be provided');
        }
    }

    private function resolveStatusId(string $statusCode): int
    {
        if (class_exists(\PrestaShop\PrestaShop\Core\Domain\Order\Query\GetOrderStatusIdByCode::class)) {
            try {
                /** @var int $id */
                $id = $this->queryBus->handle(
                    new \PrestaShop\PrestaShop\Core\Domain\Order\Query\GetOrderStatusIdByCode($statusCode)
                );

                return $id;
            } catch (\Throwable) {
                // Fallback to configuration lookup below
            }
        }

        $id = (int) \Configuration::get($statusCode);
        if ($id <= 0) {
            throw new \InvalidArgumentException('Unknown order status code');
        }

        return $id;
    }
}
