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

use ApiPlatform\Metadata\ApiResource;
use PrestaShop\PrestaShop\Core\Domain\Carrier\Exception\CarrierConstraintException;
use PrestaShop\PrestaShop\Core\Domain\Shipment\Command\SwitchShipmentCarrierCommand;
use PrestaShop\PrestaShop\Core\Domain\Shipment\Exception\CannotSaveShipmentException;
use PrestaShop\PrestaShop\Core\Domain\Shipment\Exception\ShipmentException;
use PrestaShop\PrestaShop\Core\Domain\Shipment\Exception\ShipmentNotFoundException;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSPartialUpdate;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    operations: [
        new CQRSPartialUpdate(
            uriTemplate: '/shipments/{shipmentId}/carriers',
            requirements: ['shipmentId' => '\d+'],
            output: false,
            status: Response::HTTP_NO_CONTENT,
            CQRSCommand: SwitchShipmentCarrierCommand::class,
            scopes: ['shipment_write'],
        ),
    ],
    exceptionToStatus: [
        ShipmentNotFoundException::class => Response::HTTP_NOT_FOUND,
        CannotSaveShipmentException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        CarrierConstraintException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        ShipmentException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
    ],
)]
class ShipmentCarrier
{
    public int $shipmentId;

    #[Assert\NotNull]
    public int $carrierId;
}
