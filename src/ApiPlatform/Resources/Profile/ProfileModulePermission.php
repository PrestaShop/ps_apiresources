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

namespace PrestaShop\Module\APIResources\ApiPlatform\Resources\Profile;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use PrestaShop\PrestaShop\Core\Domain\Profile\Permission\Command\UpdateModulePermissionsCommand;
use PrestaShop\PrestaShop\Core\Domain\Profile\Permission\Exception\InvalidPermissionValueException;
use PrestaShop\PrestaShop\Core\Domain\Profile\Permission\Exception\PermissionUpdateException;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSUpdate;
use Symfony\Component\HttpFoundation\Response;

#[ApiResource(
    operations: [
        new CQRSUpdate(
            uriTemplate: '/profiles/{profileId}/module-permissions',
            requirements: ['profileId' => '\d+'],
            output: false,
            // No read: false here. With it, ApiPlatform deserializes the body and builds the
            // command before the scope check runs, so an unauthorized request carrying no body
            // answers 400 instead of 403. /categories/bulk-update-status is declared the same
            // way and does answer 403.
            CQRSCommand: UpdateModulePermissionsCommand::class,
            CQRSCommandMapping: self::COMMAND_MAPPING,
            scopes: ['profile_write'],
        ),
    ],
    exceptionToStatus: [
        InvalidPermissionValueException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        PermissionUpdateException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
    ],
)]
class ProfileModulePermission
{
    #[ApiProperty(identifier: true)]
    public int $profileId;

    public int $moduleId;

    /**
     * One of view, add, edit, delete, all.
     *
     * Deliberately unconstrained: ApiPlatform validates before the scope check runs, so a
     * NotBlank here answers 422 to an unauthorized request that carries no body, where it
     * should answer 403. The command rejects an invalid value on its own, through
     * InvalidPermissionValueException, which is mapped to 422 below.
     */
    public string $permission;

    public bool $enabled;

    /**
     * UpdateModulePermissionsCommand expects an $isActive constructor argument.
     */
    public const COMMAND_MAPPING = [
        '[enabled]' => '[isActive]',
    ];
}
