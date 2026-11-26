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
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use PrestaShop\Decimal\DecimalNumber;
use PrestaShop\PrestaShop\Core\ConstraintValidator\Constraints\DefaultLanguage;
use PrestaShop\PrestaShop\Core\ConstraintValidator\Constraints\TypedRegex;
use PrestaShop\PrestaShop\Core\Domain\Product\Command\AddProductCommand;
use PrestaShop\PrestaShop\Core\Domain\Product\Command\DeleteProductCommand;
use PrestaShop\PrestaShop\Core\Domain\Product\Command\DuplicateProductCommand;
use PrestaShop\PrestaShop\Core\Domain\Product\Command\RemoveAllProductTagsCommand;
use PrestaShop\PrestaShop\Core\Domain\Product\Command\SetProductTagsCommand;
use PrestaShop\PrestaShop\Core\Domain\Product\Command\UpdateProductCommand;
use PrestaShop\PrestaShop\Core\Domain\Product\Command\UpdateProductTypeCommand;
use PrestaShop\PrestaShop\Core\Domain\Product\Exception\InvalidProductTypeException;
use PrestaShop\PrestaShop\Core\Domain\Product\Exception\ProductConstraintException;
use PrestaShop\PrestaShop\Core\Domain\Product\Exception\ProductNotFoundException;
use PrestaShop\PrestaShop\Core\Domain\Product\Pack\Exception\ProductPackException;
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
        new CQRSCreate(
            uriTemplate: '/products/{productId}/duplicate',
            requirements: ['productId' => '\d+'],
            // The product to duplicate is identified by the URI, no request body is expected
            allowEmptyBody: true,
            CQRSCommand: DuplicateProductCommand::class,
            CQRSQuery: GetProductForEditing::class,
            scopes: [
                'product_write',
            ],
            CQRSQueryMapping: Product::QUERY_MAPPING,
            CQRSCommandMapping: [
                '[_context][shopConstraint]' => '[shopConstraint]',
            ],
            openapi: new OpenApiOperation(
                summary: 'Duplicate a product.',
                description: 'Creates a copy of the product (combinations, images, prices, associations...) and answers '
                    . 'the new product. The copy is disabled, its names are prefixed with "copy of" (translated in each '
                    . 'language) and its link rewrites with "copy-of".',
            ),
        ),
        new CQRSPartialUpdate(
            uriTemplate: '/products/{productId}/change-type',
            requirements: ['productId' => '\d+'],
            validationContext: ['groups' => ['Default', 'ChangeType']],
            CQRSCommand: UpdateProductTypeCommand::class,
            CQRSQuery: GetProductForEditing::class,
            scopes: [
                'product_write',
            ],
            CQRSQueryMapping: Product::QUERY_MAPPING,
            CQRSCommandMapping: [
                '[type]' => '[productType]',
            ],
            exceptionToStatus: [
                ProductNotFoundException::class => Response::HTTP_NOT_FOUND,
                ProductConstraintException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
                // A product used in a pack cannot become a pack
                InvalidProductTypeException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
                // The packed products of a pack changed to another type are removed first
                ProductPackException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
            ],
            openapi: new OpenApiOperation(
                summary: 'Change the type of a product.',
                description: 'Sets the type of the product and answers the updated product. The content of the '
                    . 'previous type is removed: the packed products of a pack, the combinations of a product with '
                    . 'combinations (its stock is reset), the file of a virtual product. Changing to the combinations '
                    . 'type also resets the stock of the product. A product contained in a pack cannot become a pack '
                    . '(422).',
            ),
        ),
        new CQRSPartialUpdate(
            uriTemplate: '/products/{productId}/tags',
            requirements: ['productId' => '\d+'],
            validationContext: ['groups' => ['Default', 'SetTags']],
            CQRSCommand: SetProductTagsCommand::class,
            CQRSQuery: GetProductForEditing::class,
            scopes: [
                'product_write',
            ],
            CQRSQueryMapping: Product::QUERY_MAPPING,
            CQRSCommandMapping: [
                '[tags]' => '[localizedTags]',
            ],
            openapi: new OpenApiOperation(
                summary: 'Set the tags of a product.',
                description: 'Replaces the tags of each language sent and answers the updated product: the languages '
                    . 'left out keep their tags, an empty list removes the tags of its language.',
            ),
        ),
        new CQRSDelete(
            uriTemplate: '/products/{productId}/tags',
            requirements: ['productId' => '\d+'],
            CQRSCommand: RemoveAllProductTagsCommand::class,
            scopes: [
                'product_write',
            ],
            extraProperties: [
                // A CQRSDelete answers the resource read back by a query from PrestaShop 9.2.2, the arguments are
                // passed as extra properties so that the resource still loads on the older cores
                'CQRSQuery' => GetProductForEditing::class,
                'CQRSQueryMapping' => Product::QUERY_MAPPING,
                'minVersion' => '9.2.2',
            ],
            openapi: new OpenApiOperation(
                summary: 'Remove all the tags of a product.',
                description: 'Requires PrestaShop 9.2.2. Removes the tags of the product in every language and answers '
                    . 'the updated product.',
            ),
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

    #[Assert\NotBlank(groups: ['Create', 'ChangeType'])]
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

    /**
     * The tags of each language, a list of strings by locale. Written by PATCH /products/{productId}/tags.
     */
    #[LocalizedValue]
    #[Assert\NotBlank(groups: ['SetTags'])]
    #[Assert\All(constraints: [
        new Assert\Sequentially([
            new Assert\Type('array'),
            new Assert\All(constraints: [
                new Assert\Type('string'),
            ]),
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

    /**
     * @var int[]
     */
    #[ApiProperty(openapiContext: ['example' => [1, 3]])]
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
     * Managed via /products/{productId}/virtual-files to add or update it, and
     * /products/virtual-files/{virtualProductFileId} to delete it.
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
            'productId' => ['type' => 'integer'],
        ],
    ])]
    public ?array $virtualProductFile = null;

    public string $coverThumbnailUrl;

    /**
     * @var int[]
     */
    #[ApiProperty(openapiContext: ['example' => [1, 3]])]
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
        // The virtual file query result only holds its product id since PrestaShop 9.3, the mapping is
        // skipped when the product has no virtual file (a null target is not writable)
        '[productId]' => '[virtualProductFile][productId]',
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
