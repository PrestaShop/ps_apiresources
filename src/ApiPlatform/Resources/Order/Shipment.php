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
use PrestaShop\PrestaShop\Core\Domain\Shipment\Exception\ShipmentException;
use PrestaShop\PrestaShop\Core\Domain\Shipment\Exception\ShipmentNotFoundException;
use PrestaShop\PrestaShop\Core\Domain\Shipment\Query\GetShipmentForEditing;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSGet;
use Symfony\Component\HttpFoundation\Response;

#[ApiResource(
    operations: [
        new CQRSGet(
            uriTemplate: '/orders/{orderId}/shipments/{shipmentId}',
            requirements: ['orderId' => '\d+', 'shipmentId' => '\d+'],
            CQRSQuery: GetShipmentForEditing::class,
            scopes: ['shipment_read'],
            CQRSQueryMapping: self::QUERY_MAPPING,
        ),
    ],
    exceptionToStatus: [
        ShipmentNotFoundException::class => Response::HTTP_NOT_FOUND,
        OrderException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        ShipmentException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
    ],
)]
class Shipment
{
    public int $orderId;

    public int $shipmentId;

    public int $carrierId;

    public string $trackingNumber;

    #[ApiProperty(openapiContext: [
        'type' => 'object',
        'additionalProperties' => ['type' => 'integer'],
        'description' => 'Map of productId to quantity for the products currently assigned to this shipment.',
    ])]
    public array $selectedProducts = [];

    public const QUERY_MAPPING = [
        '[productsIds]' => '[selectedProducts]',
    ];
}
