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
use PrestaShop\PrestaShop\Core\Domain\State\Command\AddStateCommand;
use PrestaShop\PrestaShop\Core\Domain\State\Command\DeleteStateCommand;
use PrestaShop\PrestaShop\Core\Domain\State\Command\EditStateCommand;
use PrestaShop\PrestaShop\Core\Domain\State\Command\ToggleStateStatusCommand;
use PrestaShop\PrestaShop\Core\Domain\State\Exception\CannotAddStateException;
use PrestaShop\PrestaShop\Core\Domain\State\Exception\CannotToggleStateStatusException;
use PrestaShop\PrestaShop\Core\Domain\State\Exception\CannotUpdateStateException;
use PrestaShop\PrestaShop\Core\Domain\State\Exception\DeleteStateException;
use PrestaShop\PrestaShop\Core\Domain\State\Exception\StateConstraintException;
use PrestaShop\PrestaShop\Core\Domain\State\Exception\StateNotFoundException;
use PrestaShop\PrestaShop\Core\Domain\State\Query\GetStateForEditing;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSCreate;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSDelete;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSGet;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSPartialUpdate;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSUpdate;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    operations: [
        new CQRSGet(
            uriTemplate: '/states/{stateId}',
            requirements: ['stateId' => '\d+'],
            CQRSQuery: GetStateForEditing::class,
            scopes: ['state_read'],
            CQRSQueryMapping: self::QUERY_MAPPING,
        ),
        new CQRSCreate(
            uriTemplate: '/states',
            CQRSCommand: AddStateCommand::class,
            CQRSQuery: GetStateForEditing::class,
            scopes: ['state_write'],
            CQRSQueryMapping: self::QUERY_MAPPING,
            CQRSCommandMapping: self::COMMAND_MAPPING,
        ),
        new CQRSPartialUpdate(
            uriTemplate: '/states/{stateId}',
            requirements: ['stateId' => '\d+'],
            CQRSCommand: EditStateCommand::class,
            CQRSQuery: GetStateForEditing::class,
            scopes: ['state_write'],
            CQRSQueryMapping: self::QUERY_MAPPING,
        ),
        new CQRSDelete(
            uriTemplate: '/states/{stateId}',
            requirements: ['stateId' => '\d+'],
            CQRSCommand: DeleteStateCommand::class,
            scopes: ['state_write'],
        ),
        new CQRSUpdate(
            uriTemplate: '/states/{stateId}/toggle-status',
            requirements: ['stateId' => '\d+'],
            output: false,
            CQRSCommand: ToggleStateStatusCommand::class,
            scopes: ['state_write'],
        ),
    ],
    exceptionToStatus: [
        StateConstraintException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        StateNotFoundException::class => Response::HTTP_NOT_FOUND,
        CannotAddStateException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        CannotUpdateStateException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        DeleteStateException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        CannotToggleStateStatusException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
    ],
)]
class State
{
    #[ApiProperty(identifier: true)]
    public int $stateId;

    public int $countryId;

    public int $zoneId;

    #[Assert\NotBlank]
    public string $name;

    #[Assert\NotBlank]
    public string $isoCode;

    public bool $enabled;

    /**
     * @var int[]
     */
    public array $shopIds;

    public const QUERY_MAPPING = [
        '[isEnabled]' => '[enabled]',
        '[associatedShops]' => '[shopIds]',
    ];

    public const COMMAND_MAPPING = [
        '[enabled]' => '[active]',
    ];
}
