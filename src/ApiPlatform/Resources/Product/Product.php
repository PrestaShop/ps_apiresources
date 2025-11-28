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
use PrestaShop\PrestaShop\Core\Domain\Product\Command\AddProductCommand;
use PrestaShop\PrestaShop\Core\Domain\Product\Command\DeleteProductCommand;
use PrestaShop\PrestaShop\Core\Domain\Product\Command\UpdateProductCommand;
use PrestaShop\PrestaShop\Core\Domain\Product\Exception\ProductNotFoundException;
use PrestaShop\PrestaShop\Core\Domain\Product\Query\GetProductForEditing;
use PrestaShop\PrestaShop\Core\Domain\Shop\Exception\ShopAssociationNotFound;
use PrestaShop\PrestaShop\Core\Util\DateTime\DateImmutable;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSCreate;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSDelete;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSGet;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSPartialUpdate;
use PrestaShopBundle\ApiPlatform\Metadata\LocalizedValue;
use Symfony\Component\HttpFoundation\Response;

#[ApiResource(
    operations: [
        new CQRSGet(
            uriTemplate: '/products/{productId}',
            CQRSQuery: GetProductForEditing::class,
            scopes: [
                'product_read',
            ],
            CQRSQueryMapping: Product::QUERY_MAPPING,
        ),
        new CQRSCreate(
            uriTemplate: '/products',
            CQRSCommand: AddProductCommand::class,
            CQRSQuery: GetProductForEditing::class,
            scopes: [
                'product_write',
            ],
            CQRSQueryMapping: Product::QUERY_MAPPING,
            CQRSCommandMapping: self::CREATE_MAPPING,
        ),
        new CQRSPartialUpdate(
            uriTemplate: '/products/{productId}',
            CQRSCommand: UpdateProductCommand::class,
            CQRSQuery: GetProductForEditing::class,
            scopes: [
                'product_write',
            ],
            CQRSQueryMapping: Product::QUERY_MAPPING,
            CQRSCommandMapping: Product::UPDATE_MAPPING,
        ),
        new CQRSDelete(
            uriTemplate: '/products/{productId}',
            CQRSCommand: DeleteProductCommand::class,
            scopes: [
                'product_write',
            ],
            CQRSCommandMapping: [
                '[_context][shopConstraint]' => '[shopConstraint]',
            ]
        ),
    ],
    exceptionToStatus: [
        ProductNotFoundException::class => Response::HTTP_NOT_FOUND,
        ShopAssociationNotFound::class => Response::HTTP_NOT_FOUND,
    ],
)]
class Product
{
    #[ApiProperty(identifier: true)]
    public int $productId;

    public string $type;

    public bool $enabled;

    #[LocalizedValue]
    public array $names;

    #[LocalizedValue]
    public array $descriptions;

    #[LocalizedValue]
    public array $shortDescriptions;

    #[LocalizedValue]
    public array $tags;

    public DecimalNumber $priceTaxExcluded;

    public DecimalNumber $priceTaxIncluded;

    public DecimalNumber $ecotaxTaxExcluded;

    public DecimalNumber $ecotaxTaxIncluded;

    public int $taxRulesGroupId;

    public bool $onSale;

    public DecimalNumber $wholesalePrice;

    public DecimalNumber $unitPriceTaxExcluded;

    public DecimalNumber $unitPriceTaxIncluded;

    public string $unity;

    public DecimalNumber $unitPriceRatio;

    public string $visibility;

    public bool $availableForOrder;

    public bool $onlineOnly;

    public bool $showPrice;

    public string $condition;

    public bool $showCondition;

    public int $manufacturerId;

    public string $isbn;

    public string $upc;

    public string $gtin;

    public string $mpn;

    public string $reference;

    public DecimalNumber $width;

    public DecimalNumber $height;

    public DecimalNumber $depth;

    public DecimalNumber $weight;

    public DecimalNumber $additionalShippingCost;

    #[ApiProperty(openapiContext: ['type' => 'array', 'items' => ['type' => 'integer'], 'example' => [1, 3]])]
    public array $carrierReferenceIds;

    public int $deliveryTimeNoteType;

    #[LocalizedValue]
    public array $deliveryTimeInStockNotes;

    #[LocalizedValue]
    public array $deliveryTimeOutOfStockNotes;

    #[LocalizedValue]
    public array $metaTitles;

    #[LocalizedValue]
    public array $metaDescriptions;

    #[LocalizedValue]
    public array $linkRewrites;

    public string $redirectType;

    public ?int $redirectTarget = null;

    public int $packStockType;

    public int $outOfStockType;

    public int $quantity;

    public int $minimalQuantity;

    public int $lowStockThreshold;

    public bool $lowStockAlertEnabled;

    #[LocalizedValue]
    public array $availableNowLabels;

    public string $location;

    #[LocalizedValue]
    public array $availableLaterLabels;

    public ?DateImmutable $availableDate = null;

    public string $coverThumbnailUrl;

    #[ApiProperty(openapiContext: ['type' => 'array', 'items' => ['type' => 'integer'], 'example' => [1, 3]])]
    public array $shopIds;

