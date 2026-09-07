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

/**
 * The query result is mostly made of flat legacy arrays whose snake_case keys would land as-is in the JSON contract.
 * QUERY_MAPPING copies each value into a new camelCase root path, named after the Cart resource when both expose the
 * same value. The original containers (customerInformation, orderInformation, cartSummary) are deliberately not
 * declared below, so denormalization drops them.
 */
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

    #[ApiProperty(openapiContext: [
        'type' => 'object',
        'properties' => [
            'customerId' => ['type' => 'integer'],
            'firstName' => ['type' => 'string'],
            'lastName' => ['type' => 'string'],
            'gender' => ['type' => 'string'],
            'email' => ['type' => 'string'],
            'registrationDate' => ['type' => 'string'],
            'validOrdersCount' => ['type' => 'integer'],
            'totalSpentSinceRegistration' => ['type' => 'string'],
        ],
    ])]
    public array $customer = [];

    #[ApiProperty(openapiContext: [
        'type' => 'object',
        'description' => 'Empty values when the cart has not been ordered yet.',
        'properties' => [
            'orderId' => ['type' => 'integer', 'nullable' => true],
            'placedDate' => ['type' => 'string'],
        ],
    ])]
    public array $order = [];

    #[ApiProperty(openapiContext: [
        'type' => 'array',
        'items' => [
            'type' => 'object',
            'properties' => [
                'productId' => ['type' => 'integer'],
                'name' => ['type' => 'string'],
                'attribute' => ['type' => 'string'],
                'reference' => ['type' => 'string'],
                'supplierReference' => ['type' => 'string'],
                'availableStock' => ['type' => 'integer'],
                'quantity' => ['type' => 'integer'],
                'unitPrice' => ['type' => 'number'],
                'unitPriceFormatted' => ['type' => 'string'],
                'totalPrice' => ['type' => 'number'],
                'totalPriceFormatted' => ['type' => 'string'],
                'image' => ['type' => 'string', 'description' => 'Ready to use HTML img tag, empty when the product has no image.'],
                'customization' => [
                    'type' => 'object',
                    'description' => 'Empty array when the product line is not customized.',
                    'properties' => [
                        'quantity' => ['type' => 'integer'],
                        'fields' => [
                            'type' => 'array',
                            'description' => 'Each entry has a name, a value and a type, customizable_text_field or customizable_file. Text fields also carry allow_html, file fields an image path.',
                        ],
                    ],
                ],
            ],
        ],
    ])]
    public array $products = [];

    #[ApiProperty(openapiContext: [
        'type' => 'array',
        'items' => [
            'type' => 'object',
            'properties' => [
                'cartRuleId' => ['type' => 'integer'],
                'name' => ['type' => 'string'],
                'freeShipping' => ['type' => 'boolean'],
                'formattedValue' => ['type' => 'string'],
            ],
        ],
    ])]
    public array $cartRules = [];

    #[ApiProperty(openapiContext: [
        'type' => 'object',
        'properties' => [
            'totalProducts' => ['type' => 'number'],
            'totalProductsFormatted' => ['type' => 'string'],
            'totalDiscounts' => ['type' => 'number'],
            'totalDiscountsFormatted' => ['type' => 'string'],
            'totalWrapping' => ['type' => 'number'],
            'totalWrappingFormatted' => ['type' => 'string'],
            'totalShipping' => ['type' => 'number'],
            'totalShippingFormatted' => ['type' => 'string'],
            'total' => ['type' => 'number'],
            'totalFormatted' => ['type' => 'string'],
            'taxIncluded' => ['type' => 'boolean'],
        ],
    ])]
    public array $summary = [];

    #[ApiProperty(openapiContext: ['type' => 'string', 'nullable' => true, 'description' => 'Null once the cart has been ordered.'])]
    public ?string $cartLink = null;

    #[ApiProperty(openapiContext: ['type' => 'string'])]
    public string $dateAdd = '';

    #[ApiProperty(openapiContext: ['type' => 'string'])]
    public string $dateUpd = '';

    public const QUERY_MAPPING = [
        '[cartCurrencyId]' => '[currencyId]',

        '[customerInformation][id]' => '[customer][customerId]',
        '[customerInformation][first_name]' => '[customer][firstName]',
        '[customerInformation][last_name]' => '[customer][lastName]',
        '[customerInformation][gender]' => '[customer][gender]',
        '[customerInformation][email]' => '[customer][email]',
        '[customerInformation][registration_date]' => '[customer][registrationDate]',
        '[customerInformation][valid_orders_count]' => '[customer][validOrdersCount]',
        '[customerInformation][total_spent_since_registration]' => '[customer][totalSpentSinceRegistration]',

        '[orderInformation][id]' => '[order][orderId]',
        '[orderInformation][placed_date]' => '[order][placedDate]',

        '[cartSummary][products][@productIndex][id]' => '[products][@productIndex][productId]',
        '[cartSummary][products][@productIndex][name]' => '[products][@productIndex][name]',
        '[cartSummary][products][@productIndex][attributes]' => '[products][@productIndex][attribute]',
        '[cartSummary][products][@productIndex][reference]' => '[products][@productIndex][reference]',
        '[cartSummary][products][@productIndex][supplier_reference]' => '[products][@productIndex][supplierReference]',
        '[cartSummary][products][@productIndex][stock_quantity]' => '[products][@productIndex][availableStock]',
        '[cartSummary][products][@productIndex][cart_quantity]' => '[products][@productIndex][quantity]',
        '[cartSummary][products][@productIndex][unit_price]' => '[products][@productIndex][unitPrice]',
        '[cartSummary][products][@productIndex][unit_price_formatted]' => '[products][@productIndex][unitPriceFormatted]',
        '[cartSummary][products][@productIndex][total_price]' => '[products][@productIndex][totalPrice]',
        '[cartSummary][products][@productIndex][total_price_formatted]' => '[products][@productIndex][totalPriceFormatted]',
        '[cartSummary][products][@productIndex][image]' => '[products][@productIndex][image]',
        // Copied as a whole: the mapper cannot expand a second "@index" placeholder nested inside a first one, so the
        // customization fields keep the format built by the core query handler.
        '[cartSummary][products][@productIndex][customization]' => '[products][@productIndex][customization]',

        '[cartSummary][cart_rules][@cartRuleIndex][id]' => '[cartRules][@cartRuleIndex][cartRuleId]',
        '[cartSummary][cart_rules][@cartRuleIndex][name]' => '[cartRules][@cartRuleIndex][name]',
        '[cartSummary][cart_rules][@cartRuleIndex][is_free_shipping]' => '[cartRules][@cartRuleIndex][freeShipping]',
        '[cartSummary][cart_rules][@cartRuleIndex][formatted_value]' => '[cartRules][@cartRuleIndex][formattedValue]',

        '[cartSummary][total_products]' => '[summary][totalProducts]',
        '[cartSummary][total_products_formatted]' => '[summary][totalProductsFormatted]',
        '[cartSummary][total_discounts]' => '[summary][totalDiscounts]',
        '[cartSummary][total_discounts_formatted]' => '[summary][totalDiscountsFormatted]',
        '[cartSummary][total_wrapping]' => '[summary][totalWrapping]',
        '[cartSummary][total_wrapping_formatted]' => '[summary][totalWrappingFormatted]',
        '[cartSummary][total_shipping]' => '[summary][totalShipping]',
        '[cartSummary][total_shipping_formatted]' => '[summary][totalShippingFormatted]',
        '[cartSummary][total]' => '[summary][total]',
        '[cartSummary][total_formatted]' => '[summary][totalFormatted]',
        '[cartSummary][is_tax_included]' => '[summary][taxIncluded]',

        '[cartSummary][cart_link]' => '[cartLink]',
        '[cartSummary][date_add]' => '[dateAdd]',
        '[cartSummary][date_upd]' => '[dateUpd]',
    ];
}
