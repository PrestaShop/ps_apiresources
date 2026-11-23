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

namespace PrestaShop\Module\APIResources\ApiPlatform\Resources\Order;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use PrestaShop\Module\APIResources\ApiPlatform\Resources\Order\Dto\OrderCarrier;
use PrestaShop\Module\APIResources\ApiPlatform\Resources\Order\Dto\OrderDiscount;
use PrestaShop\Module\APIResources\ApiPlatform\Resources\Order\Dto\OrderDocument;
use PrestaShop\Module\APIResources\ApiPlatform\Resources\Order\Dto\OrderHistoryEntry;
use PrestaShop\Module\APIResources\ApiPlatform\Resources\Order\Dto\OrderPayment;
use PrestaShop\Module\APIResources\ApiPlatform\Resources\Order\Dto\OrderPrices;
use PrestaShop\Module\APIResources\ApiPlatform\Resources\Order\Dto\OrderProduct;
use PrestaShop\PrestaShop\Core\ConstraintValidator\Constraints\CleanHtml;
use PrestaShop\PrestaShop\Core\Domain\CartRule\Exception\InvalidCartRuleDiscountValueException;
use PrestaShop\PrestaShop\Core\Domain\Currency\Exception\CurrencyConstraintException;
use PrestaShop\PrestaShop\Core\Domain\Order\Command\AddCartRuleToOrderCommand;
use PrestaShop\PrestaShop\Core\Domain\Order\Command\ChangeOrderCurrencyCommand;
use PrestaShop\PrestaShop\Core\Domain\Order\Command\ChangeOrderDeliveryAddressCommand;
use PrestaShop\PrestaShop\Core\Domain\Order\Command\ChangeOrderInvoiceAddressCommand;
use PrestaShop\PrestaShop\Core\Domain\Order\Command\DeleteCartRuleFromOrderCommand;
use PrestaShop\PrestaShop\Core\Domain\Order\Command\ResendOrderEmailCommand;
use PrestaShop\PrestaShop\Core\Domain\Order\Command\SetInternalOrderNoteCommand;
use PrestaShop\PrestaShop\Core\Domain\Order\Command\UpdateOrderShippingDetailsCommand;
use PrestaShop\PrestaShop\Core\Domain\Order\Command\UpdateOrderStatusCommand;
use PrestaShop\PrestaShop\Core\Domain\Order\Exception\OrderException;
use PrestaShop\PrestaShop\Core\Domain\Order\Exception\OrderNotFoundException;
use PrestaShop\PrestaShop\Core\Domain\Order\Invoice\Command\GenerateInvoiceCommand;
use PrestaShop\PrestaShop\Core\Domain\Order\OrderDiscountType;
use PrestaShop\PrestaShop\Core\Domain\Order\Payment\Command\AddPaymentCommand;
use PrestaShop\PrestaShop\Core\Domain\Order\Product\Command\DeleteProductFromOrderCommand;
use PrestaShop\PrestaShop\Core\Domain\Order\Product\Command\UpdateProductInOrderCommand;
use PrestaShop\PrestaShop\Core\Domain\Order\Query\GetOrderForViewing;
use PrestaShop\PrestaShop\Core\Domain\Product\Exception\ProductOutOfStockException;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSCreate;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSDelete;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSGet;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSPartialUpdate;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * The order as displayed on the order page of the back office, and the actions of that page: every write on
 * /orders/{orderId}/... answers the order read back by GetOrderForViewing, so the caller sees its effect without a
 * follow-up GET.
 *
 * The read model is GetOrderForViewing; left out on purpose: the values only formatted for display (prices,
 * addresses, tax method label), the customer details (GET /customers/{customerId}/details), the order messages
 * (/order-messages), the merchandise returns (/order-returns), the sources, the linked orders and the shipments.
 *
 * Every operation reading the order requires PrestaShop 9.2.2, which casts the booleans and reads the item types of
 * the DTO lists of a query result (PrestaShop#43369).
 */
#[ApiResource(
    operations: [
        new CQRSGet(
            uriTemplate: '/orders/{orderId}',
            requirements: ['orderId' => '\d+'],
            CQRSQuery: GetOrderForViewing::class,
            scopes: ['order_read'],
            CQRSQueryMapping: self::QUERY_MAPPING,
            ApiResourceMapping: self::API_RESOURCE_MAPPING,
            extraProperties: [
                // The booleans and the DTO lists of the query result are handled from 9.2.2
                'minVersion' => '9.2.2',
            ],
            openapi: new OpenApiOperation(
                summary: 'Get an order.',
                description: 'Returns the order with its products, discounts, status history, carriers, payments and '
                    . 'documents. The amounts of prices are tax included or excluded depending on taxIncluded, the '
                    . 'amounts of the payments and documents are formatted for display.',
            ),
        ),
        new CQRSPartialUpdate(
            uriTemplate: '/orders/{orderId}/set-internal-note',
            requirements: ['orderId' => '\d+'],
            // No read, so that an omitted value reaches the NotNull constraint
            read: false,
            CQRSCommand: SetInternalOrderNoteCommand::class,
            CQRSQuery: GetOrderForViewing::class,
            scopes: ['order_write'],
            CQRSQueryMapping: self::QUERY_MAPPING,
            ApiResourceMapping: self::API_RESOURCE_MAPPING,
            extraProperties: [
                // Returns the order
                'minVersion' => '9.2.2',
            ],
            validationContext: ['groups' => ['Default', 'SetInternalNote']],
            openapi: new OpenApiOperation(
                summary: 'Set the internal note of an order.',
                description: 'Replaces the note visible only in the back office, and returns the order.',
            ),
        ),
        new CQRSPartialUpdate(
            uriTemplate: '/orders/{orderId}/set-invoice-address',
            requirements: ['orderId' => '\d+'],
            read: false,
            CQRSCommand: ChangeOrderInvoiceAddressCommand::class,
            CQRSQuery: GetOrderForViewing::class,
            scopes: ['order_write'],
            CQRSQueryMapping: self::QUERY_MAPPING,
            CQRSCommandMapping: [
                '[invoiceAddressId]' => '[newInvoiceAddressId]',
            ],
            ApiResourceMapping: self::API_RESOURCE_MAPPING,
            extraProperties: [
                'minVersion' => '9.2.2',
            ],
            validationContext: ['groups' => ['Default', 'SetInvoiceAddress']],
            openapi: new OpenApiOperation(
                summary: 'Set the invoice address of an order.',
                description: 'Replaces the invoice address of the order by another address, and returns the order. An '
                    . 'unknown address answers 422: the core reports it as an invalid address.',
            ),
        ),
        new CQRSPartialUpdate(
            uriTemplate: '/orders/{orderId}/set-delivery-address',
            requirements: ['orderId' => '\d+'],
            read: false,
            CQRSCommand: ChangeOrderDeliveryAddressCommand::class,
            CQRSQuery: GetOrderForViewing::class,
            scopes: ['order_write'],
            CQRSQueryMapping: self::QUERY_MAPPING,
            CQRSCommandMapping: [
                '[deliveryAddressId]' => '[newDeliveryAddressId]',
            ],
            ApiResourceMapping: self::API_RESOURCE_MAPPING,
            extraProperties: [
                'minVersion' => '9.2.2',
            ],
            validationContext: ['groups' => ['Default', 'SetDeliveryAddress']],
            openapi: new OpenApiOperation(
                summary: 'Set the delivery address of an order.',
                description: 'Replaces the delivery address of the order by another address, the order amounts are '
                    . 'computed again for it, and returns the order. An unknown address answers 422: the core reports '
                    . 'it as an invalid address.',
            ),
        ),
        new CQRSPartialUpdate(
            uriTemplate: '/orders/{orderId}/set-currency',
            requirements: ['orderId' => '\d+'],
            read: false,
            CQRSCommand: ChangeOrderCurrencyCommand::class,
            CQRSQuery: GetOrderForViewing::class,
            scopes: ['order_write'],
            CQRSQueryMapping: self::QUERY_MAPPING,
            CQRSCommandMapping: [
                '[currencyId]' => '[newCurrencyId]',
            ],
            ApiResourceMapping: self::API_RESOURCE_MAPPING,
            extraProperties: [
                'minVersion' => '9.2.2',
            ],
            validationContext: ['groups' => ['Default', 'SetCurrency']],
            openapi: new OpenApiOperation(
                summary: 'Set the currency of an order.',
                description: 'Converts the amounts of the order into another currency, and returns the order. Only an '
                    . 'order that is not validated yet can change its currency: a validated order, the current '
                    . 'currency or an unknown one answer 422.',
            ),
        ),
        new CQRSPartialUpdate(
            uriTemplate: '/orders/{orderId}/update-status',
            requirements: ['orderId' => '\d+'],
            read: false,
            CQRSCommand: UpdateOrderStatusCommand::class,
            CQRSQuery: GetOrderForViewing::class,
            scopes: ['order_write'],
            CQRSQueryMapping: self::QUERY_MAPPING,
            CQRSCommandMapping: [
                '[orderStateId]' => '[newOrderStatusId]',
            ],
            ApiResourceMapping: self::API_RESOURCE_MAPPING,
            extraProperties: [
                'minVersion' => '9.2.2',
            ],
            validationContext: ['groups' => ['Default', 'UpdateStatus']],
            openapi: new OpenApiOperation(
                summary: 'Change the status of an order.',
                description: 'Adds a status to the history of the order, with the side effects of that status (e-mail '
                    . 'to the customer, invoice, payment, stock), and returns the order. The current status or an '
                    . 'unknown one answer 422.',
            ),
        ),
        new CQRSCreate(
            uriTemplate: '/orders/{orderId}/resend-email',
            requirements: ['orderId' => '\d+'],
            output: false,
            CQRSCommand: ResendOrderEmailCommand::class,
            scopes: ['order_write'],
            CQRSCommandMapping: [
                '[orderStateId]' => '[orderStatusId]',
            ],
            validationContext: ['groups' => ['Default', 'ResendEmail']],
            openapi: new OpenApiOperation(
                summary: 'Send again the e-mail of an order status.',
                description: 'Sends again to the customer the e-mail of an entry of the status history (orderHistoryId '
                    . 'and its orderStateId, read in history). Nothing changes on the order: the response is a 204.',
            ),
        ),
        new CQRSPartialUpdate(
            uriTemplate: '/orders/{orderId}/carriers/{orderCarrierId}',
            requirements: ['orderId' => '\d+', 'orderCarrierId' => '\d+'],
            read: false,
            CQRSCommand: UpdateOrderShippingDetailsCommand::class,
            CQRSQuery: GetOrderForViewing::class,
            scopes: ['order_write'],
            CQRSQueryMapping: self::QUERY_MAPPING,
            CQRSCommandMapping: [
                '[orderCarrierId]' => '[currentOrderCarrierId]',
                '[carrierId]' => '[newCarrierId]',
            ],
            ApiResourceMapping: self::API_RESOURCE_MAPPING,
            extraProperties: [
                'minVersion' => '9.2.2',
            ],
            validationContext: ['groups' => ['Default', 'UpdateCarrier']],
            openapi: new OpenApiOperation(
                summary: 'Update a carrier of an order.',
                description: 'Sets the carrier and the tracking number of a carrier of the order (orderCarrierId, read '
                    . 'in carriers), and returns the order. A new tracking number sends the in transit e-mail to the '
                    . 'customer; an invalid tracking number answers 422.',
            ),
        ),
        new CQRSPartialUpdate(
            uriTemplate: '/orders/{orderId}/products/{orderDetailId}',
            requirements: ['orderId' => '\d+', 'orderDetailId' => '\d+'],
            read: false,
            CQRSCommand: UpdateProductInOrderCommand::class,
            CQRSQuery: GetOrderForViewing::class,
            scopes: ['order_write'],
            CQRSQueryMapping: self::QUERY_MAPPING,
            CQRSCommandMapping: [
                '[unitPriceTaxIncluded]' => '[priceTaxIncluded]',
                '[unitPriceTaxExcluded]' => '[priceTaxExcluded]',
            ],
            ApiResourceMapping: self::API_RESOURCE_MAPPING,
            extraProperties: [
                'minVersion' => '9.2.2',
            ],
            validationContext: ['groups' => ['Default', 'UpdateProduct']],
            openapi: new OpenApiOperation(
                summary: 'Update a product of an order.',
                description: 'Sets the unit prices and the quantity of a product line of the order (orderDetailId, '
                    . 'read in products), the order amounts and the stock are updated, and returns the order. Every '
                    . 'field is required. A delivered order cannot be edited (422).',
            ),
        ),
        new CQRSDelete(
            uriTemplate: '/orders/{orderId}/products/{orderDetailId}',
            requirements: ['orderId' => '\d+', 'orderDetailId' => '\d+'],
            CQRSCommand: DeleteProductFromOrderCommand::class,
            scopes: ['order_write'],
            ApiResourceMapping: self::API_RESOURCE_MAPPING,
            extraProperties: [
                // Returns the order, a CQRSDelete answers the resource read back from 9.2.2
                'CQRSQuery' => GetOrderForViewing::class,
                'CQRSQueryMapping' => self::QUERY_MAPPING,
                'minVersion' => '9.2.2',
            ],
            openapi: new OpenApiOperation(
                summary: 'Remove a product from an order.',
                description: 'Removes a product line of the order (orderDetailId, read in products), the order '
                    . 'amounts and the stock are updated, and returns the order. A delivered order cannot be edited '
                    . '(422).',
            ),
        ),
        new CQRSCreate(
            uriTemplate: '/orders/{orderId}/discounts',
            requirements: ['orderId' => '\d+'],
            CQRSCommand: AddCartRuleToOrderCommand::class,
            CQRSQuery: GetOrderForViewing::class,
            scopes: ['order_write'],
            CQRSQueryMapping: self::QUERY_MAPPING,
            CQRSCommandMapping: [
                '[discountName]' => '[cartRuleName]',
                '[discountType]' => '[cartRuleType]',
                '[discountValue]' => '[value]',
            ],
            ApiResourceMapping: self::API_RESOURCE_MAPPING,
            extraProperties: [
                'minVersion' => '9.2.2',
                'defaultValues' => [
                    // AddCartRuleToOrderCommand has no default for it, free shipping has no value
                    'discountValue' => null,
                ],
            ],
            validationContext: ['groups' => ['Default', 'AddDiscount']],
            openapi: new OpenApiOperation(
                summary: 'Add a discount to an order.',
                description: 'Creates a cart rule for the order and applies it, the order amounts are updated, and '
                    . 'returns the order (the discount is read back in discounts). A percentage above 100 or an '
                    . 'amount above the order total answer 422.',
            ),
        ),
        new CQRSDelete(
            uriTemplate: '/orders/{orderId}/discounts/{orderCartRuleId}',
            requirements: ['orderId' => '\d+', 'orderCartRuleId' => '\d+'],
            CQRSCommand: DeleteCartRuleFromOrderCommand::class,
            scopes: ['order_write'],
            ApiResourceMapping: self::API_RESOURCE_MAPPING,
            extraProperties: [
                'CQRSQuery' => GetOrderForViewing::class,
                'CQRSQueryMapping' => self::QUERY_MAPPING,
                'minVersion' => '9.2.2',
            ],
            openapi: new OpenApiOperation(
                summary: 'Remove a discount from an order.',
                description: 'Removes a discount of the order (orderCartRuleId, read in discounts), the order amounts '
                    . 'are updated, and returns the order. A discount that is not part of the order answers 422.',
            ),
        ),
        new CQRSCreate(
            uriTemplate: '/orders/{orderId}/payments',
            requirements: ['orderId' => '\d+'],
            CQRSCommand: AddPaymentCommand::class,
            CQRSQuery: GetOrderForViewing::class,
            scopes: ['order_write'],
            CQRSQueryMapping: self::QUERY_MAPPING,
            ApiResourceMapping: self::API_RESOURCE_MAPPING,
            extraProperties: [
                'minVersion' => '9.2.2',
                'defaultValues' => [
                    'employeeId' => 0,
                ],
            ],
            validationContext: ['groups' => ['Default', 'AddPayment']],
            openapi: new OpenApiOperation(
                summary: 'Add a payment to an order.',
                description: 'Records a payment on the order, and returns the order (the payment is read back in '
                    . 'payments). An unknown currency or invoice answers 422.',
            ),
        ),
        new CQRSCreate(
            uriTemplate: '/orders/{orderId}/invoices',
            requirements: ['orderId' => '\d+'],
            allowEmptyBody: true,
            CQRSCommand: GenerateInvoiceCommand::class,
            CQRSQuery: GetOrderForViewing::class,
            scopes: ['order_write'],
            CQRSQueryMapping: self::QUERY_MAPPING,
            ApiResourceMapping: self::API_RESOURCE_MAPPING,
            extraProperties: [
                'minVersion' => '9.2.2',
            ],
            openapi: new OpenApiOperation(
                summary: 'Generate the invoice of an order.',
                description: 'Creates the invoice of an order that has none, and returns the order (the invoice is '
                    . 'read back in documents). An order that already has an invoice, or a shop where the invoices '
                    . 'are disabled, answer 422.',
            ),
        ),
    ],
    normalizationContext: ['skip_null_values' => false],
    exceptionToStatus: [
        OrderNotFoundException::class => Response::HTTP_NOT_FOUND,
        OrderException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        InvalidCartRuleDiscountValueException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        ProductOutOfStockException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        CurrencyConstraintException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
    ],
)]
class Order
{
    #[ApiProperty(identifier: true)]
    public int $orderId;