    #[ApiProperty(openapiContext: [
        'type' => 'array',
        'items' => [
            'type' => 'object',
            'properties' => [
                'categoryId' => ['type' => 'integer'],
                'name' => ['type' => 'string'],
                'displayName' => ['type' => 'string'],
            ],
        ],
        'example' => [
            [
                'categoryId' => 2,
                'name' => 'Home',
                'displayName' => 'Home',
            ],
        ]])
    ]
    public array $categories;

    public int $defaultCategoryId;

    public const QUERY_MAPPING = [
        '[_context][shopConstraint]' => '[shopConstraint]',
        '[_context][langId]' => '[displayLanguageId]',
        '[active]' => '[enabled]',
        '[basicInformation][localizedNames]' => '[names]',
        '[basicInformation][localizedDescriptions]' => '[descriptions]',
        '[basicInformation][localizedShortDescriptions]' => '[shortDescriptions]',
        '[basicInformation][localizedTags]' => '[tags]',
        '[pricesInformation][price]' => '[priceTaxExcluded]',
        '[pricesInformation][priceTaxIncluded]' => '[priceTaxIncluded]',
        '[pricesInformation][ecotax]' => '[ecotaxTaxExcluded]',
        '[pricesInformation][ecotaxTaxIncluded]' => '[ecotaxTaxIncluded]',
        '[pricesInformation][taxRulesGroupId]' => '[taxRulesGroupId]',
        '[pricesInformation][onSale]' => '[onSale]',
        '[pricesInformation][wholesalePrice]' => '[wholesalePrice]',
        '[pricesInformation][unitPrice]' => '[unitPriceTaxExcluded]',
        '[pricesInformation][unitPriceTaxIncluded]' => '[unitPriceTaxIncluded]',
        '[pricesInformation][unity]' => '[unity]',
        '[pricesInformation][unitPriceRatio]' => '[unitPriceRatio]',
        '[options][visibility]' => '[visibility]',
        '[options][availableForOrder]' => '[availableForOrder]',
        '[options][onlineOnly]' => '[onlineOnly]',
        '[options][showPrice]' => '[showPrice]',
        '[options][condition]' => '[condition]',
        '[options][showCondition]' => '[showCondition]',
        '[options][manufacturerId]' => '[manufacturerId]',
        '[details][isbn]' => '[isbn]',
        '[details][upc]' => '[upc]',
        '[details][gtin]' => '[gtin]',
        '[details][mpn]' => '[mpn]',
        '[details][reference]' => '[reference]',
        '[shippingInformation][width]' => '[width]',
        '[shippingInformation][height]' => '[height]',
        '[shippingInformation][depth]' => '[depth]',
        '[shippingInformation][weight]' => '[weight]',
        '[shippingInformation][additionalShippingCost]' => '[additionalShippingCost]',
        '[shippingInformation][carrierReferences]' => '[carrierReferenceIds]',
        '[shippingInformation][deliveryTimeNoteType]' => '[deliveryTimeNoteType]',
        '[shippingInformation][localizedDeliveryTimeInStockNotes]' => '[deliveryTimeInStockNotes]',
        '[shippingInformation][localizedDeliveryTimeOutOfStockNotes]' => '[deliveryTimeOutOfStockNotes]',
        '[productSeoOptions][localizedMetaTitles]' => '[metaTitles]',
        '[productSeoOptions][localizedMetaDescriptions]' => '[metaDescriptions]',
        '[productSeoOptions][localizedLinkRewrites]' => '[linkRewrites]',
        '[productSeoOptions][redirectType]' => '[redirectType]',
        '[productSeoOptions][redirectTarget][id]' => '[redirectTarget]',
        '[stockInformation][packStockType]' => '[packStockType]',
        '[stockInformation][outOfStockType]' => '[outOfStockType]',
        '[stockInformation][quantity]' => '[quantity]',
        '[stockInformation][minimalQuantity]' => '[minimalQuantity]',
        '[stockInformation][lowStockThreshold]' => '[lowStockThreshold]',
        '[stockInformation][lowStockAlertEnabled]' => '[lowStockAlertEnabled]',
        '[stockInformation][localizedAvailableNowLabels]' => '[availableNowLabels]',
        '[stockInformation][localizedAvailableLaterLabels]' => '[availableLaterLabels]',
        '[stockInformation][location]' => '[location]',
        '[stockInformation][availableDate]' => '[availableDate]',
        // Transform each field one by one (instead of the whole array) to avoid having an extra id field in the target
        '[categoriesInformation][categoriesInformation][@index][id]' => '[categories][@index][categoryId]',
        '[categoriesInformation][categoriesInformation][@index][name]' => '[categories][@index][name]',
        '[categoriesInformation][categoriesInformation][@index][displayName]' => '[categories][@index][displayName]',
        '[categoriesInformation][defaultCategoryId]' => '[defaultCategoryId]',
    ];

    public const CREATE_MAPPING = [
        '[_context][shopId]' => '[shopId]',
        '[type]' => '[productType]',
        '[names]' => '[localizedNames]',
        '[enabled]' => '[active]',
    ];

    public const UPDATE_MAPPING = [
        '[_context][shopConstraint]' => '[shopConstraint]',
        '[type]' => '[productType]',
        '[enabled]' => '[active]',
        '[names]' => '[localizedNames]',
        '[descriptions]' => '[localizedDescriptions]',
        '[shortDescriptions]' => '[localizedShortDescriptions]',
        '[metaTitles]' => '[localizedMetaTitles]',
        '[metaDescriptions]' => '[localizedMetaDescriptions]',
        '[linkRewrites]' => '[localizedLinkRewrites]',
        '[deliveryTimeInStockNotes]' => '[localizedDeliveryTimeInStockNotes]',
        '[deliveryTimeOutOfStockNotes]' => '[localizedDeliveryTimeOutOfStockNotes]',
        '[availableNowLabels]' => '[localizedAvailableNowLabels]',
        '[availableLaterLabels]' => '[localizedAvailableLaterLabels]',
        '[priceTaxExcluded]' => '[price]',
        '[unitPriceTaxExcluded]' => '[unitPrice]',
        '[ecotaxTaxExcluded]' => '[ecotax]',
    ];
}
