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

namespace PrestaShop\Module\APIResources\ApiPlatform\Resources\Discount;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use PrestaShop\Decimal\DecimalNumber;
use PrestaShop\PrestaShop\Core\Domain\Discount\Command\UpdateDiscountConditionsCommand;
use PrestaShop\PrestaShop\Core\Domain\Discount\Exception\DiscountConstraintException;
use PrestaShop\PrestaShop\Core\Domain\Discount\Exception\DiscountNotFoundException;
use PrestaShop\PrestaShop\Core\Domain\Discount\Query\GetDiscountForEditing;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSGet;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSPartialUpdate;
use Symfony\Component\HttpFoundation\Response;

#[ApiResource(
    operations: [
        new CQRSGet(
            uriTemplate: '/discount/{discountId}/conditions',
            requirements: ['discountId' => '\d+'],
            CQRSQuery: GetDiscountForEditing::class,
            scopes: ['discount_read'],
            CQRSQueryMapping: self::QUERY_MAPPING,
        ),
        new CQRSPartialUpdate(
            uriTemplate: '/discount/{discountId}/conditions',
            requirements: ['discountId' => '\d+'],
            CQRSCommand: UpdateDiscountConditionsCommand::class,
            CQRSQuery: GetDiscountForEditing::class,
            scopes: ['discount_write'],
            CQRSQueryMapping: self::QUERY_MAPPING,
            CQRSCommandMapping: self::COMMAND_MAPPING,
        ),
    ],
    exceptionToStatus: [
        DiscountNotFoundException::class => Response::HTTP_NOT_FOUND,
        DiscountConstraintException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
    ],
)]
class DiscountConditions
{
    #[ApiProperty(identifier: true)]
    public int $discountId;

    #[ApiProperty(
        openapiContext: [
            'type' => 'integer',
            'description' => 'Minimum quantity of products required',
            'minimum' => 0,
        ]
    )]
    public ?int $minimumProductsQuantity;

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
                                'type' => ['type' => 'string'],
                                'itemIds' => [
                                    'type' => 'array',
                                    'items' => ['type' => 'integer'],
                                ],
                            ],
                        ],
                    ],
                    'type' => ['type' => 'string'],
                ],
            ],
        ]
    )]
    public ?array $productConditions;

    #[ApiProperty(
        openapiContext: [
            'type' => 'string',
            'description' => 'Minimum amount required',
            'format' => 'decimal',
        ]
    )]
    public ?DecimalNumber $minimumAmount;

    #[ApiProperty(
        openapiContext: [
            'type' => 'integer',
            'description' => 'Currency ID for minimum amount',
        ]
    )]
    public ?int $minimumAmountCurrencyId;

    #[ApiProperty(
        openapiContext: [
            'type' => 'boolean',
            'description' => 'Whether minimum amount is tax included',
        ]
    )]
    public ?bool $minimumAmountTaxIncluded;

    #[ApiProperty(
        openapiContext: [
            'type' => 'boolean',
            'description' => 'Whether minimum amount includes shipping',
        ]
    )]
    public ?bool $minimumAmountShippingIncluded;

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

    protected const QUERY_MAPPING = [
        '[minimumProductQuantity]' => '[minimumProductsQuantity]',
    ];

    protected const COMMAND_MAPPING = [
        '[minimumAmount]' => '[amountDiscount]',
        '[minimumAmountCurrencyId]' => '[currencyId]',
        '[minimumAmountTaxIncluded]' => '[taxIncluded]',
    ];
}
