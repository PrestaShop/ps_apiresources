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

namespace PrestaShop\Module\APIResources\ApiPlatform\Resources\QuickAccess;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use PrestaShop\PrestaShop\Core\Domain\QuickAccess\Command\AddQuickAccessCommand;
use PrestaShop\PrestaShop\Core\Domain\QuickAccess\Command\DeleteQuickAccessCommand;
use PrestaShop\PrestaShop\Core\Domain\QuickAccess\Command\EditQuickAccessCommand;
use PrestaShop\PrestaShop\Core\Domain\QuickAccess\Command\ToggleQuickAccessNewWindowCommand;
use PrestaShop\PrestaShop\Core\Domain\QuickAccess\Exception\CannotAddQuickAccessException;
use PrestaShop\PrestaShop\Core\Domain\QuickAccess\Exception\CannotDeleteQuickAccessException;
use PrestaShop\PrestaShop\Core\Domain\QuickAccess\Exception\CannotEditQuickAccessException;
use PrestaShop\PrestaShop\Core\Domain\QuickAccess\Exception\QuickAccessConstraintException;
use PrestaShop\PrestaShop\Core\Domain\QuickAccess\Exception\QuickAccessNotFoundException;
use PrestaShop\PrestaShop\Core\Domain\QuickAccess\Query\GetQuickAccessForEditing;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSCreate;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSDelete;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSGet;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSPartialUpdate;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSUpdate;
use PrestaShopBundle\ApiPlatform\Metadata\LocalizedValue;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    operations: [
        new CQRSCreate(
            uriTemplate: '/quick-accesses',
            CQRSCommand: AddQuickAccessCommand::class,
            scopes: ['quick_access_write'],
            CQRSCommandMapping: self::COMMAND_MAPPING,
        ),
        new CQRSGet(
            uriTemplate: '/quick-accesses/{quickAccessId}',
            requirements: ['quickAccessId' => '\d+'],
            CQRSQuery: GetQuickAccessForEditing::class,
            scopes: ['quick_access_read'],
        ),
        new CQRSPartialUpdate(
            uriTemplate: '/quick-accesses/{quickAccessId}',
            requirements: ['quickAccessId' => '\d+'],
            read: false,
            CQRSCommand: EditQuickAccessCommand::class,
            scopes: ['quick_access_write'],
            CQRSCommandMapping: self::COMMAND_MAPPING,
        ),
        new CQRSDelete(
            uriTemplate: '/quick-accesses/{quickAccessId}',
            requirements: ['quickAccessId' => '\d+'],
            CQRSCommand: DeleteQuickAccessCommand::class,
            scopes: ['quick_access_write'],
        ),
        new CQRSUpdate(
            uriTemplate: '/quick-accesses/{quickAccessId}/new-window-toggles',
            requirements: ['quickAccessId' => '\d+'],
            allowEmptyBody: true,
            read: false,
            CQRSCommand: ToggleQuickAccessNewWindowCommand::class,
            scopes: ['quick_access_write'],
        ),
    ],
    exceptionToStatus: [
        QuickAccessNotFoundException::class => Response::HTTP_NOT_FOUND,
        QuickAccessConstraintException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        CannotAddQuickAccessException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        CannotEditQuickAccessException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        CannotDeleteQuickAccessException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
    ],
)]
class QuickAccess
{
    #[ApiProperty(identifier: true)]
    public int $quickAccessId;

    #[LocalizedValue]
    #[Assert\NotBlank(groups: ['Create'])]
    public array $localizedNames;

    #[Assert\NotBlank(groups: ['Create'])]
    public string $link;

    #[Assert\NotNull(groups: ['Create'])]
    public bool $newWindow;

    public const COMMAND_MAPPING = [
        '[localizedNames]' => '[localizedNames]',
        '[link]' => '[link]',
        '[newWindow]' => '[newWindow]',
    ];
}
