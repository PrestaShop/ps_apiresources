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

namespace PrestaShop\Module\APIResources\ApiPlatform\Resources\Cart;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use PrestaShop\PrestaShop\Core\Domain\Cart\Exception\CartNotFoundException;
use PrestaShop\PrestaShop\Core\Domain\Cart\Query\GetCartForViewing;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSGet;
use Symfony\Component\HttpFoundation\Response;

#[ApiResource(
    operations: [
        new CQRSGet(
            uriTemplate: '/carts/{cartId}/view',
            requirements: ['cartId' => '\d+'],
            CQRSQuery: GetCartForViewing::class,
            scopes: ['cart_read'],
            CQRSQueryMapping: self::QUERY_MAPPING,
        ),
    ],
    normalizationContext: ['skip_null_values' => false],
    exceptionToStatus: [
        CartNotFoundException::class => Response::HTTP_NOT_FOUND,
    ],
)]
class CartView
{
    #[ApiProperty(identifier: true)]
    public int $cartId;

    #[ApiProperty(openapiContext: ['type' => 'integer', 'example' => 1])]
    public int $currencyId;

    // Keys of the three arrays below are snake_case: they come straight from the legacy query result, unmapped.
    #[ApiProperty(openapiContext: [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'integer'],
            'first_name' => ['type' => 'string'],
            'last_name' => ['type' => 'string'],
            'gender' => ['type' => 'string'],
            'email' => ['type' => 'string'],
            'registration_date' => ['type' => 'string'],
            'valid_orders_count' => ['type' => 'integer'],
            'total_spent_since_registration' => ['type' => 'string'],
        ],
    ])]
    public array $customerInformation;

    #[ApiProperty(openapiContext: [
        'type' => 'object',
        'description' => 'Empty values when the cart has not been ordered yet.',
        'properties' => [
            'id' => ['type' => 'integer', 'nullable' => true],
            'placed_date' => ['type' => 'string'],
        ],
    ])]
    public array $orderInformation;

    #[ApiProperty(openapiContext: [
        'type' => 'object',
        'properties' => [
            'products' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'integer'],
                        'name' => ['type' => 'string'],
                        'attributes' => ['type' => 'string'],
                        'reference' => ['type' => 'string'],
                        'supplier_reference' => ['type' => 'string'],
                        'stock_quantity' => ['type' => 'integer'],
                        'cart_quantity' => ['type' => 'integer'],
                        'total_price' => ['type' => 'number'],
                        'unit_price' => ['type' => 'number'],
                        'total_price_formatted' => ['type' => 'string'],
                        'unit_price_formatted' => ['type' => 'string'],
                        'image' => ['type' => 'string', 'description' => 'Ready to use HTML img tag, empty when the product has no image.'],
                        'customization' => [
                            'type' => 'object',
                            'description' => 'Empty array when the product line is not customized.',
                            'properties' => [
                                'quantity' => ['type' => 'integer'],
                                'fields' => [
                                    'type' => 'array',
                                    'description' => 'Each entry has a name, a value and a type, customizable_text_field or customizable_file.',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'cart_rules' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'integer'],
                        'name' => ['type' => 'string'],
                        'is_free_shipping' => ['type' => 'boolean'],
                        'formatted_value' => ['type' => 'string'],
                    ],
                ],
            ],
            'total_products' => ['type' => 'number'],
            'total_products_formatted' => ['type' => 'string'],
            'total_discounts' => ['type' => 'number'],
            'total_discounts_formatted' => ['type' => 'string'],
            'total_wrapping' => ['type' => 'number'],
            'total_wrapping_formatted' => ['type' => 'string'],
            'total_shipping' => ['type' => 'number'],
            'total_shipping_formatted' => ['type' => 'string'],
            'total' => ['type' => 'number'],
            'total_formatted' => ['type' => 'string'],
            'is_tax_included' => ['type' => 'boolean'],
            'cart_link' => ['type' => 'string', 'nullable' => true, 'description' => 'Null once the cart has been ordered.'],
            'date_add' => ['type' => 'string'],
            'date_upd' => ['type' => 'string'],
        ],
    ])]
    public array $cartSummary;

    public const QUERY_MAPPING = [
        '[cartCurrencyId]' => '[currencyId]',
    ];
}
