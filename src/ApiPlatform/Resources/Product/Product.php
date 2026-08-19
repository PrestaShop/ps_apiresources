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
use PrestaShop\PrestaShop\Core\ConstraintValidator\Constraints\DefaultLanguage;
use PrestaShop\PrestaShop\Core\ConstraintValidator\Constraints\TypedRegex;
use PrestaShop\PrestaShop\Core\Domain\Product\Command\AddProductCommand;
use PrestaShop\PrestaShop\Core\Domain\Product\Command\DeleteProductCommand;
use PrestaShop\PrestaShop\Core\Domain\Product\Command\UpdateProductCommand;
use PrestaShop\PrestaShop\Core\Domain\Product\Exception\ProductConstraintException;
use PrestaShop\PrestaShop\Core\Domain\Product\Exception\ProductNotFoundException;
use PrestaShop\PrestaShop\Core\Domain\Product\Pack\ValueObject\PackStockType;
use PrestaShop\PrestaShop\Core\Domain\Product\ProductSettings;
use PrestaShop\PrestaShop\Core\Domain\Product\Query\GetProductForEditing;
use PrestaShop\PrestaShop\Core\Domain\Product\Stock\ValueObject\OutOfStockType;
use PrestaShop\PrestaShop\Core\Domain\Product\ValueObject\DeliveryTimeNoteType;
use PrestaShop\PrestaShop\Core\Domain\Product\ValueObject\Gtin;
use PrestaShop\PrestaShop\Core\Domain\Product\ValueObject\Isbn;
use PrestaShop\PrestaShop\Core\Domain\Product\ValueObject\ProductCondition;
use PrestaShop\PrestaShop\Core\Domain\Product\ValueObject\ProductType;
use PrestaShop\PrestaShop\Core\Domain\Product\ValueObject\ProductVisibility;
use PrestaShop\PrestaShop\Core\Domain\Product\ValueObject\RedirectType;
use PrestaShop\PrestaShop\Core\Domain\Product\ValueObject\Reference;
use PrestaShop\PrestaShop\Core\Domain\Product\ValueObject\Upc;
use PrestaShop\PrestaShop\Core\Domain\Shop\Exception\ShopAssociationNotFound;
use PrestaShop\PrestaShop\Core\Util\DateTime\DateImmutable;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSCreate;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSDelete;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSGet;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSPartialUpdate;
use PrestaShopBundle\ApiPlatform\Metadata\LocalizedValue;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints as Assert;

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
            validationContext: ['groups' => ['Default', 'Create']],
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
            validationContext: ['groups' => ['Default', 'Update']],
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
        ProductConstraintException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
    ],
)]
class Product
{
    #[ApiProperty(identifier: true)]
    public int $productId;

    #[Assert\NotBlank(groups: ['Create'])]
    #[Assert\Choice(choices: ProductType::AVAILABLE_TYPES)]
    public string $type;

    public bool $enabled;

