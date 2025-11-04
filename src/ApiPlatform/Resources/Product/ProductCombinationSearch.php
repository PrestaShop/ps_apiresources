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

namespace PrestaShop\Module\APIResources\ApiPlatform\Resources\Product;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\QueryParameter;
use PrestaShop\PrestaShop\Core\Domain\Product\Combination\Query\SearchProductCombinations;
use PrestaShop\PrestaShop\Core\Domain\Product\Exception\ProductNotFoundException;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSGet;
use Symfony\Component\HttpFoundation\Response;

#[ApiResource(
    operations: [
        new CQRSGet(
            uriTemplate: '/products/{productId}/combinations/search',
            requirements: ['productId' => '\\d+'],
            CQRSQuery: SearchProductCombinations::class,
            scopes: [
                'product_read',
            ],
            parameters: [
                'phrase' => new QueryParameter(
                    key: 'phrase',
                    required: true,
                    description: 'Search phrase to filter combinations (by attributes, name, etc.)'
                ),
                'limit' => new QueryParameter(
                    key: 'limit',
                    schema: ['type' => 'integer', 'default' => '20'],
                    required: false,
                    description: 'Maximum number of results to return'
                ),
            ],
            CQRSQueryMapping: [
                '[_context][shopConstraint]' => '[shopConstraint]',
                '[_context][langId]' => '[languageId]',
                '[_context][uriVariables][productId]' => '[productId]',
                '[phrase]' => '[searchPhrase]',
                '[limit]' => '[limit]',
            ],
            ApiResourceMapping: [
                // Map QueryResult (ProductCombinationsCollection) to API result shape
                '[productCombinations]' => '[combinations]',
            ],
        ),
    ],
    exceptionToStatus: [
        ProductNotFoundException::class => Response::HTTP_NOT_FOUND,
    ],
)]
class ProductCombinationSearch
{
    #[ApiProperty(identifier: true, openapiContext: ['example' => 42])]
    public int $productId = 0;

    /**
     * @var array<int, array{combinationId:int, combinationName:string}>
     */
    #[ApiProperty(openapiContext: [
        'type' => 'array',
        'items' => [
            'type' => 'object',
            'properties' => [
                'combinationId' => ['type' => 'integer', 'example' => 123],
                'combinationName' => ['type' => 'string', 'example' => 'Size: M - Color: Red'],
            ],
        ],
    ])]
    public array $combinations = [];
}
