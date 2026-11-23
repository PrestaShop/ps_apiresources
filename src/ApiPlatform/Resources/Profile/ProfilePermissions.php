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
use ApiPlatform\Metadata\Parameters;
use ApiPlatform\Metadata\QueryParameter;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use PrestaShop\PrestaShop\Core\Domain\Profile\Exception\ProfileConstraintException;
use PrestaShop\PrestaShop\Core\Domain\Profile\Permission\Query\GetPermissionsForConfiguration;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSGet;
use Symfony\Component\HttpFoundation\Response;

/**
 * The permission configuration of the whole back office.
 *
 * employeeProfileId is not the profile being read: GetPermissionsForConfiguration returns the
 * permissions of every profile, and takes the profile of the employee looking at them only to
 * decide hasEmployeeEditPermission. It is therefore a query parameter of a collection level
 * read, not a path segment — /profiles/{employeeProfileId}/permissions read as "the
 * permissions of profile X", which is not what this returns.
 */
#[ApiResource(
    operations: [
        new CQRSGet(
            uriTemplate: '/profiles/permissions',
            CQRSQuery: GetPermissionsForConfiguration::class,
            CQRSQueryMapping: self::QUERY_MAPPING,
            scopes: ['profile_read'],
            parameters: new Parameters([
                new QueryParameter(
                    key: 'employeeProfileId',
                    schema: ['type' => 'integer'],
                    required: true,
                    description: 'Profile of the employee the configuration is computed for.'
                ),
            ]),
            openapi: new OpenApiOperation(
                summary: 'Get the permission configuration of the back office.',
                description: 'Returns the page and module permissions of every profile, with the catalogues of '
                    . 'profiles, pages and permissions. employeeProfileId is the profile of the employee looking at '
                    . 'the configuration: hasEmployeeEditPermission tells whether that profile may edit the '
                    . 'permissions. Before PrestaShop 9.2.2 (PrestaShop/PrestaShop#43298), this endpoint answers 500 '
                    . 'to an API client, since the core asks the back office voters about a logged-in employee. '
                    . 'bulkConfiguration is always empty until the core exposes it (PrestaShop/PrestaShop#42048).',
            ),
        ),
    ],
    exceptionToStatus: [
        // employeeProfileId lower than 1
        ProfileConstraintException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
    ],
)]
class ProfilePermissions
{
    // ConfigurablePermissions exposes hasEmployeeEditPermission(), which the serializer
    // normalizes to "employeeEditPermission" — without this the property is never populated
    // and the field silently disappears from the response.
    public const QUERY_MAPPING = [
        '[employeeEditPermission]' => '[hasEmployeeEditPermission]',
    ];

    #[ApiProperty(identifier: true)]
    public int $employeeProfileId;

    public bool $hasEmployeeEditPermission;

    /** Map { profileId: { tabId: {view, add, edit, delete}[] } } */
    #[ApiProperty(openapiContext: ['type' => 'object'])]
    public array $profilePermissionsForTabs;

    /** Map { profileId: { moduleId: {view, configure, uninstall}[] } } */
    #[ApiProperty(openapiContext: ['type' => 'object'])]
    public array $profilePermissionsForModules;

    /**
     * Map { profileId: { view, add, edit, delete, all } }.
     *
     * Requires PrestaShop/PrestaShop#42048: ConfigurablePermissions only exposes the per
     * profile isBulkXConfigurationEnabled() accessors on the current cores, so the raw map is
     * unreachable and this stays an empty array until that core PR is merged.
     */
    #[ApiProperty(openapiContext: ['type' => 'object'])]
    public array $bulkConfiguration = [];

    public array $profiles;

    public array $tabs;

    public array $permissions;
}
