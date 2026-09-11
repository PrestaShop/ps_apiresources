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
use ApiPlatform\Metadata\Parameters;
use ApiPlatform\Metadata\QueryParameter;
use PrestaShop\PrestaShop\Core\Domain\Alias\Query\SearchForSearchTerm;
use PrestaShop\PrestaShop\Core\Exception\InvalidArgumentException;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSGet;
use Symfony\Component\HttpFoundation\Response;

#[ApiResource(
    operations: [
        new CQRSGet(
            uriTemplate: '/search-terms',
            CQRSQuery: SearchForSearchTerm::class,
            scopes: ['search_alias_read'],
            CQRSQueryMapping: self::QUERY_MAPPING,
            parameters: new Parameters([
                new QueryParameter(
                    key: 'phrase',
                    required: true,
                    description: 'Search phrase used to find matching search terms'
                ),
                new QueryParameter(
                    key: 'limit',
                    schema: ['type' => 'integer', 'default' => '20'],
                    required: false,
                    description: 'Maximum number of search terms to return'
                ),
            ]),
            openapiContext: [
                'parameters' => [
                    [
                        'name' => 'phrase',
                        'in' => 'query',
                        'required' => true,
                        'schema' => ['type' => 'string'],
                        'description' => 'Search phrase used to find matching search terms',
                    ],
                    [
                        'name' => 'limit',
                        'in' => 'query',
                        'required' => false,
                        'schema' => ['type' => 'integer', 'default' => 20],
                        'description' => 'Maximum number of search terms to return',
                    ],
                ],
            ],
        ),
    ],
    exceptionToStatus: [
        InvalidArgumentException::class => Response::HTTP_BAD_REQUEST,
    ],
)]
class FoundSearchTerm
{
    #[ApiProperty(
        openapiContext: [
            'type' => 'array',
            'items' => ['type' => 'string'],
        ]
    )]
    public array $searchTerms = [];

    public const QUERY_MAPPING = [
        '[phrase]' => '[searchTerm]',
        '[@index]' => '[searchTerms][@index]',
    ];
}