    #[LocalizedValue]
    // Only checked on creation: the partial update endpoint supports partial localized
    // values (the core merges them per language)
    #[DefaultLanguage(groups: ['Create'], fieldName: 'names')]
    #[Assert\All(constraints: [
        new TypedRegex([
            'type' => TypedRegex::TYPE_CATALOG_NAME,
        ]),
        new Assert\Length(max: ProductSettings::MAX_NAME_LENGTH),
    ])]
    public array $names;

    #[LocalizedValue]
    public array $descriptions;

    #[LocalizedValue]
    public array $shortDescriptions;

    #[LocalizedValue]
    #[Assert\All(constraints: [
        new TypedRegex([
            'type' => TypedRegex::TYPE_GENERIC_NAME,
        ]),
    ])]
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

    #[Assert\Choice(choices: ProductVisibility::AVAILABLE_VISIBILITY_VALUES)]
    public string $visibility;

    public bool $availableForOrder;

    public bool $onlineOnly;

    public bool $showPrice;

    #[Assert\Choice(choices: ProductCondition::AVAILABLE_CONDITIONS)]
    public string $condition;

    public bool $showCondition;

    public int $manufacturerId;

    #[TypedRegex(['type' => TypedRegex::TYPE_ISBN])]
    #[Assert\Length(max: Isbn::MAX_LENGTH)]
    public string $isbn;

    #[TypedRegex(['type' => TypedRegex::TYPE_UPC])]
    #[Assert\Length(max: Upc::MAX_LENGTH)]
    public string $upc;

    #[TypedRegex(['type' => TypedRegex::TYPE_GTIN])]
    #[Assert\Length(max: Gtin::MAX_LENGTH)]
    public string $gtin;

    #[Assert\Length(max: ProductSettings::MAX_MPN_LENGTH)]
    public string $mpn;

    #[TypedRegex(['type' => TypedRegex::TYPE_REFERENCE])]
    #[Assert\Length(max: Reference::MAX_LENGTH)]
    public string $reference;

    public DecimalNumber $width;

    public DecimalNumber $height;

    public DecimalNumber $depth;

    public DecimalNumber $weight;

    public DecimalNumber $additionalShippingCost;

    #[ApiProperty(openapiContext: ['type' => 'array', 'items' => ['type' => 'integer'], 'example' => [1, 3]])]
    public array $carrierReferenceIds;

    #[Assert\Choice(choices: DeliveryTimeNoteType::ALLOWED_TYPES)]
    public int $deliveryTimeNoteType;

    #[LocalizedValue]
    public array $deliveryTimeInStockNotes;

    #[LocalizedValue]
    public array $deliveryTimeOutOfStockNotes;

    #[LocalizedValue]
    #[Assert\All(constraints: [
        new Assert\Length(max: ProductSettings::MAX_META_TITLE_LENGTH),
    ])]
    public array $metaTitles;

    #[LocalizedValue]
    #[Assert\All(constraints: [
        new Assert\Length(max: ProductSettings::MAX_META_DESCRIPTION_LENGTH),
    ])]
    public array $metaDescriptions;

    #[LocalizedValue]
    #[Assert\All(constraints: [
        new TypedRegex([
            'type' => TypedRegex::TYPE_LINK_REWRITE,
        ]),
        new Assert\Length(max: ProductSettings::MAX_LINK_REWRITE_LENGTH),
    ])]
    public array $linkRewrites;

    #[Assert\Choice(choices: RedirectType::AVAILABLE_REDIRECT_TYPES)]
    public string $redirectType;

    public ?int $redirectTarget = null;

    #[Assert\Choice(choices: PackStockType::ALLOWED_PACK_STOCK_TYPES)]
    public int $packStockType;

    #[Assert\Choice(choices: OutOfStockType::ALLOWED_OUT_OF_STOCK_TYPES)]
    public int $outOfStockType;

    public int $quantity;

    #[Assert\Positive]
    public int $minimalQuantity;

    public int $lowStockThreshold;

    public bool $lowStockAlertEnabled;

    #[LocalizedValue]
    #[Assert\All(constraints: [
        new TypedRegex([
            'type' => TypedRegex::TYPE_GENERIC_NAME,
        ]),
        new Assert\Length(max: ProductSettings::MAX_AVAILABLE_NOW_LABEL_LENGTH),
    ])]
    public array $availableNowLabels;

    public string $location;

    #[LocalizedValue]
    #[Assert\All(constraints: [
        new TypedRegex([
            'type' => TypedRegex::TYPE_GENERIC_NAME,
        ]),
        new Assert\Length(max: ProductSettings::MAX_AVAILABLE_LATER_LABEL_LENGTH),
    ])]
    public array $availableLaterLabels;

    public ?DateImmutable $availableDate = null;

    /**
     * Virtual product file attached to the product (null for products without one).
     * Managed via the /products/{productId}/virtual-files endpoints.
     */
    #[ApiProperty(openapiContext: [
        'type' => 'object',
        'nullable' => true,
        'properties' => [
            'id' => ['type' => 'integer'],
            'fileName' => ['type' => 'string'],
            'displayName' => ['type' => 'string'],
            'accessDays' => ['type' => 'integer'],
            'downloadTimesLimit' => ['type' => 'integer'],
            'expirationDate' => ['type' => 'string', 'format' => 'date-time', 'nullable' => true],
        ],
    ])]
    public ?array $virtualProductFile = null;

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
