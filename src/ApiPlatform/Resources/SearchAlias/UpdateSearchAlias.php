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
use PrestaShop\PrestaShop\Core\Domain\Alias\Command\UpdateSearchTermAliasesCommand;
use PrestaShop\PrestaShop\Core\Domain\Alias\Exception\AliasConstraintException;
use PrestaShop\PrestaShop\Core\Domain\Alias\Exception\AliasNotFoundException;
use PrestaShop\PrestaShop\Core\Domain\Alias\Query\GetAliasesBySearchTermForEditing;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSUpdate;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    operations: [
        new CQRSUpdate(
            uriTemplate: '/search-aliases/{searchTerm}',
            CQRSCommand: UpdateSearchTermAliasesCommand::class,
            CQRSQuery: GetAliasesBySearchTermForEditing::class,
            scopes: ['search_alias_write'],
            CQRSCommandMapping: self::UPDATE_COMMAND_MAPPING,
            output: false,
            experimentalOperation: true,
        ),
    ],
    exceptionToStatus: [
        AliasNotFoundException::class => Response::HTTP_NOT_FOUND,
        AliasConstraintException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
    ],
)]
class UpdateSearchAlias
{
    #[ApiProperty(identifier: true)]
    #[Assert\NotBlank]
    #[Assert\Length(min: 1, max: 255)]
    public string $searchTerm;

    #[Assert\All(
        constraints: [
            new Assert\Collection(
                fields: [
                    'alias' => new Assert\NotBlank(),
                    'enabled' => new Assert\Type(type: 'bool'),
                    // Add active because after normalization both active and enabled are present
                    'active' => new Assert\Type(type: 'bool'),
                ],
            ),
        ],
    )]
    #[ApiProperty(
        openapiContext: [
            'type' => 'array',
            'items' => [
                'type' => 'object',
                'properties' => [
                    'alias' => ['type' => 'string'],
                    'enabled' => ['type' => 'boolean'],
                ],
            ],
        ]
    )]
    public array $aliases = [];

    #[Assert\Length(min: 1, max: 255)]
    public ?string $newSearchTerm = null;

    protected const UPDATE_COMMAND_MAPPING = [
        '[searchTerm]' => '[oldSearchTerm]',
        '[aliases][@index][alias]' => '[aliases][@index][alias]',
        '[aliases][@index][enabled]' => '[aliases][@index][active]',
    ];
}
