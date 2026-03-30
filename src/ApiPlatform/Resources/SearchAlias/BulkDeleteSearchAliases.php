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
use PrestaShop\PrestaShop\Core\Domain\Alias\Command\BulkDeleteSearchTermsAliasesCommand;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSDelete;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    operations: [
        new CQRSDelete(
            // Usually the identifier searchTerm would be in the URL, but since here it is a string this would
            // conflict with the bulk url /search-aliases/bulk-delete (bulk-delete would be considered the identifier,
            // vice versa)
            // We will have only one endpoint for deletion, if there are multiple search terms to delete they will be
            // passed in the JSON body, if there is only one search term to delete it will still need to be passed in
            // the JSON body as an array with one element
            // As a consequence, there is no single delete endpoint with the DeleteSearchTermAliasesCommand.
            uriTemplate: '/search-aliases/bulk-delete',
            CQRSCommand: BulkDeleteSearchTermsAliasesCommand::class,
            scopes: ['search_alias_write'],
            allowEmptyBody: false,
            openapiContext: [
                'requestBody' => [
                    'required' => true,
                    'content' => [
                        'application/json' => [
                            'schema' => [
                                'type' => 'object',
                                'required' => ['searchTerms'],
                                'properties' => [
                                    'searchTerms' => [
                                        'type' => 'array',
                                        'items' => ['type' => 'string'],
                                    ],
                                ],
                            ],
                            'example' => [
                                'searchTerms' => ['blouse', 't-shirt'],
                            ],
                        ],
                    ],
                ],
            ],
        ),
    ]
)]
class BulkDeleteSearchAliases
{
    #[ApiProperty(
        openapiContext: [
            'type' => 'array',
            'items' => ['type' => 'string'],
        ]
    )]
    #[Assert\NotBlank]
    #[Assert\Count(min: 1)]
    public array $searchTerms = [];
}
