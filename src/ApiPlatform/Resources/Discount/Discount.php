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

namespace PrestaShop\Module\APIResources\ApiPlatform\Resources\Discount;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use PrestaShop\Decimal\DecimalNumber;
use PrestaShop\PrestaShop\Core\Domain\Discount\Command\AddDiscountCommand;
use PrestaShop\PrestaShop\Core\Domain\Discount\Command\DeleteDiscountCommand;
use PrestaShop\PrestaShop\Core\Domain\Discount\Command\DuplicateDiscountCommand;
use PrestaShop\PrestaShop\Core\Domain\Discount\Command\UpdateDiscountCommand;
use PrestaShop\PrestaShop\Core\Domain\Discount\Exception\DiscountConstraintException;
use PrestaShop\PrestaShop\Core\Domain\Discount\Exception\DiscountNotFoundException;
use PrestaShop\PrestaShop\Core\Domain\Discount\Query\GetDiscountForEditing;
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
            uriTemplate: '/discounts/{discountId}',
            requirements: ['discountId' => '\d+'],
            CQRSQuery: GetDiscountForEditing::class,
            scopes: ['discount_read'],
            CQRSQueryMapping: self::QUERY_MAPPING,
        ),
        new CQRSCreate(
            uriTemplate: '/discounts',
            validationContext: ['groups' => ['Default', 'Create']],
            CQRSCommand: AddDiscountCommand::class,
            CQRSQuery: GetDiscountForEditing::class,
            scopes: ['discount_write'],
            CQRSQueryMapping: self::QUERY_MAPPING,
            CQRSCommandMapping: self::COMMAND_MAPPING,
        ),
        new CQRSPartialUpdate(
            uriTemplate: '/discounts/{discountId}',
            requirements: ['discountId' => '\d+'],
            CQRSCommand: UpdateDiscountCommand::class,
            CQRSQuery: GetDiscountForEditing::class,
            scopes: ['discount_write'],
            CQRSQueryMapping: self::QUERY_MAPPING,
            CQRSCommandMapping: self::COMMAND_MAPPING,
        ),
        new CQRSDelete(
            uriTemplate: '/discounts/{discountId}',
            CQRSCommand: DeleteDiscountCommand::class,
            scopes: [
                'discount_write',
            ],
        ),
        new CQRSCreate(
            uriTemplate: '/discounts/{discountId}/duplicate',
            requirements: ['discountId' => '\d+'],
            CQRSCommand: DuplicateDiscountCommand::class,
            CQRSQuery: GetDiscountForEditing::class,
            scopes: ['discount_write'],
            CQRSQueryMapping: self::QUERY_MAPPING,
            allowEmptyBody: true,
            openapi: new OpenApiOperation(
                summary: 'Duplicate a Discount resource.',
                description: 'Creates a copy of an existing Discount resource.',
            )
        ),
    ],
    normalizationContext: ['skip_null_values' => false],
    exceptionToStatus: [
        DiscountNotFoundException::class => Response::HTTP_NOT_FOUND,
        DiscountConstraintException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
    ],
)]
class Discount
{
    #[ApiProperty(identifier: true)]
    public int $discountId;
    #[Assert\NotBlank(groups: ['Create'])]
    public string $type;
    #[Assert\NotBlank(groups: ['Create'])]
    #[LocalizedValue]
    public array $names;
    public string $description;
    public string $code;
    public bool $enabled;
    public ?int $totalQuantity;
    public ?int $quantityPerUser;
    public ?DecimalNumber $reductionPercent;
    #[ApiProperty(
        openapiContext: [
            'type' => 'object',
            'description' => 'Fixed reduction amount',
            'properties' => [
                'amount' => [
                    'type' => 'number',
                    'description' => 'Fixed reduction amount value',
                ],
                'currencyId' => [
                    'type' => 'integer',
                    'description' => 'Currency ID for reduction amount',
                ],
                'taxIncluded' => [
                    'type' => 'boolean',
                    'Whether reduction amount is tax included',
                ],
            ],
        ]
    )]
    public ?array $reductionAmount;
    public ?int $giftProductId;
    public ?int $giftCombinationId;

    // Conditions/compatibility values
    public bool $cheapestProduct;
    #[ApiProperty(
        openapiContext: [
            'type' => 'array',
            'description' => 'Product conditions (rule groups)',
            'items' => [
                'type' => 'object',
                'properties' => [
                    'quantity' => ['type' => 'integer'],
                    'rules' => [
                        'type' => 'array',
                        'items' => [
                            'type' => 'object',
                            'properties' => [
                                'type' => [
                                    'type' => 'string',
                                    'enum' => [
                                        // We use hard-coded values because the ProductRuleType class is only available
                                        // in 9.1 and using it breaks the parsing of API resources on 9.0
                                        'categories',
                                        'products',
                                        'combinations',
                                        'manufacturers',
                                        'suppliers',
                                        'attributes',
                                        'features',
                                    ],
                                ],
                                'itemIds' => [
                                    'type' => 'array',
                                    'items' => ['type' => 'integer'],
                                ],
                                'required' => ['type', 'itemIds'],
                            ],
                        ],
                    ],
                    'type' => [
                        'type' => 'string',
                        'enum' => [
                            // We use hard-coded values because the ProductRuleGroupType class is only available
                            // in 9.1 and using it breaks the parsing of API resources on 9.0
                            'all_product_rules',
                            'at_least_one_product_rule',
                        ],
                    ],
                    'required' => ['quantity', 'rules'],
                ],
            ],
        ]
    )]
    public ?array $productConditions;

    #[ApiProperty(
        openapiContext: [
            'type' => 'integer',
            'description' => 'Minimum quantity of products required',
            'minimum' => 0,
        ]
    )]
    public ?int $minimumProductQuantity;

    #[ApiProperty(
        openapiContext: [
            'type' => 'object',
            'description' => 'Minimum amount required',
            'properties' => [
                'amount' => [
                    'type' => 'number',
                    'description' => 'Minimum amount value',
                ],
                'currencyId' => [
                    'type' => 'integer',
                    'description' => 'Currency ID for minimum amount',
                ],
                'taxIncluded' => [
                    'type' => 'boolean',
                    'Whether minimum amount is tax included',
                ],
                'shippingIncluded' => [
                    'type' => 'boolean',
                    'description' => 'Whether minimum amount includes shipping',
                ],
            ],
        ]
    )]
    public ?array $minimumAmount;

    public ?int $customerId;
    #[ApiProperty(
        openapiContext: [
            'type' => 'array',
            'description' => 'Customer group IDs for which the discount is valid',
            'items' => ['type' => 'integer'],
        ]
    )]
    public ?array $customerGroupIds;

    #[ApiProperty(
        openapiContext: [
            'type' => 'array',
            'description' => 'Carrier IDs for which the discount is valid',
            'items' => ['type' => 'integer'],
        ]
    )]
    public ?array $carrierIds;

    #[ApiProperty(
        openapiContext: [
            'type' => 'array',
            'description' => 'Country IDs for which the discount is valid',
            'items' => ['type' => 'integer'],
        ]
    )]
    public ?array $countryIds;
    #[ApiProperty(
        openapiContext: [
            'type' => 'array',
            'description' => 'Discount Type IDs compatible with the discount',
            'items' => ['type' => 'integer'],
        ]
    )]
    public ?array $compatibleDiscountTypeIds;
    // End of conditions/compatibility values

    public bool $highlightInCart;
    public bool $allowPartialUse;
    public int $priority;
    public \DateTimeImmutable $validFrom;
    public \DateTimeImmutable $validTo;

    protected const QUERY_MAPPING = [
        '[localizedNames]' => '[names]',
        '[active]' => '[enabled]',
    ];
    protected const COMMAND_MAPPING = [
        '[names]' => '[localizedNames]',
        '[enabled]' => '[active]',
    ];
}
