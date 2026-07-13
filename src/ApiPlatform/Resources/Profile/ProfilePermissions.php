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
use PrestaShop\PrestaShop\Core\Domain\Profile\Permission\Query\GetPermissionsForConfiguration;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSGet;

#[ApiResource(
    operations: [
        new CQRSGet(
            uriTemplate: '/profiles/{employeeProfileId}/permissions',
            requirements: ['employeeProfileId' => '\d+'],
            CQRSQuery: GetPermissionsForConfiguration::class,
            scopes: ['profile_read'],
        ),
    ],
)]
class ProfilePermissions
{
    #[ApiProperty(identifier: true)]
    public int $employeeProfileId;

    public bool $hasEmployeeEditPermission;

    /** Map { profileId: { tabId: {view, add, edit, delete}[] } } */
    #[ApiProperty(openapiContext: ['type' => 'object'])]
    public array $profilePermissionsForTabs;

    /** Map { profileId: { moduleId: {view, configure, uninstall}[] } } */
    #[ApiProperty(openapiContext: ['type' => 'object'])]
    public array $profilePermissionsForModules;

    /** Map { profileId: { view, add, edit, delete, all } } */
    #[ApiProperty(openapiContext: ['type' => 'object'])]
    public array $bulkConfiguration;

    public array $profiles;

    public array $tabs;

    public array $permissions;
}
