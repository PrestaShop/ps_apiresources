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
use PrestaShop\PrestaShop\Core\Domain\Hook\Command\UpdateHookStatusCommand;
use PrestaShop\PrestaShop\Core\Domain\Hook\Exception\HookNotFoundException;
use PrestaShop\PrestaShop\Core\Domain\Hook\Query\GetHook;
use PrestaShop\PrestaShop\Core\Domain\Hook\Query\GetHookStatus;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSGet;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSUpdate;
use PrestaShopBundle\ApiPlatform\Metadata\PaginatedList;
use PrestaShopBundle\ApiPlatform\Provider\QueryListProvider;

#[ApiResource(
    operations: [
        new CQRSGet(
            uriTemplate: '/hook-status/{id}',
            requirements: ['id' => '\d+'],
            openapiContext: [
                'summary' => 'Get hook status A',
                'description' => 'Get hook status B',
                'parameters' => [
                    [
                        'name' => 'id',
                        'in' => 'path',
                        'required' => true,
                        'schema' => [
                            'type' => 'string',
                        ],
                        'description' => 'Id of the hook you are requesting the status from',
                    ],
                    [
                        'name' => 'Authorization',
                        'in' => 'scopes',
                        'description' => 'hook_read <br> hook_write ',
                    ],
                ],
            ],
            exceptionToStatus: [HookNotFoundException::class => 404],
            CQRSQuery: GetHookStatus::class,
            scopes: ['hook_read']
        ),
        new CQRSUpdate(
            uriTemplate: '/hook-status',
            CQRSCommand: UpdateHookStatusCommand::class,
            scopes: ['hook_write']
        ),
        new CQRSGet(
            uriTemplate: '/hooks/{id}',
            requirements: ['id' => '\d+'],
            exceptionToStatus: [HookNotFoundException::class => 404],
            CQRSQuery: GetHook::class,
            scopes: ['hook_read']
        ),
        new PaginatedList(
            uriTemplate: '/hooks',
            provider: QueryListProvider::class,
            scopes: ['hook_read'],
            ApiResourceMapping: ['[id_hook]' => '[id]'],
            gridDataFactory: 'prestashop.core.grid.data_factory.hook',
        ),
    ],
)]
class Hook
{
    #[ApiProperty(identifier: true)]
    public int $id;

    public bool $active;

    public string $name;

    public string $title;

    public string $description;
}
