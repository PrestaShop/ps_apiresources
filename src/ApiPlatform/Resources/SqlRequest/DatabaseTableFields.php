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

namespace PrestaShop\Module\APIResources\ApiPlatform\Resources\SqlRequest;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use PrestaShop\PrestaShop\Core\Domain\SqlManagement\Exception\SqlManagementConstraintException;
use PrestaShop\PrestaShop\Core\Domain\SqlManagement\Query\GetDatabaseTableFieldsList;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSGet;
use Symfony\Component\HttpFoundation\Response;

#[ApiResource(
    operations: [
        new CQRSGet(
            uriTemplate: '/sql-requests/database-tables/{tableName}/fields',
            requirements: ['tableName' => '[A-Za-z0-9_-]+'],
            CQRSQuery: GetDatabaseTableFieldsList::class,
            scopes: [
                'sql_management_read',
            ],
        ),
    ],
    exceptionToStatus: [
        SqlManagementConstraintException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
    ],
)]
class DatabaseTableFields
{
    #[ApiProperty(identifier: true)]
    public string $tableName;

    /**
     * @var array<int, array{name: string, type: string}>
     */
    #[ApiProperty(openapiContext: [
        'type' => 'array',
        'items' => [
            'type' => 'object',
            'properties' => [
                'name' => ['type' => 'string'],
                'type' => ['type' => 'string'],
            ],
        ],
    ])]
    public array $fields;
}