    public string $reference;

    public ?int $customerId = null;

    public int $cartId;

    public int $shopId;

    // Input of PATCH /set-currency as well
    #[Assert\NotNull(groups: ['SetCurrency'])]
    #[Assert\Positive]
    public int $currencyId;

    // Input of PATCH /carriers/{orderCarrierId} as well: the carrier to set on that order carrier
    #[Assert\NotNull(groups: ['UpdateCarrier'])]
    #[Assert\Positive]
    public int $carrierId;

    public string $carrierName;

    // Input of PATCH /update-status and POST /resend-email as well
    #[ApiProperty(openapiContext: ['description' => 'Current status of the order (order state).'])]
    #[Assert\NotNull(groups: ['UpdateStatus', 'ResendEmail'])]
    #[Assert\Positive]
    public int $orderStateId;

    public bool $valid;

    public bool $paid;

    public bool $shipped;

    public bool $delivered;

    public bool $virtual;

    public bool $taxIncluded;

    public bool $refundable;

    #[ApiProperty(openapiContext: ['description' => 'Name of the payment method of the order.'])]
    public string $payment;

    public string $paymentModuleName;

    // Input of PATCH /set-internal-note as well. Nullable so that an explicit null reaches the NotNull constraint
    #[ApiProperty(openapiContext: ['description' => 'Note about the order, visible only in the back office.'])]
    #[Assert\NotNull(groups: ['SetInternalNote'])]
    #[CleanHtml]
    public ?string $internalNote;

