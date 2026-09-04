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
use PrestaShop\PrestaShop\Core\Domain\Cart\Command\AddCartRuleToCartCommand;
use PrestaShop\PrestaShop\Core\Domain\Cart\Command\CreateEmptyCustomerCartCommand;
use PrestaShop\PrestaShop\Core\Domain\Cart\Command\DeleteCartCommand;
use PrestaShop\PrestaShop\Core\Domain\Cart\Command\RemoveCartRuleFromCartCommand;
use PrestaShop\PrestaShop\Core\Domain\Cart\Command\SendCartToCustomerCommand;
use PrestaShop\PrestaShop\Core\Domain\Cart\Command\UpdateCartAddressesCommand;
use PrestaShop\PrestaShop\Core\Domain\Cart\Command\UpdateCartCarrierCommand;
use PrestaShop\PrestaShop\Core\Domain\Cart\Command\UpdateCartCurrencyCommand;
use PrestaShop\PrestaShop\Core\Domain\Cart\Command\UpdateCartDeliverySettingsCommand;
use PrestaShop\PrestaShop\Core\Domain\Cart\Command\UpdateCartLanguageCommand;
use PrestaShop\PrestaShop\Core\Domain\Cart\Exception\CartException;
use PrestaShop\PrestaShop\Core\Domain\Cart\Exception\CartNotFoundException;
use PrestaShop\PrestaShop\Core\Domain\Cart\Query\GetCartForOrderCreation;
use PrestaShop\PrestaShop\Core\Domain\CartRule\Exception\CartRuleValidityException;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSCreate;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSDelete;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSGet;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSPartialUpdate;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSUpdate;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    operations: [
        new CQRSGet(
            uriTemplate: '/carts/{cartId}',
            requirements: ['cartId' => '\d+'],
            CQRSQuery: GetCartForOrderCreation::class,
            CQRSQueryMapping: self::QUERY_MAPPING,
            scopes: ['cart_read'],
        ),
        new CQRSCreate(
            uriTemplate: '/carts',
            validationContext: ['groups' => ['Default', 'Create']],
            CQRSCommand: CreateEmptyCustomerCartCommand::class,
            CQRSQuery: GetCartForOrderCreation::class,
            CQRSQueryMapping: self::QUERY_MAPPING,
            scopes: ['cart_write'],
        ),
        new CQRSDelete(
            uriTemplate: '/carts/{cartId}',
            requirements: ['cartId' => '\d+'],
            CQRSCommand: DeleteCartCommand::class,
            scopes: ['cart_write'],
        ),
        new CQRSPartialUpdate(
            uriTemplate: '/carts/{cartId}/addresses',
            requirements: ['cartId' => '\d+'],
            validationContext: ['groups' => ['Default', 'UpdateAddresses']],
            CQRSCommand: UpdateCartAddressesCommand::class,
            CQRSQuery: GetCartForOrderCreation::class,
            CQRSQueryMapping: self::QUERY_MAPPING,
            scopes: ['cart_write'],
            CQRSCommandMapping: self::COMMAND_MAPPING_ADDRESSES,
        ),
        new CQRSPartialUpdate(
            uriTemplate: '/carts/{cartId}/carrier',
            requirements: ['cartId' => '\d+'],
            validationContext: ['groups' => ['Default', 'UpdateCarrier']],
            CQRSCommand: UpdateCartCarrierCommand::class,
            CQRSQuery: GetCartForOrderCreation::class,
            CQRSQueryMapping: self::QUERY_MAPPING,
            scopes: ['cart_write'],
            CQRSCommandMapping: self::COMMAND_MAPPING_CARRIER,
        ),
        new CQRSPartialUpdate(
            uriTemplate: '/carts/{cartId}/currency',
            requirements: ['cartId' => '\d+'],
            validationContext: ['groups' => ['Default', 'UpdateCurrency']],
            CQRSCommand: UpdateCartCurrencyCommand::class,
            CQRSQuery: GetCartForOrderCreation::class,
            CQRSQueryMapping: self::QUERY_MAPPING,
            scopes: ['cart_write'],
            CQRSCommandMapping: self::COMMAND_MAPPING_CURRENCY,
        ),
        new CQRSPartialUpdate(
            uriTemplate: '/carts/{cartId}/language',
            requirements: ['cartId' => '\d+'],
            validationContext: ['groups' => ['Default', 'UpdateLanguage']],
            CQRSCommand: UpdateCartLanguageCommand::class,
            CQRSQuery: GetCartForOrderCreation::class,
            CQRSQueryMapping: self::QUERY_MAPPING,
            scopes: ['cart_write'],
            CQRSCommandMapping: self::COMMAND_MAPPING_LANGUAGE,
        ),
        new CQRSPartialUpdate(
            uriTemplate: '/carts/{cartId}/delivery-settings',
            requirements: ['cartId' => '\d+'],
            validationContext: ['groups' => ['Default', 'UpdateDeliverySettings']],
            CQRSCommand: UpdateCartDeliverySettingsCommand::class,
            CQRSQuery: GetCartForOrderCreation::class,
            CQRSQueryMapping: self::QUERY_MAPPING,
            scopes: ['cart_write'],
            CQRSCommandMapping: self::COMMAND_MAPPING_DELIVERY_SETTINGS,
        ),
        new CQRSCreate(
            uriTemplate: '/carts/{cartId}/cart-rules',
            requirements: ['cartId' => '\d+'],
            validationContext: ['groups' => ['Default', 'AddCartRule']],
            CQRSCommand: AddCartRuleToCartCommand::class,
            CQRSQuery: GetCartForOrderCreation::class,
            CQRSQueryMapping: self::QUERY_MAPPING,
            scopes: ['cart_write'],
        ),
        new CQRSDelete(
            uriTemplate: '/carts/{cartId}/cart-rules',
            requirements: ['cartId' => '\d+'],
            // ApiPlatform skips validation on DELETE unless it is explicitly enabled.
            validationContext: ['groups' => ['Default', 'RemoveCartRule']],
            validate: true,
            // CQRSDelete answers 204 and exposes no CQRSQuery parameter, hence the overrides.
            status: Response::HTTP_OK,
            CQRSCommand: RemoveCartRuleFromCartCommand::class,
            scopes: ['cart_write'],
            output: Cart::class,
            extraProperties: [
                'CQRSQuery' => GetCartForOrderCreation::class,
                'CQRSQueryMapping' => self::QUERY_MAPPING,
            ],
        ),
        new CQRSUpdate(
            uriTemplate: '/carts/{cartId}/emails',
            requirements: ['cartId' => '\d+'],
            allowEmptyBody: true,
            CQRSCommand: SendCartToCustomerCommand::class,
            CQRSQuery: GetCartForOrderCreation::class,
            CQRSQueryMapping: self::QUERY_MAPPING,
            scopes: ['cart_write'],
        ),
    ],
    normalizationContext: ['skip_null_values' => false],
    // Order matters, the first match wins: CartNotFoundException extends CartException.
    exceptionToStatus: [
        CartNotFoundException::class => Response::HTTP_NOT_FOUND,
        CartException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        CartRuleValidityException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
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

    #[Assert\NotBlank(groups: ['UpdateCurrency'])]
    #[Assert\Positive(groups: ['UpdateCurrency'])]
    #[ApiProperty(openapiContext: ['type' => 'integer', 'example' => 1])]
    public int $currencyId;

    #[Assert\NotBlank(groups: ['UpdateLanguage'])]
    #[Assert\Positive(groups: ['UpdateLanguage'])]
    #[ApiProperty(openapiContext: ['type' => 'integer', 'example' => 1])]
    public int $languageId;

    #[ApiProperty(openapiContext: [
        'type' => 'array',
        'description' => 'Read-only. Products are managed through the /carts/{cartId}/products endpoints.',
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
        'type' => 'object',
        'description' => 'Read-only, keyed by cart rule ID, empty array when the cart has none. Cart rules are managed through the /carts/{cartId}/cart-rules endpoints.',
        'additionalProperties' => [
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
        'description' => 'Read-only. Addresses are managed through PATCH /carts/{cartId}/addresses.',
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

    // Also the body of PATCH /delivery-settings, with the same structure as in read: only the four keys below are
    // mapped onto the command, the other ones can be sent back untouched and are ignored.
    #[Assert\NotNull(groups: ['UpdateDeliverySettings'])]
    #[Assert\Collection(
        fields: ['freeShipping' => new Assert\Required(groups: ['UpdateDeliverySettings'])],
        groups: ['UpdateDeliverySettings'],
        allowExtraFields: true,
    )]
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
        'description' => 'Read-only. Recomputed by the core on every write.',
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

    // Write-only fields below, body of PATCH /addresses
    #[Assert\NotBlank(groups: ['UpdateAddresses'])]
    #[Assert\Positive(groups: ['UpdateAddresses'])]
    #[ApiProperty(readable: false, openapiContext: ['type' => 'integer', 'example' => 1])]
    public int $deliveryAddressId;

    #[Assert\NotBlank(groups: ['UpdateAddresses'])]
    #[Assert\Positive(groups: ['UpdateAddresses'])]
    #[ApiProperty(readable: false, openapiContext: ['type' => 'integer', 'example' => 1])]
    public int $invoiceAddressId;

    // Body of PATCH /carrier, the selected carrier is read in shipping.selectedCarrierId
    #[Assert\NotBlank(groups: ['UpdateCarrier'])]
    #[Assert\Positive(groups: ['UpdateCarrier'])]
    #[ApiProperty(readable: false, openapiContext: ['type' => 'integer', 'example' => 2])]
    public int $carrierId;

    // Body of POST and DELETE /cart-rules, the applied cart rules are read in cartRules
    #[Assert\NotBlank(groups: ['AddCartRule', 'RemoveCartRule'])]
    #[Assert\Positive(groups: ['AddCartRule', 'RemoveCartRule'])]
    #[ApiProperty(readable: false, openapiContext: ['type' => 'integer', 'example' => 1])]
    public int $cartRuleId;

    public const QUERY_MAPPING = [
        '[langId]' => '[languageId]',
    ];

    public const COMMAND_MAPPING_ADDRESSES = [
        '[deliveryAddressId]' => '[newDeliveryAddressId]',
        '[invoiceAddressId]' => '[newInvoiceAddressId]',
    ];

    public const COMMAND_MAPPING_CARRIER = [
        '[carrierId]' => '[newCarrierId]',
    ];

    public const COMMAND_MAPPING_CURRENCY = [
        '[currencyId]' => '[newCurrencyId]',
    ];

    public const COMMAND_MAPPING_DELIVERY_SETTINGS = [
        '[shipping][freeShipping]' => '[allowFreeShipping]',
        '[shipping][gift]' => '[isAGift]',
        '[shipping][giftMessage]' => '[giftMessage]',
        '[shipping][recycledPackaging]' => '[useRecycledPackaging]',
    ];

    public const COMMAND_MAPPING_LANGUAGE = [
        '[languageId]' => '[newLanguageId]',
    ];
}
