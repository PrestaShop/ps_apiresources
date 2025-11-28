<?php
declare(strict_types=1);

namespace PrestaShop\Module\APIResources\ApiPlatform\Resources\RequestSql;

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


#[ApiResource(
    operations: [
        new CQRSGet(
            uriTemplate: '/request-sql/{requestSqlId}',
            requirements: ['requestSqlId' => '\d+'],
            CQRSQuery: GetSqlRequestForEditing::class,
            scopes: ['request_sql_read']
        ),
        new CQRSCreate(
            uriTemplate: '/request-sql',
            CQRSCommand: AddSqlRequestCommand::class,
            CQRSQuery: GetSqlRequestForEditing::class,
            scopes: ['request_sql_write']
        ),
        new CQRSPartialUpdate(
            uriTemplate: '/request-sql/{requestSqlId}',
            CQRSCommand: EditSqlRequestCommand::class,
            CQRSQuery: GetSqlRequestForEditing::class,
            scopes: [
                'request_sql_write',
            ]
        ),
        new CQRSDelete(
            uriTemplate: '/request-sql/{requestSqlId}',
            CQRSCommand: DeleteSqlRequestCommand::class,
            scopes: [
                'request_sql_write',
            ],
        )
    ],
    exceptionToStatus: [
        SqlRequestConstraintException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        SqlRequestNotFoundException::class => Response::HTTP_NOT_FOUND,
    ],
)]

// Todo:
// Cannot create an instance of \"PrestaShop\\PrestaShop\\Core\\Domain\\SqlManagement\\Query\\GetSqlRequestForEditing\" from serialized data because its constructor requires the following parameters to be present : \"$requestSqlId\".

class RequestSql
{
    #[ApiProperty(identifier: true)]
    public int $requestSqlId;

    public string $name;

    public string $sql;
}
