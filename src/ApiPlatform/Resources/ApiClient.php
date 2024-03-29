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

namespace PrestaShop\Module\APIResources\ApiPlatform\Resources;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use PrestaShop\PrestaShop\Core\Domain\ApiClient\Command\AddApiClientCommand;
use PrestaShop\PrestaShop\Core\Domain\ApiClient\Command\DeleteApiClientCommand;
use PrestaShop\PrestaShop\Core\Domain\ApiClient\Command\EditApiClientCommand;
use PrestaShop\PrestaShop\Core\Domain\ApiClient\Exception\ApiClientNotFoundException;
use PrestaShop\PrestaShop\Core\Domain\ApiClient\Query\GetApiClientForEditing;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSCreate;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSDelete;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSGet;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSPartialUpdate;

#[ApiResource(
    operations: [
        new CQRSGet(
            uriTemplate: '/api-client/{apiClientId}',
            requirements: ['apiClientId' => '\d+'],
            openapiContext: [
                'summary' => 'Get API Client details',
                'description' => 'Get API Client public details only, sensitive information like secrets is not returned',
                'parameters' => [
                    [
                        'name' => 'apiClientId',
                        'in' => 'path',
                        'required' => true,
                        'schema' => [
                            'type' => 'string',
                        ],
                        'description' => 'Id of the API Client you are requesting the details from',
                    ],
                    [
                        'name' => 'Authorization',
                        'in' => 'scopes',
                        'description' => 'api_client_read',
                    ],
                ],
            ],
            CQRSQuery: GetApiClientForEditing::class,
            scopes: ['api_client_read']
        ),
        new CQRSDelete(
            uriTemplate: '/api-client/{apiClientId}',
            requirements: ['apiClientId' => '\d+'],
            openapiContext: [
                'summary' => 'Delete API Client details',
                'description' => 'Delete API Client public details only, sensitive information like secrets is not returned',
                'parameters' => [
                    [
                        'name' => 'apiClientId',
                        'in' => 'path',
                        'required' => true,
                        'schema' => [
                            'type' => 'string',
                        ],
                        'description' => 'Id of the API Client you are deleting',
                    ],
                    [
                        'name' => 'Authorization',
                        'in' => 'scopes',
                        'description' => 'api_client_write',
                    ],
                ],
            ],
            output: false,
            CQRSQuery: DeleteApiClientCommand::class,
            scopes: ['api_client_write']
        ),
        new CQRSCreate(
            uriTemplate: '/api-client',
            CQRSCommand: AddApiClientCommand::class,
            scopes: ['api_client_write']
        ),
        new CQRSPartialUpdate(
            uriTemplate: '/api-client/{apiClientId}',
            read: false,
            CQRSCommand: EditApiClientCommand::class,
            CQRSQuery: GetApiClientForEditing::class,
            scopes: ['api_client_write']
        ),
    ],
    exceptionToStatus: [ApiClientNotFoundException::class => 404],
)]
class ApiClient
{
    #[ApiProperty(identifier: true)]
    public int $apiClientId;

    public string $secret;

    public string $clientId;

    public string $clientName;

    public string $description;

    public bool $enabled;

    public int $lifetime;

    public array $scopes;
}
