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
use PrestaShop\PrestaShop\Core\Domain\Cart\Command\AddProductToCartCommand;
use PrestaShop\PrestaShop\Core\Domain\Cart\Command\RemoveProductFromCartCommand;
use PrestaShop\PrestaShop\Core\Domain\Cart\Command\UpdateProductPriceInCartCommand;
use PrestaShop\PrestaShop\Core\Domain\Cart\Command\UpdateProductQuantityInCartCommand;
use PrestaShop\PrestaShop\Core\Domain\Cart\Exception\CartException;
use PrestaShop\PrestaShop\Core\Domain\Cart\Exception\CartNotFoundException;
use PrestaShop\PrestaShop\Core\Domain\Cart\Query\GetCartForOrderCreation;
use PrestaShop\PrestaShop\Core\Domain\Product\Customization\Exception\CustomizationException;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSCreate;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSDelete;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSPartialUpdate;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints as Assert;

// The core has no query returning only the products of a cart, so these operations reuse GetCartForOrderCreation:
// declaring just cartId and products is enough to drop the rest of the query result on denormalization.
#[ApiResource(
    operations: [
        new CQRSCreate(
            uriTemplate: '/carts/{cartId}/products',
            requirements: ['cartId' => '\d+'],
            validationContext: ['groups' => ['Default', 'AddProduct']],
            CQRSCommand: AddProductToCartCommand::class,
            CQRSQuery: GetCartForOrderCreation::class,
            scopes: ['cart_write'],
        ),
        new CQRSDelete(
            uriTemplate: '/carts/{cartId}/products/{productId}',
            requirements: ['cartId' => '\d+', 'productId' => '\d+'],
            // A CQRSDelete answers 204 with no content by default, but here the updated product list is the whole
            // point of the endpoint, hence the explicit status and output.
            status: Response::HTTP_OK,
            CQRSCommand: RemoveProductFromCartCommand::class,
            scopes: ['cart_write'],
            output: CartProduct::class,
            extraProperties: ['CQRSQuery' => GetCartForOrderCreation::class],
        ),
        new CQRSPartialUpdate(
            uriTemplate: '/carts/{cartId}/products/{productId}/quantity',
            requirements: ['cartId' => '\d+', 'productId' => '\d+'],
            validationContext: ['groups' => ['Default', 'UpdateQuantity']],
            CQRSCommand: UpdateProductQuantityInCartCommand::class,
            CQRSQuery: GetCartForOrderCreation::class,
            scopes: ['cart_write'],
        ),
        new CQRSPartialUpdate(
            uriTemplate: '/carts/{cartId}/products/{productId}/price',
            requirements: ['cartId' => '\d+', 'productId' => '\d+'],
            validationContext: ['groups' => ['Default', 'UpdatePrice']],
            CQRSCommand: UpdateProductPriceInCartCommand::class,
            CQRSQuery: GetCartForOrderCreation::class,
            scopes: ['cart_write'],
        ),
    ],
    // Order matters, the first match wins: CartNotFoundException extends CartException.
    exceptionToStatus: [
        CartNotFoundException::class => Response::HTTP_NOT_FOUND,
        CartException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        CustomizationException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
    ],
)]
class CartProduct
{
    #[ApiProperty(identifier: true)]
    public int $cartId;

    // From the URI, except on POST /products where it comes from the body
    #[Assert\NotNull(groups: ['AddProduct'])]
    #[Assert\Positive(groups: ['AddProduct'])]
    #[ApiProperty(openapiContext: ['type' => 'integer', 'example' => 1])]
    public int $productId;

    #[ApiProperty(openapiContext: [
        'type' => 'array',
        'description' => 'Read-only. The product list of the cart, as updated by the operation.',
        'items' => [
            'type' => 'object',
            'properties' => [
                'productId' => ['type' => 'integer'],
                'attributeId' => ['type' => 'integer'],
                'name' => ['type' => 'string'],
                'attribute' => ['type' => 'string'],
                'reference' => ['type' => 'string'],
                'unitPrice' => ['type' => 'string'],
                'quantity' => ['type' => 'integer'],
                'price' => ['type' => 'string'],
                'imageLink' => ['type' => 'string'],
                'availableStock' => ['type' => 'integer'],
                'availableOutOfStock' => ['type' => 'boolean'],
                'gift' => ['type' => 'boolean'],
                'customization' => [
                    'nullable' => true,
                    'type' => 'object',
                    'properties' => [
                        'customizationId' => ['type' => 'integer'],
                        'customizationFieldsData' => ['type' => 'array'],
                    ],
                ],
            ],
        ],
    ])]
    public array $products;

    // Write-only fields below, body of POST /products and of PATCH /products/{productId}/quantity
    #[Assert\NotNull(groups: ['AddProduct', 'UpdateQuantity'])]
    #[Assert\Positive(groups: ['AddProduct', 'UpdateQuantity'])]
    #[ApiProperty(openapiContext: ['type' => 'integer', 'example' => 2])]
    public int $quantity;

    // Must be sent as a JSON number, UpdateProductPriceInCartCommand rejects anything that is not a PHP float
    #[Assert\NotNull(groups: ['UpdatePrice'])]
    #[Assert\PositiveOrZero(groups: ['UpdatePrice'])]
    #[ApiProperty(openapiContext: ['type' => 'number', 'format' => 'float', 'example' => 19.99])]
    public float $price;

    // Optional, for products with combinations
    #[Assert\Positive(groups: ['AddProduct', 'UpdateQuantity', 'UpdatePrice'])]
    #[ApiProperty(openapiContext: ['type' => 'integer', 'example' => null, 'nullable' => true])]
    public ?int $combinationId;

    // Optional, to target a single customized line of the product rather than all of them
    #[Assert\Positive(groups: ['UpdateQuantity'])]
    #[ApiProperty(openapiContext: ['type' => 'integer', 'example' => null, 'nullable' => true])]
    public ?int $customizationId;

    // Optional, text customizations to attach to the product being added. Only text fields are supported: file
    // fields expect an UploadedFile, which a JSON body cannot carry.
    #[ApiProperty(openapiContext: [
        'type' => 'object',
        'nullable' => true,
        'description' => 'Key-value pairs where key is the customization field ID and value is the text customization value',
        'example' => ['1' => 'My custom text'],
    ])]
    public array $customizationsByFieldIds;
}
