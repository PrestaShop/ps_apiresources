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
use PrestaShopBundle\ApiPlatform\Metadata\CQRSCreate;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSGet;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSPartialUpdate;
use Symfony\Component\HttpFoundation\Response;

#[ApiResource(
    operations: [
        new CQRSGet(
            uriTemplate: '/carts/{cartId}',
            requirements: ['cartId' => '\d+'],
            scopes: ['cart_read'],
            CQRSQuery: \PrestaShop\PrestaShop\Core\Domain\Cart\Query\GetCartForViewing::class,
            CQRSQueryMapping: [
                '[cartId]' => '[cartId]',
                '[customer][id]' => '[customerId]',
                '[customer][firstname]' => '[customerFirstname]',
                '[customer][lastname]' => '[customerLastname]',
                '[customer][email]' => '[customerEmail]',
                '[shop][id]' => '[shopId]',
                '[currency][id]' => '[currencyId]',
                '[currency][iso_code]' => '[currencyIso]',
                '[language][id]' => '[langId]',
                '[language][name]' => '[langName]',
                '[deliveryAddress][id]' => '[deliveryAddressId]',
                '[invoiceAddress][id]' => '[invoiceAddressId]',
                '[carrier][id]' => '[carrierId]',
                '[carrier][name]' => '[carrierName]',
                '[products]' => '[products]',
                '[totals][total_products]' => '[totalProducts]',
                '[totals][total_products_tax_incl]' => '[totalProductsTaxIncl]',
                '[totals][total_products_tax_excl]' => '[totalProductsTaxExcl]',
                '[totals][total_discounts]' => '[totalDiscounts]',
                '[totals][total_discounts_tax_incl]' => '[totalDiscountsTaxIncl]',
                '[totals][total_discounts_tax_excl]' => '[totalDiscountsTaxExcl]',
                '[totals][total_shipping]' => '[totalShipping]',
                '[totals][total_shipping_tax_incl]' => '[totalShippingTaxIncl]',
                '[totals][total_shipping_tax_excl]' => '[totalShippingTaxExcl]',
                '[totals][total_tax]' => '[totalTax]',
                '[totals][total_tax_incl]' => '[totalTaxIncl]',
                '[totals][total_tax_excl]' => '[totalTaxExcl]',
                '[createdAt]' => '[dateAdd]',
                '[updatedAt]' => '[dateUpd]',
            ],
        ),
        new CQRSPartialUpdate(
            uriTemplate: '/carts/{cartId}/products',
            requirements: ['cartId' => '\d+'],
            scopes: ['cart_write'],
            CQRSCommand: \PrestaShop\PrestaShop\Core\Domain\Cart\Command\AddProductToCartCommand::class,
            CQRSCommandMapping: [
                '[cartId]' => '[cartId]',
                '[productId]' => '[productId]',
                '[quantity]' => '[quantity]',
                '[combinationId]' => '[combinationId]',
                '[customizationsByFieldIds]' => '[customizationsByFieldIds]',
            ],
            openapiContext: [
                'summary' => 'Add product to cart',
                'description' => 'Add a product to an existing cart',
            ],
        ),
        new CQRSCreate(
            uriTemplate: '/carts',
            allowEmptyBody: true,
            scopes: ['cart_write'],
            CQRSCommand: \PrestaShop\PrestaShop\Core\Domain\Cart\Command\CreateEmptyCustomerCartCommand::class,
            CQRSCommandMapping: [
                '[customerId]' => '[customerId]',
            ],
        ),
    ],
    exceptionToStatus: [
        \PrestaShop\PrestaShop\Core\Domain\Cart\Exception\CartNotFoundException::class => Response::HTTP_NOT_FOUND,
        \PrestaShop\PrestaShop\Core\Domain\Cart\Exception\CartException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException::class => Response::HTTP_FORBIDDEN,
    ],
)]
class Cart
{
    #[ApiProperty(identifier: true)]
    public int $cartId = 0;

    public int $customerId = 0;

    public int $shopId = 0;

    public int $currencyId = 0;

    public int $langId = 0;

    public int $deliveryAddressId = 0;

    public int $invoiceAddressId = 0;

    public int $carrierId = 0;

    public string $dateAdd = '';

    public string $dateUpd = '';

    public float $totalProducts = 0.0;

    public float $totalProductsWt = 0.0;

    public float $totalDiscounts = 0.0;

    public float $totalDiscountsTaxIncl = 0.0;

    public float $totalDiscountsTaxExcl = 0.0;

    public float $totalShipping = 0.0;

    public float $totalShippingTaxIncl = 0.0;

    public float $totalShippingTaxExcl = 0.0;

    public float $totalTax = 0.0;

    public float $totalTaxIncl = 0.0;

    public float $totalTaxExcl = 0.0;

    // Fields for cart creation
    public array $products = [];

    public ?array $customer = null;

    public ?array $shop = null;

    public ?array $currency = null;

    public ?array $language = null;

    public ?array $deliveryAddress = null;

    public ?array $invoiceAddress = null;

    public ?array $carrier = null;

    public ?array $totals = null;

    public ?string $createdAt = null;

    public ?string $updatedAt = null;

    // Additional fields for detailed view
    public string $customerFirstname = '';

    public string $customerLastname = '';

    public string $customerEmail = '';

    public string $currencyIso = '';

    public string $langName = '';

    public string $carrierName = '';

    public float $totalProductsTaxIncl = 0.0;

    public float $totalProductsTaxExcl = 0.0;

    // Fields for adding products to cart
    public int $productId = 0;

    public int $quantity = 0;

    public ?int $combinationId = null;

    public array $customizationsByFieldIds = [];
}