    // Input of PATCH /set-invoice-address as well
    #[Assert\NotNull(groups: ['SetInvoiceAddress'])]
    #[Assert\Positive]
    public int $invoiceAddressId;

    // Input of PATCH /set-delivery-address as well
    #[Assert\NotNull(groups: ['SetDeliveryAddress'])]
    #[Assert\Positive]
    public int $deliveryAddressId;

    public \DateTimeImmutable $createdAt;

    public OrderPrices $prices;

    /**
     * @var OrderProduct[]
     */
    public array $products;

    /**
     * @var OrderDiscount[]
     */
    public array $discounts;

    /**
     * @var OrderHistoryEntry[]
     */
    public array $history;

    /**
     * @var OrderCarrier[]
     */
    public array $carriers;

    /**
     * @var OrderPayment[]
     */
    public array $payments;

    /**
     * @var OrderDocument[]
     */
    public array $documents;

    // Path variable of PATCH /carriers/{orderCarrierId}, typed so that it reaches the command as an integer
    #[ApiProperty(readable: false, writable: false)]
    public int $orderCarrierId;

    // Path variable of PATCH and DELETE /products/{orderDetailId}
    #[ApiProperty(readable: false, writable: false)]
    public int $orderDetailId;

    // Path variable of DELETE /discounts/{orderCartRuleId}
    #[ApiProperty(readable: false, writable: false)]
    public int $orderCartRuleId;

