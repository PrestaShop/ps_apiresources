<?php
/**
 * Copyright since 2007 PrestaShop SA and Contributors
 * PrestaShop is an International Registered Trademark & Property of PrestaShop SA
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/OSL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to https://devdocs.prestashop.com/ for more information.
 *
 * @author    PrestaShop SA and Contributors <contact@prestashop.com>
 * @copyright Since 2007 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License (OSL 3.0)
 */

declare(strict_types=1);

namespace PrestaShop\Module\APIResources\ApiPlatform\Resources;

use ApiPlatform\Core\Annotation\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Post;
use ApiPlatform\Metadata\Put;
use PrestaShop\PrestaShop\Core\Domain\ApiAccess\Command\AddApiAccessCommand;
use PrestaShop\PrestaShop\Core\Domain\ApiAccess\Command\DeleteApiAccessCommand;
use PrestaShop\PrestaShop\Core\Domain\ApiAccess\Command\EditApiAccessCommand;
use PrestaShop\PrestaShop\Core\Domain\ApiAccess\Exception\ApiAccessNotFoundException;
use PrestaShop\PrestaShop\Core\Domain\ApiAccess\Query\GetApiAccessForEditing;
use PrestaShopBundle\ApiPlatform\Processor\CommandProcessor;
use PrestaShopBundle\ApiPlatform\Provider\QueryProvider;

#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/api-access/{apiAccessId}',
            requirements: ['apiAccessId' => '\d+'],
            openapiContext: [
                'summary' => 'Get API Access details',
                'description' => 'Get API Access public details only, sensitive information like secrets is not returned',
                'parameters' => [
                    [
                        'name' => 'apiAccessId',
                        'in' => 'path',
                        'required' => true,
                        'schema' => [
                            'type' => 'string',
                        ],
                        'description' => 'Id of the API Access you are requesting the details from',
                    ],
                    [
                        'name' => 'Authorization',
                        'in' => 'scopes',
                        'description' => 'api_access_read',
                    ],
                ],
            ],
            provider: QueryProvider::class,
            extraProperties: [
                'query' => GetApiAccessForEditing::class,
                'CQRSQuery' => GetApiAccessForEditing::class,
                'scopes' => ['api_access_read'],
            ]
        ),
        new Delete(
            uriTemplate: '/api-access/{apiAccessId}',
            requirements: ['apiAccessId' => '\d+'],
            openapiContext: [
                'summary' => 'Delete API Access details',
                'description' => 'Delete API Access public details only, sensitive information like secrets is not returned',
                'parameters' => [
                    [
                        'name' => 'apiAccessId',
                        'in' => 'path',
                        'required' => true,
                        'schema' => [
                            'type' => 'string',
                        ],
                        'description' => 'Id of the API Access you are deleting',
                    ],
                    [
                        'name' => 'Authorization',
                        'in' => 'scopes',
                        'description' => 'api_access_write',
                    ],
                ],
            ],
            output: false,
            provider: QueryProvider::class,
            extraProperties: [
                'query' => DeleteApiAccessCommand::class,
                'CQRSQuery' => DeleteApiAccessCommand::class,
                'scopes' => ['api_access_write'],
            ]
        ),
        new Post(
            uriTemplate: '/api-access',
            processor: CommandProcessor::class,
            extraProperties: [
                'command' => AddApiAccessCommand::class,
                'CQRSCommand' => AddApiAccessCommand::class,
                'scopes' => ['api_access_write'],
            ]
        ),
        new Put(
            uriTemplate: '/api-access/{apiAccessId}',
            read: false,
            processor: CommandProcessor::class,
            extraProperties: [
                'command' => EditApiAccessCommand::class,
                'query' => GetApiAccessForEditing::class,
                'CQRSCommand' => EditApiAccessCommand::class,
                'CQRSQuery' => GetApiAccessForEditing::class,
                'scopes' => ['api_access_write'],
            ]
        ),
    ],
    exceptionToStatus: [ApiAccessNotFoundException::class => 404],
)]
class ApiAccess
{
    #[ApiProperty(identifier: true)]
    public int $apiAccessId;

    public string $secret;

    public string $apiClientId;

    public string $clientName;

    public string $description;

    public bool $enabled;

    public int $lifetime;

    public array $scopes;
}
