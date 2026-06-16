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
use PrestaShop\PrestaShop\Core\Domain\SqlManagement\Command\AddSqlRequestCommand;
use PrestaShop\PrestaShop\Core\Domain\SqlManagement\Command\DeleteSqlRequestCommand;
use PrestaShop\PrestaShop\Core\Domain\SqlManagement\Command\EditSqlRequestCommand;
use PrestaShop\PrestaShop\Core\Domain\SqlManagement\Exception\SqlRequestConstraintException;
use PrestaShop\PrestaShop\Core\Domain\SqlManagement\Exception\SqlRequestNotFoundException;
use PrestaShop\PrestaShop\Core\Domain\SqlManagement\Query\GetSqlRequestForEditing;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSCreate;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSDelete;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSGet;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSPartialUpdate;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    operations: [
        new CQRSCreate(
            uriTemplate: '/sql-requests',
            validationContext: ['groups' => ['Default', 'Create']],
            CQRSCommand: AddSqlRequestCommand::class,
            scopes: ['sql_management_write'],
        ),
        new CQRSDelete(
            uriTemplate: '/sql-requests/{sqlRequestId}',
            requirements: ['sqlRequestId' => '\d+'],
            output: false,
            CQRSCommand: DeleteSqlRequestCommand::class,
            scopes: ['sql_management_write'],
        ),
        new CQRSGet(
            uriTemplate: '/sql-requests/{sqlRequestId}',
            requirements: ['sqlRequestId' => '\d+'],
            CQRSQuery: GetSqlRequestForEditing::class,
            scopes: ['sql_management_read'],
            CQRSQueryMapping: self::QUERY_MAPPING,
            ApiResourceMapping: self::RESOURCE_MAPPING,
        ),
        new CQRSPartialUpdate(
            uriTemplate: '/sql-requests/{sqlRequestId}',
            requirements: ['sqlRequestId' => '\d+'],
            read: false,
            CQRSCommand: EditSqlRequestCommand::class,
            CQRSQuery: GetSqlRequestForEditing::class,
            scopes: ['sql_management_write'],
            CQRSQueryMapping: self::QUERY_MAPPING,
            ApiResourceMapping: self::RESOURCE_MAPPING,
        ),
    ],
    normalizationContext: ['skip_null_values' => false],
    exceptionToStatus: [
        SqlRequestNotFoundException::class => Response::HTTP_NOT_FOUND,
        SqlRequestConstraintException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
    ],
)]
class SqlRequest
{
    #[ApiProperty(identifier: true)]
    public int $sqlRequestId;

    #[Assert\NotBlank(groups: ['Create'])]
    public string $name;

    #[Assert\NotBlank(groups: ['Create'])]
    public string $sql;

    /**
     * GetSqlRequestForEditing expects a $requestSqlId constructor argument (legacy naming),
     * so the sqlRequestId URI variable must be renamed when the query is built.
     */
    public const QUERY_MAPPING = [
        '[sqlRequestId]' => '[requestSqlId]',
    ];

    /**
     * The query mapping above also renames the query result key, so map it back to the
     * sqlRequestId resource identifier when denormalizing the response.
     */
    public const RESOURCE_MAPPING = [
        '[requestSqlId]' => '[sqlRequestId]',
    ];
}
