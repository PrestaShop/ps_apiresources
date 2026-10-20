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

namespace PrestaShop\Module\APIResources\ApiPlatform\Resources\Carrier;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use PrestaShop\PrestaShop\Core\Domain\Carrier\Command\BulkDeleteCarrierCommand;
use PrestaShop\PrestaShop\Core\Domain\Carrier\Command\BulkToggleCarrierStatusCommand;
use PrestaShop\PrestaShop\Core\Domain\Carrier\Exception\CarrierConstraintException;
use PrestaShop\PrestaShop\Core\Domain\Carrier\Exception\CarrierNotFoundException;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSDelete;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSUpdate;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * The bulk operations of the domain share this class: the body of each operation is documented from its
 * command, so only the fields that command takes appear; the validation groups keep the field of one
 * operation optional for the others.
 */
#[ApiResource(
    operations: [
        new CQRSDelete(
            uriTemplate: '/carriers/bulk-delete',
            CQRSCommand: BulkDeleteCarrierCommand::class,
            scopes: ['carrier_write'],
            allowEmptyBody: false,
            openapi: new OpenApiOperation(
                summary: 'Delete several carriers.',
                description: 'Deletes the listed carriers in the given order. The first unknown carrier stops the '
                    . 'operation with a 404: the carriers listed before it are already deleted.',
            ),
        ),
        new CQRSUpdate(
            uriTemplate: '/carriers/bulk-update-status',
            output: false,
            validationContext: ['groups' => ['Default', 'BulkUpdateStatus']],
            CQRSCommand: BulkToggleCarrierStatusCommand::class,
            CQRSCommandMapping: [
                '[enabled]' => '[expectedStatus]',
            ],
            scopes: ['carrier_write'],
            openapi: new OpenApiOperation(
                summary: 'Enable or disable several carriers.',
                description: 'Sets the status of the listed carriers in the given order. The first unknown carrier '
                    . 'stops the operation with a 404: the carriers listed before it are already updated.',
            ),
        ),
    ],
    exceptionToStatus: [
        CarrierNotFoundException::class => Response::HTTP_NOT_FOUND,
        CarrierConstraintException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
    ],
)]
class BulkCarriers
{
    /**
     * @var int[]
     */
    #[ApiProperty(openapiContext: ['type' => 'array', 'items' => ['type' => 'integer'], 'example' => [1, 3]])]
    #[Assert\NotBlank]
    #[Assert\All([new Assert\Type('integer'), new Assert\Positive()])]
    public array $carrierIds;

    #[ApiProperty(openapiContext: ['description' => 'Status to set on every listed carrier.'])]
    #[Assert\NotNull(groups: ['BulkUpdateStatus'])]
    public bool $enabled;
}
