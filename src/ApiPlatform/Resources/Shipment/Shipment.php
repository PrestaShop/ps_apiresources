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

namespace PrestaShop\Module\APIResources\ApiPlatform\Resources\Shipment;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use PrestaShop\PrestaShop\Core\Domain\Carrier\Exception\CarrierConstraintException;
use PrestaShop\PrestaShop\Core\Domain\Shipment\Command\EditShipment;
use PrestaShop\PrestaShop\Core\Domain\Shipment\Exception\ShipmentException;
use PrestaShop\PrestaShop\Core\Domain\Shipment\Exception\ShipmentNotFoundException;
use PrestaShop\PrestaShop\Core\Domain\Shipment\Query\GetShipmentForViewing;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSGet;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSUpdate;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    operations: [
        new CQRSGet(
            uriTemplate: '/shipments/{shipmentId}',
            requirements: ['shipmentId' => '\d+'],
            CQRSQuery: GetShipmentForViewing::class,
            scopes: ['shipment_read'],
            ApiResourceMapping: [
                '[id]' => '[shipmentId]',
                '[carrierSummary]' => '[carrier]',
                '[shippingAdressSummary]' => '[shippingAddress]',
            ],
        ),
        new CQRSUpdate(
            uriTemplate: '/shipments/{shipmentId}',
            requirements: ['shipmentId' => '\d+'],
            output: false,
            CQRSCommand: EditShipment::class,
            scopes: ['shipment_write'],
        ),
    ],
    normalizationContext: ['skip_null_values' => false],
    exceptionToStatus: [
        ShipmentNotFoundException::class => Response::HTTP_NOT_FOUND,
        ShipmentException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        CarrierConstraintException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
    ],
)]
class Shipment
{
    #[ApiProperty(identifier: true)]
    public int $shipmentId;

    public ?string $trackingNumber;

    /**
     * @var array{id: int, name: string}
     */
    public array $carrier;

    /**
     * @var array<string, string>
     */
    public array $shippingAddress;

    #[ApiProperty(readable: false)]
    #[Assert\NotBlank]
    public int $carrierId;
}
