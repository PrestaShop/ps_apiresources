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
class ProductCombination
{
    #[ApiProperty(identifier: true)]
    public int $combinationId;

    public int $productId;

    public bool $isDefault; // I cannot see this atribute, It works?

    public string $name;

    public int $quantity;

    public array $imageIds;

    public string $coverThumbnailUrl;

    public string $gtin;
    public string $isbn;
    public string $mpn;
    public string $reference;
    public string $upc;
    public DecimalNumber $impactOnWeight;

    public DecimalNumber $impactOnPrice;
    public DecimalNumber $impactOnPriceTaxIncluded;
    public DecimalNumber $impactOnUnitPrice;
    public DecimalNumber $impactOnUnitPriceTaxIncluded;
    public DecimalNumber $ecotax;
    public DecimalNumber $ecotaxTaxIncluded;
    public DecimalNumber $wholesalePrice;
    public DecimalNumber $productTaxRate;
    public DecimalNumber $productPrice;
    public DecimalNumber $productEcotax;

    public const QUERY_MAPPING = [
        '[_context][shopConstraint]' => '[shopConstraint]',
        '[details][gtin]' => '[gtin]',
        '[details][isbn]' => '[isbn]',
        '[details][mpn]' => '[mpn]',
        '[details][reference]' => '[reference]',
        '[details][upc]' => '[upc]',
        '[details][impactOnWeight]' => '[impactOnWeight]',
        '[prices][impactOnPrice]' => '[impactOnPrice]',
        '[prices][impactOnPriceTaxIncluded]' => '[impactOnPriceTaxIncluded]',
        '[prices][impactOnUnitPrice]' => '[impactOnUnitPrice]',
        '[prices][impactOnUnitPriceTaxIncluded]' => '[impactOnUnitPriceTaxIncluded]',
        '[prices][ecotax]' => '[ecotax]',
        '[prices][ecotaxTaxIncluded]' => '[ecotaxTaxIncluded]',
        '[prices][wholesalePrice]' => '[wholesalePrice]',
        '[prices][productTaxRate]' => '[productTaxRate]',
        '[prices][productPrice]' => '[productPrice]',
        '[prices][productEcotax]' => '[productEcotax]',
        '[stock][quantity]' => '[quantity]',
    ];
}
