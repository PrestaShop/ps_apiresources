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

namespace PrestaShop\Module\APIResources\ApiPlatform\Resources\SearchAlias;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use PrestaShop\PrestaShop\Core\Domain\Alias\Command\AddSearchTermAliasesCommand;
use PrestaShop\PrestaShop\Core\Domain\Alias\Command\DeleteSearchTermAliasesCommand;
use PrestaShop\PrestaShop\Core\Domain\Alias\Command\UpdateSearchTermAliasesCommand;
use PrestaShop\PrestaShop\Core\Domain\Alias\Exception\AliasConstraintException;
use PrestaShop\PrestaShop\Core\Domain\Alias\Exception\AliasNotFoundException;
use PrestaShop\PrestaShop\Core\Domain\Alias\Query\GetAliasesBySearchTermForEditing;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSCreate;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSDelete;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSGet;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSUpdate;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    operations: [
        new CQRSGet(
            uriTemplate: '/search-alias/{search}',
            CQRSQuery: GetAliasesBySearchTermForEditing::class,
            scopes: ['search_alias_read'],
            exceptionToStatus: [AliasNotFoundException::class => 404],
            CQRSQueryMapping: self::QUERY_MAPPING,
        ),
        new CQRSCreate(
            uriTemplate: '/search-alias',
            validationContext: ['groups' => ['Default', 'Create']],
            CQRSCommand: AddSearchTermAliasesCommand::class,
            scopes: ['search_alias_write'],
            CQRSCommandMapping: self::COMMAND_MAPPING,
        ),
        new CQRSUpdate(
            uriTemplate: '/search-alias/{search}',
            CQRSCommand: UpdateSearchTermAliasesCommand::class,
            CQRSQuery: GetAliasesBySearchTermForEditing::class,
            scopes: ['search_alias_write'],
            CQRSCommandMapping: self::UPDATE_COMMAND_MAPPING,
            CQRSQueryMapping: self::QUERY_MAPPING,
        ),
        new CQRSDelete(
            uriTemplate: '/search-alias/{search}',
            CQRSCommand: DeleteSearchTermAliasesCommand::class,
            scopes: ['search_alias_write'],
            CQRSCommandMapping: self::DELETE_COMMAND_MAPPING,
            output: false,
        ),
    ],
    exceptionToStatus: [
        AliasNotFoundException::class => Response::HTTP_NOT_FOUND,
        AliasConstraintException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
    ],
)]
class SearchAlias
{
    #[ApiProperty(identifier: true)]
    #[Assert\NotBlank(groups: ['Create'])]
    #[Assert\Length(min: 1, max: 255)]
    public string $search = '';

    #[Assert\NotBlank(groups: ['Create'])]
    #[Assert\Count(min: 1, groups: ['Create'])]
    #[Assert\All(
        constraints: [
            new Assert\Collection(
                fields: [
                    'alias' => new Assert\NotBlank(groups: ['Default', 'Create']),
                    'active' => new Assert\Type(type: 'bool', groups: ['Default', 'Create']),
                ],
                allowMissingFields: false,
                groups: ['Default', 'Create']
            ),
        ],
        groups: ['Default', 'Create']
    )]
    #[ApiProperty(
        openapiContext: [
            'type' => 'array',
            'items' => [
                'type' => 'object',
                'properties' => [
                    'id_alias' => ['type' => 'integer'],
                    'alias' => ['type' => 'string'],
                    'active' => ['type' => 'boolean'],
                ],
            ],
        ]
    )]
    public array $aliases = [];

    protected const QUERY_MAPPING = [
        '[search]' => '[searchTerm]',
        '[searchTerm]' => '[search]',
    ];

    protected const COMMAND_MAPPING = [
        '[search]' => '[searchTerm]',
        '[aliases]' => '[aliases]',
    ];

    protected const UPDATE_COMMAND_MAPPING = [
        '[search]' => '[oldSearchTerm]',
        '[aliases]' => '[aliases]',
    ];

    protected const DELETE_COMMAND_MAPPING = [
        '[search]' => '[searchTerm]',
    ];
}
