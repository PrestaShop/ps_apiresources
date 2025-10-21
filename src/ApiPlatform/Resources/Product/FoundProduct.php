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
use PrestaShop\PrestaShop\Core\Domain\Product\Query\SearchProducts;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSGetCollection;

#[ApiResource(
    operations: [
        new CQRSGetCollection(
            uriTemplate: '/products/search',
            scopes: [
                'product_read',
            ],
            CQRSQuery: SearchProducts::class,
            parameters: [
                new QueryParameter(
                    key: 'phrase',
                    required: true,
                    description: 'Search phrase to find products'
                ),
                new QueryParameter(
                    key: 'resultsLimit',
                    schema: ['type' => 'integer', 'default' => 20],
                    required: true,
                    description: 'Maximum number of results to return'
                ),
                new QueryParameter(
                    key: 'isoCode',
                    required: true,
                    description: 'Currency ISO code (e.g., EUR, USD)'
                ),
                new QueryParameter(
                    key: 'orderId',
                    schema: ['type' => 'integer'],
                    required: false,
                    description: 'Optional order ID for context-specific pricing'
                ),
            ],
            openapiContext: [
                'parameters' => [
                    [
                        'name' => 'phrase',
                        'in' => 'query',
                        'required' => true,
                        'schema' => [
                            'type' => 'string',
                        ],
                        'description' => 'Search phrase to find products',
                    ],
                    [
                        'name' => 'resultsLimit',
                        'in' => 'query',
                        'required' => true,
                        'schema' => [
                            'type' => 'integer',
                            'default' => 20,
                        ],
                        'description' => 'Maximum number of results to return',
                    ],
                    [
                        'name' => 'isoCode',
                        'in' => 'query',
                        'required' => true,
                        'schema' => [
                            'type' => 'string',
                        ],
                        'description' => 'Currency ISO code (e.g., EUR, USD)',
                    ],
                    [
                        'name' => 'orderId',
                        'in' => 'query',
                        'required' => false,
                        'schema' => [
                            'type' => 'integer',
                        ],
                        'description' => 'Optional order ID for context-specific pricing',
                    ],
                ],
            ]
        ),
    ],
)]
class FoundProduct
{
    #[ApiProperty(identifier: true)]
    public int $productId;

    public bool $availableOutOfStock;

    public string $name;

    public float $taxRate;

    public string $formattedPrice;

    public float $priceTaxIncl;

    public float $priceTaxExcl;

    public int $stock;

    public string $location;

    public array $combinations;

    public array $customizationFields;
}
