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
use ApiPlatform\Metadata\Parameters;
use ApiPlatform\Metadata\QueryParameter;
use PrestaShop\PrestaShop\Core\Domain\Order\Exception\OrderException;
use PrestaShop\PrestaShop\Core\Domain\Shipment\Exception\ShipmentException;
use PrestaShop\PrestaShop\Core\Domain\Shipment\Exception\ShipmentNotFoundException;
use PrestaShop\PrestaShop\Core\Domain\Shipment\Query\ListAvailableShipments;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSGetCollection;
use Symfony\Component\HttpFoundation\Response;

#[ApiResource(
    operations: [
        new CQRSGetCollection(
            uriTemplate: '/orders/{orderId}/available-shipments',
            requirements: ['orderId' => '\d+'],
            CQRSQuery: ListAvailableShipments::class,
            scopes: ['shipment_read'],
            CQRSQueryMapping: self::QUERY_MAPPING,
            ApiResourceMapping: self::API_RESOURCE_MAPPING,
            parameters: new Parameters([
                new QueryParameter(key: 'orderDetailIds', required: true, schema: [
                    'type' => 'array',
                    'items' => ['type' => 'integer'],
                ]),
            ]),
        ),
    ],
    exceptionToStatus: [
        ShipmentNotFoundException::class => Response::HTTP_NOT_FOUND,
        OrderException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        ShipmentException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
    ],
)]
class AvailableShipments
{
    public int $orderId;

    #[ApiProperty(openapiContext: ['type' => 'array', 'items' => ['type' => 'integer']])]
    public array $orderDetailIds = [];

    public int $shipmentId;

    public string $shipmentName;

    public bool $compatible;

    public const QUERY_MAPPING = [
        '[orderDetailIds]' => '[orderIdDetails]',
    ];

    public const API_RESOURCE_MAPPING = [
        '[id]' => '[shipmentId]',
        '[handleProduct]' => '[compatible]',
    ];
}
