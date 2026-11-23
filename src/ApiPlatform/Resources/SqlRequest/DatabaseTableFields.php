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

namespace PrestaShop\Module\APIResources\ApiPlatform\Resources\SqlRequest;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use PrestaShop\Module\APIResources\ApiPlatform\Resources\SqlRequest\Dto\DatabaseTableField;
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
            openapi: new OpenApiOperation(
                summary: 'List the columns of a database table.',
                description: 'Returns the name and the MySQL type of each column of the table, which must be one of '
                    . 'the tables listed by GET /sql-requests/database-tables.',
            ),
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
     * @var DatabaseTableField[]
     */
    public array $fields;
}