    // Input of POST /resend-email: the entry of history whose e-mail is sent again
    #[ApiProperty(readable: false)]
    #[Assert\NotNull(groups: ['ResendEmail'])]
    #[Assert\Positive]
    public int $orderHistoryId;

    // Input of PATCH /carriers/{orderCarrierId}, read back in carriers. No default value: an initialized write-only
    // property would be returned by the write operations
    #[ApiProperty(readable: false)]
    public ?string $trackingNumber;

    // Input of PATCH /products/{orderDetailId}, read back in products
    #[ApiProperty(readable: false)]
    #[Assert\NotNull(groups: ['UpdateProduct'])]
    #[Assert\Positive]
    public int $quantity;

    // Input of PATCH /products/{orderDetailId}, read back in products. A string: UpdateProductInOrderCommand takes the
    // prices as strings, and a DecimalNumber is normalized as a float that its constructor refuses
    #[ApiProperty(readable: false, openapiContext: ['description' => 'Unit price tax included, a numeric string.', 'example' => '11.90'])]
    #[Assert\NotNull(groups: ['UpdateProduct'])]
    #[Assert\Type('numeric')]
    public string $unitPriceTaxIncluded;

    // Input of PATCH /products/{orderDetailId}, read back in products
    #[ApiProperty(readable: false, openapiContext: ['description' => 'Unit price tax excluded, a numeric string.', 'example' => '11.90'])]
    #[Assert\NotNull(groups: ['UpdateProduct'])]
    #[Assert\Type('numeric')]
    public string $unitPriceTaxExcluded;

