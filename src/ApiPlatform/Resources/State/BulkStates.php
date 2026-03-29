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

namespace PrestaShop\Module\APIResources\ApiPlatform\Resources\State;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use PrestaShop\PrestaShop\Core\Domain\State\Command\BulkDeleteStateCommand;
use PrestaShop\PrestaShop\Core\Domain\State\Command\BulkToggleStateStatusCommand;
use PrestaShop\PrestaShop\Core\Domain\State\Command\BulkUpdateStateZoneCommand;
use PrestaShop\PrestaShop\Core\Domain\State\Exception\CannotToggleStateStatusException;
use PrestaShop\PrestaShop\Core\Domain\State\Exception\CannotUpdateStateException;
use PrestaShop\PrestaShop\Core\Domain\State\Exception\DeleteStateException;
use PrestaShop\PrestaShop\Core\Domain\State\Exception\StateNotFoundException;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSDelete;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSUpdate;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    operations: [
        new CQRSDelete(
            uriTemplate: '/states/bulk-delete',
            CQRSCommand: BulkDeleteStateCommand::class,
            scopes: ['state_write'],
        ),
        new CQRSUpdate(
            uriTemplate: '/states/bulk-update-status',
            output: false,
            CQRSCommand: BulkToggleStateStatusCommand::class,
            scopes: ['state_write'],
            CQRSCommandMapping: [
                '[enabled]' => '[expectedStatus]',
            ],
        ),
        new CQRSUpdate(
            uriTemplate: '/states/bulk-update-zone',
            output: false,
            CQRSCommand: BulkUpdateStateZoneCommand::class,
            scopes: ['state_write'],
        ),
    ],
    exceptionToStatus: [
        StateNotFoundException::class => Response::HTTP_NOT_FOUND,
        DeleteStateException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        CannotToggleStateStatusException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        CannotUpdateStateException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
    ],
)]
class BulkStates
{
    /**
     * @var int[]
     */
    #[ApiProperty(openapiContext: ['type' => 'array', 'items' => ['type' => 'integer'], 'example' => [1, 3]])]
    #[Assert\NotBlank]
    public array $stateIds;

    public bool $enabled;

    public int $zoneId;
}
