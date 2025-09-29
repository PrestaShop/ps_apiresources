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
use PrestaShop\PrestaShop\Core\Domain\Order\Command\AddOrderFromBackOfficeCommand;
use PrestaShop\PrestaShop\Core\Domain\Order\Command\UpdateOrderStatusCommand;
use PrestaShop\PrestaShop\Core\Domain\Order\Exception\OrderException;
use PrestaShop\PrestaShop\Core\Domain\Order\Exception\OrderNotFoundException;
use PrestaShop\PrestaShop\Core\Domain\Order\Query\GetOrderForViewing;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSCreate;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSGet;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSPartialUpdate;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Serializer\Exception\NotNormalizableValueException;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Exception\ValidationFailedException;

#[ApiResource(
    operations: [
        new CQRSGet(
            uriTemplate: '/order/{orderId}',
            requirements: ['orderId' => '\d+'],
            scopes: ['order_read'],
            CQRSQuery: GetOrderForViewing::class,
            CQRSQueryMapping: self::QUERY_MAPPING,
            openapiContext: [
                'summary' => 'Get order details',
                'description' => 'Retrieve detailed information about a specific order',
            ],
        ),
        new CQRSCreate(
            uriTemplate: '/order',
            scopes: ['order_write'],
            CQRSCommand: AddOrderFromBackOfficeCommand::class,
            openapiContext: [
                'summary' => 'Create a new order',
                'description' => 'Create a new order from an existing cart',
            ],
        ),
        new CQRSPartialUpdate(
            uriTemplate: '/order/{orderId}',
            requirements: ['orderId' => '\d+'],
            scopes: ['order_write'],
            CQRSCommand: UpdateOrderStatusCommand::class,
            CQRSQuery: GetOrderForViewing::class,
            CQRSCommandMapping: self::UPDATE_COMMAND_MAPPING,
            CQRSQueryMapping: self::UPDATE_QUERY_MAPPING,
            openapiContext: [
                'summary' => 'Update order status',
                'description' => 'Update the status of an order',
            ],
        ),
    ],
    exceptionToStatus: [
        OrderNotFoundException::class => Response::HTTP_NOT_FOUND,
        OrderException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        AccessDeniedHttpException::class => Response::HTTP_FORBIDDEN,
        ValidationFailedException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        NotNormalizableValueException::class => Response::HTTP_BAD_REQUEST,
    ],
)]
class Order
{
    #[ApiProperty(identifier: true)]
    public int $orderId = 0;

    public string $reference = '';

    public string $status = '';

    public int $statusId = 0;

    public int $shopId = 0;

    public int $langId = 0;

    public int $customerId = 0;

    public string $currencyIso = '';

    public int $deliveryAddressId = 0;

    public int $invoiceAddressId = 0;

    public int $carrierId = 0;

    public string $dateAdd = '';

    public array $vatBreakdown = [];

    public array $vatSummary = [];

    public float $totalPaidTaxIncl = 0.0;

    public float $totalPaidTaxExcl = 0.0;

    public float $totalProductsTaxIncl = 0.0;

    public float $totalProductsTaxExcl = 0.0;

    public array $items = [];

    // Fields for order creation
    #[Assert\NotBlank(groups: ['Create'])]
    #[Assert\Positive(groups: ['Create'])]
    public int $cartId = 0;

    #[Assert\NotBlank(groups: ['Create'])]
    #[Assert\Positive(groups: ['Create'])]
    public int $employeeId = 0;

    #[Assert\Length(max: 1000, groups: ['Create'])]
    public string $orderMessage = '';

    #[Assert\Length(max: 255, groups: ['Create'])]
    public string $paymentModuleName = '';

    #[Assert\Positive(groups: ['Create', 'Update'])]
    public int $orderStateId = 0;

    // Validation is handled by CQRS commands and domain services
    // No direct PrestaShop entity validation in API resources

    public const QUERY_MAPPING = [
        '[history][currentOrderStatusId]' => '[statusId]',
        '[history][statuses][0][name]' => '[status]',
        '[prices][totalPaid]' => '[totalPaidTaxIncl:float]',
        '[prices][totalPaidTaxExcluded]' => '[totalPaidTaxExcl:float]',
        '[prices][productsTotal]' => '[totalProductsTaxIncl:float]',
        '[prices][productsTotalTaxExcluded]' => '[totalProductsTaxExcl:float]',
        '[prices][vatBreakdown]' => '[vatBreakdown]',
        '[prices][vatSummary]' => '[vatSummary]',
        '[customer][languageId]' => '[langId]',
        '[customer][id]' => '[customerId]',
        '[shippingAddress][addressId]' => '[deliveryAddressId]',
        '[invoiceAddress][addressId]' => '[invoiceAddressId]',
        '[shipping][carrierId]' => '[carrierId]',
        '[createdAt]' => '[dateAdd]',
        '[products]' => '[items]',
    ];

    public const UPDATE_QUERY_MAPPING = [
        '[history][currentOrderStatusId]' => '[statusId]',
        '[history][statuses][0][name]' => '[status]',
        '[customer][id]' => '[customerId]',
        '[createdAt]' => '[dateAdd]',
    ];

    public const UPDATE_COMMAND_MAPPING = [
        '[statusId]' => '[newOrderStatusId]',
    ];
}
