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
use ApiPlatform\Metadata\Link;
use ApiPlatform\Metadata\QueryParameter;
use PrestaShop\Decimal\DecimalNumber;
use PrestaShop\PrestaShop\Core\Domain\Product\Combination\Query\GetEditableCombinationsList;
use PrestaShop\PrestaShop\Core\Domain\Product\Exception\ProductNotFoundException;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSPaginate;
use Symfony\Component\HttpFoundation\Response;

#[ApiResource(
    operations: [
        new CQRSPaginate(
            uriTemplate: '/products/{productId}/combinations',
            requirements: ['productId' => '\\d+'],
            uriVariables: [
                'productId' => new Link(identifiers: ['productId']),
            ],
            CQRSQuery: GetEditableCombinationsList::class,
            scopes: [
                'product_read',
            ],
            itemsField: 'combinations',
            countField: 'totalCombinationsCount',
            parameters: [
                'limit' => new QueryParameter(
                    key: 'limit',
                    schema: ['type' => 'integer'],
                    required: false,
                    description: 'Maximum number of combinations to return'
                ),
                'offset' => new QueryParameter(
                    key: 'offset',
                    schema: ['type' => 'integer'],
                    required: false,
                    description: 'Offset of the first combination to return'
                ),
                'orderBy' => new QueryParameter(
                    key: 'orderBy',
                    schema: ['type' => 'string'],
                    required: false,
                    description: 'Sort field (combinationId, reference, price, ...)'
                ),
                'orderWay' => new QueryParameter(
                    key: 'orderWay',
                    schema: ['type' => 'string'],
                    required: false,
                    description: 'Sort order (ASC or DESC)'
                ),
            ],
            CQRSQueryMapping: [
                '[_context][shopConstraint]' => '[shopConstraint]',
                '[_context][langId]' => '[languageId]',
                '[_context][uriVariables][productId]' => '[productId]',
                '[limit]' => '[limit]',
                '[offset]' => '[offset]',
                '[orderBy]' => '[orderBy]',
                '[orderWay]' => '[orderWay]',
            ],
            ApiResourceMapping: [
                // EditableCombinationForListing::isDefault() serialises to key 'isDefault'
                '[isDefault]' => '[default]',
            ],
        ),
    ],
    exceptionToStatus: [
        ProductNotFoundException::class => Response::HTTP_NOT_FOUND,
    ],
)]
class ProductCombinationsList
{
    #[ApiProperty(readable: false, writable: false)]
    public int $productId;

    #[ApiProperty(identifier: true)]
    public int $combinationId;

    public string $combinationName;

    public string $reference;

    public bool $default;

    public DecimalNumber $impactOnPrice;

    public int $quantity;

    public string $imageUrl;

    public DecimalNumber $ecoTax;

    #[ApiProperty(openapiContext: [
        'type' => 'array',
        'items' => [
            'type' => 'object',
            'properties' => [
                'attributeGroupId' => ['type' => 'integer'],
                'attributeGroupName' => ['type' => 'string'],
                'attributeId' => ['type' => 'integer'],
                'attributeName' => ['type' => 'string'],
            ],
        ],
    ])]
    public array $attributesInformation;
}
