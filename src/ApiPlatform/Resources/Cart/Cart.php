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
use PrestaShop\PrestaShop\Core\Domain\Cart\Command\CreateEmptyCustomerCartCommand;
use PrestaShop\PrestaShop\Core\Domain\Cart\Command\DeleteCartCommand;
use PrestaShop\PrestaShop\Core\Domain\Cart\Exception\CannotDeleteCartException;
use PrestaShop\PrestaShop\Core\Domain\Cart\Exception\CannotDeleteOrderedCartException;
use PrestaShop\PrestaShop\Core\Domain\Cart\Exception\CartConstraintException;
use PrestaShop\PrestaShop\Core\Domain\Cart\Exception\CartNotFoundException;
use PrestaShop\PrestaShop\Core\Domain\Cart\Query\GetCartForOrderCreation;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSCreate;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSDelete;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSGet;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    operations: [
        new CQRSGet(
            uriTemplate: '/carts/{cartId}',
            requirements: ['cartId' => '\d+'],
            CQRSQuery: GetCartForOrderCreation::class,
            scopes: ['cart_read'],
        ),
        new CQRSCreate(
            uriTemplate: '/carts',
            validationContext: ['groups' => ['Default', 'Create']],
            CQRSCommand: CreateEmptyCustomerCartCommand::class,
            CQRSQuery: GetCartForOrderCreation::class,
            scopes: ['cart_write'],
        ),
        new CQRSDelete(
            uriTemplate: '/carts/{cartId}',
            requirements: ['cartId' => '\d+'],
            CQRSCommand: DeleteCartCommand::class,
            scopes: ['cart_write'],
        ),
    ],
    normalizationContext: ['skip_null_values' => false],
    exceptionToStatus: [
        CartConstraintException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        CartNotFoundException::class => Response::HTTP_NOT_FOUND,
        CannotDeleteCartException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        CannotDeleteOrderedCartException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
    ],
)]
class Cart
{
    #[ApiProperty(identifier: true)]
    public int $cartId;

    #[Assert\NotBlank(groups: ['Create'])]
    #[Assert\Positive(groups: ['Create'])]
    #[ApiProperty(openapiContext: ['type' => 'integer', 'example' => 1])]
    public ?int $customerId = null;

    #[ApiProperty(openapiContext: ['type' => 'integer', 'example' => 1])]
    public int $currencyId;

    #[ApiProperty(openapiContext: ['type' => 'integer', 'example' => 1])]
    public int $langId;

    #[ApiProperty(openapiContext: [
        'type' => 'array',
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

    #[ApiProperty(openapiContext: [
        'type' => 'array',
        'items' => [
            'type' => 'object',
            'properties' => [
                'cartRuleId' => ['type' => 'integer'],
                'name' => ['type' => 'string'],
                'description' => ['type' => 'string'],
                'value' => ['type' => 'string'],
            ],
        ],
    ])]
    public array $cartRules;

    #[ApiProperty(openapiContext: [
        'type' => 'array',
        'items' => [
            'type' => 'object',
            'properties' => [
                'addressId' => ['type' => 'integer'],
                'alias' => ['type' => 'string'],
                'formattedAddress' => ['type' => 'string'],
                'delivery' => ['type' => 'boolean'],
                'invoice' => ['type' => 'boolean'],
            ],
        ],
    ])]
    public array $addresses;

    #[ApiProperty(openapiContext: [
        'nullable' => true,
        'type' => 'object',
        'properties' => [
            'shippingPrice' => ['type' => 'string'],
            'freeShipping' => ['type' => 'boolean'],
            'selectedCarrierId' => ['type' => 'integer', 'nullable' => true],
            'recycledPackaging' => ['type' => 'boolean'],
            'gift' => ['type' => 'boolean'],
            'giftMessage' => ['type' => 'string'],
            'virtual' => ['type' => 'boolean'],
            'deliveryOptions' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'carrierId' => ['type' => 'integer'],
                        'carrierName' => ['type' => 'string'],
                        'carrierDelay' => ['type' => 'string'],
                    ],
                ],
            ],
        ],
    ])]
    public ?array $shipping;

    #[ApiProperty(openapiContext: [
        'type' => 'object',
        'properties' => [
            'totalProductsPrice' => ['type' => 'string'],
            'totalDiscount' => ['type' => 'string'],
            'totalShippingPrice' => ['type' => 'string'],
            'totalShippingWithoutTaxes' => ['type' => 'string'],
            'totalTaxes' => ['type' => 'string'],
            'totalPriceWithTaxes' => ['type' => 'string'],
            'totalPriceWithoutTaxes' => ['type' => 'string'],
            'orderMessage' => ['type' => 'string'],
            'processOrderLink' => ['type' => 'string'],
        ],
    ])]
    public array $summary;
}
