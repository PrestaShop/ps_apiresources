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
use PrestaShop\Module\APIResources\ApiPlatform\Provider\GetEditableCombinationsListProvider;
use PrestaShop\PrestaShop\Core\Domain\Product\Combination\Query\GetEditableCombinationsList;
use PrestaShop\PrestaShop\Core\Domain\Product\Exception\ProductNotFoundException;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSGet;
use Symfony\Component\HttpFoundation\Response;

#[ApiResource(
    operations: [
        new CQRSGet(
            uriTemplate: '/products/{productId}/combinations',
            requirements: ['productId' => '\\d+'],
            CQRSQuery: GetEditableCombinationsList::class,
            provider: GetEditableCombinationsListProvider::class,
            scopes: [
                'product_read',
            ],
            ApiResourceMapping: [
                '[_context][uriVariables][productId]' => '[productId]',
            ],
            parameters: [
                new QueryParameter(
                    key: 'limit',
                    schema: ['type' => 'integer'],
                    required: false,
                    description: 'Maximum number of combinations to return'
                ),
                new QueryParameter(
                    key: 'offset',
                    schema: ['type' => 'integer'],
                    required: false,
                    description: 'Offset of the first combination to return'
                ),
                new QueryParameter(
                    key: 'orderBy',
                    schema: ['type' => 'string'],
                    required: false,
                    description: 'Sort field (combinationId, reference, price, ...)'
                ),
                new QueryParameter(
                    key: 'orderWay',
                    schema: ['type' => 'string'],
                    required: false,
                    description: 'Sort order (ASC or DESC)'
                ),
            ],
            CQRSQueryMapping: [
                '[_context][shopConstraint]' => '[shopConstraint]',
                '[_context][langId]' => '[languageId]',
                '[productId]' => '[productId]',
            ],
        ),
    ],
    exceptionToStatus: [
        ProductNotFoundException::class => Response::HTTP_NOT_FOUND,
    ],
)]
class ProductCombinationsList
{
    #[ApiProperty(identifier: true, openapiContext: ['type' => 'integer', 'example' => 42])]
    public int $productId = 0;

    /**
     * List of combinations with their details
     */
    #[ApiProperty(openapiContext: ['type' => 'array', 'items' => ['type' => 'object']])]
    public ?array $combinations = null;

    /**
     * Total number of combinations for the product
     */
    #[ApiProperty(openapiContext: ['type' => 'integer', 'example' => 2])]
    public ?int $totalCombinationsCount = null;
}
