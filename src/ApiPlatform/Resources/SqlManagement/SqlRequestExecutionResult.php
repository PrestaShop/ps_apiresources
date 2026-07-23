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

namespace PrestaShop\Module\APIResources\ApiPlatform\Resources\SqlManagement;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use PrestaShop\PrestaShop\Core\Domain\SqlManagement\Exception\SqlRequestException;
use PrestaShop\PrestaShop\Core\Domain\SqlManagement\Exception\SqlRequestNotFoundException;
use PrestaShop\PrestaShop\Core\Domain\SqlManagement\Query\GetSqlRequestExecutionResult as GetSqlRequestExecutionResultQuery;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSGet;
use Symfony\Component\HttpFoundation\Response;

#[ApiResource(
    operations: [
        new CQRSGet(
            uriTemplate: '/sql-requests/{sqlRequestId}/execution-result',
            requirements: ['sqlRequestId' => '\d+'],
            CQRSQuery: GetSqlRequestExecutionResultQuery::class,
            scopes: ['sql_request_read'],
            openapiContext: [
                'summary' => 'Execute a saved SQL request and return its rows',
                'description' => 'Runs the SQL query stored under the given SqlRequest identifier and returns the resulting columns and rows. Sensitive attributes (e.g. password fields) are automatically obfuscated.',
            ],
            CQRSQueryMapping: [
                '[sqlRequestId]' => '[requestSqlId]',
            ],
        ),
    ],
    exceptionToStatus: [
        SqlRequestNotFoundException::class => Response::HTTP_NOT_FOUND,
        SqlRequestException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
    ],
)]
class SqlRequestExecutionResult
{
    #[ApiProperty(identifier: true)]
    public int $sqlRequestId;

    /**
     * @var string[]
     */
    #[ApiProperty(openapiContext: [
        'type' => 'array',
        'items' => ['type' => 'string'],
        'example' => ['id_lang', 'name'],
    ])]
    public array $columns;

    /**
     * @var array<int, array<string, mixed>>
     */
    #[ApiProperty(openapiContext: [
        'type' => 'array',
        'items' => ['type' => 'object', 'additionalProperties' => true],
        'example' => [['id_lang' => 1, 'name' => 'English']],
    ])]
    public array $rows;
}
