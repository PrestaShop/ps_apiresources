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
 * @author    Pascal Cescon <pascal.cescon@gmail.com>
 * @copyright Since 2007 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License version 3.0
 */

declare(strict_types=1);

namespace PrestaShop\Module\APIResources\ApiPlatform\Resources\Carrier;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use PrestaShop\PrestaShop\Core\Domain\Carrier\Command\BulkDeleteCarrierCommand;
use PrestaShop\PrestaShop\Core\Domain\Carrier\Command\BulkToggleCarrierStatusCommand;
use PrestaShop\PrestaShop\Core\Domain\Carrier\Exception\CannotDeleteCarrierException;
use PrestaShop\PrestaShop\Core\Domain\Carrier\Exception\CannotToggleCarrierStatusException;
use PrestaShop\PrestaShop\Core\Domain\Carrier\Exception\CarrierConstraintException;
use PrestaShop\PrestaShop\Core\Domain\Carrier\Exception\CarrierNotFoundException;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSDelete;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSUpdate;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    operations: [
        new CQRSDelete(
            uriTemplate: '/carriers/bulk-delete',
            CQRSCommand: BulkDeleteCarrierCommand::class,
            scopes: ['carrier_write'],
        ),
        new CQRSUpdate(
            uriTemplate: '/carriers/bulk-update-status',
            output: false,
            CQRSCommand: BulkToggleCarrierStatusCommand::class,
            scopes: ['carrier_write'],
            CQRSCommandMapping: [
                '[enabled]' => '[expectedStatus]',
            ],
        ),
    ],
    exceptionToStatus: [
        CarrierConstraintException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        CarrierNotFoundException::class => Response::HTTP_NOT_FOUND,
        CannotDeleteCarrierException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        CannotToggleCarrierStatusException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
    ],
)]
class BulkCarriers
{
    /**
     * @var int[]
     */
    #[ApiProperty(openapiContext: ['type' => 'array', 'items' => ['type' => 'integer'], 'example' => [1, 3]])]
    #[Assert\NotBlank]
    public array $carrierIds;

    public bool $enabled;
}
