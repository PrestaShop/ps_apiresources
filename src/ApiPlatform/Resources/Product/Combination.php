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
use PrestaShop\Decimal\DecimalNumber;
use PrestaShop\PrestaShop\Core\Domain\Product\Combination\Exception\CombinationNotFoundException;
use PrestaShop\PrestaShop\Core\Domain\Product\Combination\Query\GetCombinationForEditing;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSGet;
use Symfony\Component\HttpFoundation\Response;

#[ApiResource(
    operations: [
        new CQRSGet(
            uriTemplate: '/products/combinations/{combinationId}',
            CQRSQuery: GetCombinationForEditing::class,
            scopes: [
                'product_read',
            ],
            CQRSQueryMapping: self::QUERY_MAPPING,
        ),
    ],
    exceptionToStatus: [
        CombinationNotFoundException::class => Response::HTTP_NOT_FOUND,
    ],
)]
class Combination
{
    public int $productId;

    #[ApiProperty(identifier: true)]
    public int $combinationId;
    public string $name;
    public bool $default;

    public string $gtin;
    public string $isbn;
    public string $mpn;
    public string $reference;
    public string $upc;

    public string $coverThumbnailUrl;
    #[ApiProperty(openapiContext: ['type' => 'array', 'description' => 'List of image IDs', 'items' => ['type' => 'integer'], 'example' => [1, 3]])]
    public array $imageIds;

    public DecimalNumber $impactOnPriceTaxExcluded;
    public DecimalNumber $impactOnPriceTaxIncluded;
    public DecimalNumber $impactOnUnitPrice;
    public DecimalNumber $impactOnUnitPriceTaxIncluded;
    public DecimalNumber $ecotaxTaxExcluded;
    public DecimalNumber $ecotaxTaxIncluded;
    public DecimalNumber $impactOnWeight;
    public DecimalNumber $wholesalePrice;
    public DecimalNumber $productTaxRate;
    public DecimalNumber $productPriceTaxExcluded;
    public DecimalNumber $productEcotaxTaxExcluded;

    public int $quantity;

    public const QUERY_MAPPING = [
        '[_context][shopConstraint]' => '[shopConstraint]',
        '[details][gtin]' => '[gtin]',
        '[details][isbn]' => '[isbn]',
        '[details][mpn]' => '[mpn]',
        '[details][reference]' => '[reference]',
        '[details][upc]' => '[upc]',
        '[details][impactOnWeight]' => '[impactOnWeight]',
        '[prices][impactOnPrice]' => '[impactOnPriceTaxExcluded]',
        '[prices][impactOnPriceTaxIncluded]' => '[impactOnPriceTaxIncluded]',
        '[prices][impactOnUnitPrice]' => '[impactOnUnitPriceTaxExcluded]',
        '[prices][impactOnUnitPriceTaxIncluded]' => '[impactOnUnitPriceTaxIncluded]',
        '[prices][ecotax]' => '[ecotaxTaxExcluded]',
        '[prices][ecotaxTaxIncluded]' => '[ecotaxTaxIncluded]',
        '[prices][wholesalePrice]' => '[wholesalePrice]',
        '[prices][productTaxRate]' => '[productTaxRate]',
        '[prices][productPrice]' => '[productPriceTaxExcluded]',
        '[prices][productEcotax]' => '[productEcotaxTaxExcluded]',
        '[stock][quantity]' => '[quantity]',
    ];
}
