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
use PrestaShop\PrestaShop\Core\Domain\Product\Query\SearchProducts;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSGetCollection;

#[ApiResource(
    operations: [
        new CQRSGetCollection(
            uriTemplate: '/products/search/{phrase}/{resultsLimit}/{isoCode}',
            openapiContext: [
                'parameters' => [
                    [
                        'name' => 'phrase',
                        'in' => 'path',
                        'required' => true,
                        'schema' => [
                            'type' => 'string',
                        ],
                    ],
                    [
                        'name' => 'resultsLimit',
                        'in' => 'path',
                        'required' => true,
                        'schema' => [
                            'type' => 'int',
                        ],
                    ],
                    [
                        'name' => 'isoCode',
                        'in' => 'path',
                        'required' => true,
                        'schema' => [
                            'type' => 'string',
                        ],
                    ],
                    [
                        'name' => 'orderId',
                        'in' => 'query',
                        'required' => false,
                        'schema' => [
                            'type' => 'int',
                        ],
                    ],
                ],
            ],
            CQRSQuery: SearchProducts::class
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