    // Input of PATCH /products/{orderDetailId}, POST /discounts and POST /payments: the invoice the line, the discount
    // or the payment is attached to
    #[ApiProperty(readable: false)]
    #[Assert\Positive]
    public ?int $orderInvoiceId;

    // Input of POST /discounts, read back in discounts
    #[ApiProperty(readable: false)]
    #[Assert\NotBlank(groups: ['AddDiscount'])]
    public string $discountName;

    // Input of POST /discounts
    #[ApiProperty(readable: false, openapiContext: ['description' => 'Type of the discount.'])]
    #[Assert\NotBlank(groups: ['AddDiscount'])]
    #[Assert\Choice(choices: [
        OrderDiscountType::DISCOUNT_PERCENT,
        OrderDiscountType::DISCOUNT_AMOUNT,
        OrderDiscountType::FREE_SHIPPING,
    ])]
    public string $discountType;

    // Input of POST /discounts: a percentage or an amount depending on discountType, required except for free shipping.
    // A string, like the value AddCartRuleToOrderCommand takes; null by default (defaultValues of the operation)
    #[ApiProperty(readable: false, openapiContext: ['description' => 'Percentage or amount of the discount depending on discountType, a numeric string, required except for free_shipping.', 'example' => '5.00'])]
    #[Assert\Type('numeric')]
    public ?string $discountValue;

    // Input of POST /payments, read back in payments
    #[ApiProperty(readable: false)]
    #[Assert\NotNull(groups: ['AddPayment'])]
    public \DateTimeImmutable $paymentDate;

    // Input of POST /payments, read back in payments
    #[ApiProperty(readable: false)]
    #[Assert\NotBlank(groups: ['AddPayment'])]
    public string $paymentMethod;

    // Input of POST /payments, read back (formatted) in payments. A string, like the amount AddPaymentCommand takes
    #[ApiProperty(readable: false, openapiContext: ['description' => 'Amount of the payment, a numeric string.', 'example' => '50.50'])]
    #[Assert\NotNull(groups: ['AddPayment'])]
    #[Assert\Type('numeric')]
    public string $paymentAmount;

    // Input of POST /payments
    #[ApiProperty(readable: false)]
    #[Assert\NotNull(groups: ['AddPayment'])]
    #[Assert\Positive]
    public int $paymentCurrencyId;

    // Input of POST /payments, read back in payments
    #[ApiProperty(readable: false)]
    public ?string $transactionId;

    // Input of POST /payments: the employee recorded on the payment, none (0) by default
    #[ApiProperty(readable: false, openapiContext: ['description' => 'Employee recorded on the payment, none (0) by default.'])]
    #[Assert\PositiveOrZero]
    public int $employeeId;

    public const QUERY_MAPPING = [
        '[id]' => '[orderId]',
        '[customer][id]' => '[customerId]',
        '[history][currentOrderStatusId]' => '[orderStateId]',
        '[beenPaid]' => '[paid]',
        '[paymentName]' => '[payment]',
        '[paymentModule]' => '[paymentModuleName]',
        '[note]' => '[internalNote]',
        '[invoiceAddress][addressId]' => '[invoiceAddressId]',
        '[shippingAddress][addressId]' => '[deliveryAddressId]',
        '[prices][productsPriceRaw]' => '[prices][productsPrice]',
        '[prices][discountsAmountRaw]' => '[prices][discountsAmount]',
        '[prices][wrappingPriceRaw]' => '[prices][wrappingPrice]',
        '[prices][shippingPriceRaw]' => '[prices][shippingPrice]',
        '[prices][shippingRefundableAmountRaw]' => '[prices][shippingRefundableAmount]',
        '[prices][taxesAmountRaw]' => '[prices][taxesAmount]',
        '[prices][totalAmountRaw]' => '[prices][totalAmount]',
        // The rows are renamed in place, API_RESOURCE_MAPPING then removes the level of their wrapper
        '[products][products][@index][id]' => '[products][products][@index][productId]',
        '[products][products][@index][unitPriceTaxExclRaw]' => '[products][products][@index][unitPriceTaxExcluded]',
        '[products][products][@index][unitPriceTaxInclRaw]' => '[products][products][@index][unitPriceTaxIncluded]',
        '[discounts][discounts][@index][amountRaw]' => '[discounts][discounts][@index][amount]',
        '[history][statuses][@index][orderStatusId]' => '[history][statuses][@index][orderStateId]',
        '[shipping][carriers][@index][name]' => '[shipping][carriers][@index][carrierName]',
        '[payments][payments][@index][paymentId]' => '[payments][payments][@index][orderPaymentId]',
        '[payments][payments][@index][date]' => '[payments][payments][@index][paymentDate]',
        '[documents][documents][@index][id]' => '[documents][documents][@index][documentId]',
    ];

    // The lists of the query result are wrapped in an object, a mapping cannot move a list and rename its rows at once
    public const API_RESOURCE_MAPPING = [
        '[products][products]' => '[products]',
        '[discounts][discounts]' => '[discounts]',
        '[history][statuses]' => '[history]',
        '[shipping][carriers]' => '[carriers]',
        '[payments][payments]' => '[payments]',
        '[documents][documents]' => '[documents]',
    ];
}
