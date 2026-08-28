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

namespace PrestaShop\Module\APIResources\ApiPlatform\Resources\Product;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use PrestaShop\Decimal\DecimalNumber;
use PrestaShop\PrestaShop\Core\Domain\Product\Combination\Query\GetEditableCombinationsList;
use PrestaShop\PrestaShop\Core\Domain\Product\Exception\ProductNotFoundException;
use PrestaShop\PrestaShop\Core\Search\Filters\ProductCombinationFilters;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSPaginate;
use Symfony\Component\HttpFoundation\Response;

#[ApiResource(
    operations: [
        new CQRSPaginate(
            uriTemplate: '/products/{productId}/combinations',
            CQRSQuery: GetEditableCombinationsList::class,
            scopes: [
                'product_read',
            ],
            CQRSQueryMapping: [
                '[_context][langId]' => '[languageId]',
                '[_context][shopConstraint]' => '[shopConstraint]',
            ],
            ApiResourceMapping: [
                '[combinationName]' => '[name]',
                '[attributesInformation]' => '[attributes]',
                '[impactOnPrice]' => '[impactOnPriceTaxExcluded]',
            ],
            filtersClass: ProductCombinationFilters::class,
            filtersMapping: [
                '[_context][shopId]' => '[shopId]',
            ],
            itemsField: 'combinations',
            countField: 'totalCombinationsCount',
        ),
    ],
    exceptionToStatus: [
        ProductNotFoundException::class => Response::HTTP_NOT_FOUND,
    ],
)]
class CombinationList
{
    public int $productId;
    public int $combinationId;
    public string $name;
    public bool $default;
    public string $reference;
    public DecimalNumber $impactOnPriceTaxExcluded;
    public DecimalNumber $ecoTax;
    public int $quantity;
    public string $imageUrl;
    #[ApiProperty(
        openapiContext: [
            'type' => 'array',
            'description' => 'Combination attributes',
            'items' => [
                'type' => 'object',
                'properties' => [
                    'attributeGroupId' => ['type' => 'integer'],
                    'attributeGroupName' => ['type' => 'string'],
                    'attributeId' => ['type' => 'integer'],
                    'attributeName' => ['type' => 'string'],
                ],
            ],
        ]
    )]
    public array $attributes;
}
