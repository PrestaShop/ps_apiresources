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
use PrestaShop\PrestaShop\Core\Domain\SqlManagement\Command\BulkDeleteSqlRequestCommand;
use PrestaShop\PrestaShop\Core\Domain\SqlManagement\Exception\SqlRequestException;
use PrestaShop\PrestaShop\Core\Domain\SqlManagement\Exception\SqlRequestNotFoundException;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSDelete;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    operations: [
        new CQRSDelete(
            uriTemplate: '/sql-requests/bulk-delete',
            CQRSCommand: BulkDeleteSqlRequestCommand::class,
            scopes: ['sql_management_write'],
            allowEmptyBody: false,
            openapi: new OpenApiOperation(
                summary: 'Delete several SQL requests.',
                description: 'Deletes the listed SQL requests one after the other; a failure stops the deletion of '
                    . 'the following ones and answers 422.',
            ),
        ),
    ],
    exceptionToStatus: [
        // The first matching class wins: SqlRequestNotFoundException extends SqlRequestException
        SqlRequestNotFoundException::class => Response::HTTP_NOT_FOUND,
        // Thrown by the SqlRequestId value object and when a deletion fails
        SqlRequestException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
    ],
)]
class BulkSqlRequests
{
    /**
     * @var int[]
     */
    #[ApiProperty(openapiContext: ['example' => [1, 3]])]
    #[Assert\NotBlank]
    #[Assert\All([new Assert\Type('integer'), new Assert\Positive()])]
    public array $sqlRequestIds;
}
