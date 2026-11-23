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
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use PrestaShop\PrestaShop\Core\Domain\Profile\Exception\ProfileConstraintException;
use PrestaShop\PrestaShop\Core\Domain\Profile\Permission\Command\UpdateTabPermissionsCommand;
use PrestaShop\PrestaShop\Core\Domain\Profile\Permission\Exception\InvalidPermissionValueException;
use PrestaShop\PrestaShop\Core\Domain\Profile\Permission\Exception\PermissionUpdateException;
use PrestaShop\PrestaShop\Core\Domain\Profile\Permission\ValueObject\ControllerAllPermissions;
use PrestaShop\PrestaShop\Core\Domain\Profile\Permission\ValueObject\ControllerPermission;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSUpdate;
use Symfony\Component\HttpFoundation\Response;

#[ApiResource(
    operations: [
        new CQRSUpdate(
            uriTemplate: '/profiles/{profileId}/tab-permissions',
            requirements: ['profileId' => '\d+'],
            output: false,
            // Both of these matter for an unauthorized request, which carries no body:
            // allowEmptyBody keeps the JSON decoder from choking on it, and the absence of
            // read: false keeps ApiPlatform from building the CQRS command before the scope
            // check runs. /categories/bulk-update-status is declared the same way, and it does
            // answer 403.
            allowEmptyBody: true,
            CQRSCommand: UpdateTabPermissionsCommand::class,
            CQRSCommandMapping: self::COMMAND_MAPPING,
            scopes: ['profile_write'],
            openapi: new OpenApiOperation(
                summary: 'Grant or revoke a back office page permission of a profile.',
                description: 'Sets one permission (view, add, edit, delete, or all of them) of the profile on one back '
                    . 'office page (tabId), or on every page with tabId -1, like the permissions page of the back '
                    . 'office. The core does not check that the profile exists: an unknown profileId answers 204 and '
                    . 'stores a permission row for that id.',
            ),
        ),
    ],
    exceptionToStatus: [
        ProfileConstraintException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        InvalidPermissionValueException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        PermissionUpdateException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
    ],
)]
class ProfileTabPermission
{
    #[ApiProperty(identifier: true)]
    public int $profileId;

    #[ApiProperty(openapiContext: ['description' => 'Back office page (tab) id, -1 for every page.', 'example' => 10])]
    public int $tabId;

    /**
     * Deliberately unconstrained: ApiPlatform validates before the scope check runs, so a
     * NotBlank here answers 422 to an unauthorized request that carries no body, where it
     * should answer 403. The command rejects an invalid value on its own, through
     * InvalidPermissionValueException, which is mapped to 422 below.
     */
    #[ApiProperty(openapiContext: [
        'description' => 'Permission to set: view, add, edit, delete, or all of them.',
        'enum' => [...ControllerPermission::SUPPORTED_PERMISSIONS, ControllerAllPermissions::ALL],
    ])]
    public string $permission;

    public bool $enabled;

    /**
     * UpdateTabPermissionsCommand expects an $isActive constructor argument.
     */
    public const COMMAND_MAPPING = [
        '[enabled]' => '[isActive]',
    ];
}
