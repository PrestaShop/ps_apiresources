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
use PrestaShopBundle\ApiPlatform\Metadata\CQRSCreate;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSGet;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSPartialUpdate;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ApiResource(
    operations: [
        new CQRSGet(
            uriTemplate: '/orders/{orderId}',
            requirements: ['orderId' => '\d+'],
            scopes: ['order_read'],
            CQRSQuery: \PrestaShop\PrestaShop\Core\Domain\Order\Query\GetOrderForViewing::class,
            CQRSQueryMapping: [
                '[orderId]' => '[orderId]',
                '[reference]' => '[reference]',
                // status id and a best-effort status label
                '[history][currentOrderStatusId]' => '[statusId]',
                '[history][statuses][0][name]' => '[status]',
                '[prices][totalPaid]' => '[totalPaidTaxIncl:float]',
                '[prices][totalPaidTaxExcluded]' => '[totalPaidTaxExcl:float]',
                '[prices][productsTotal]' => '[totalProductsTaxIncl:float]',
                '[prices][productsTotalTaxExcluded]' => '[totalProductsTaxExcl:float]',
                '[prices][vatBreakdown]' => '[vatBreakdown]',
                '[prices][vatSummary]' => '[vatSummary]',
                '[taxes][breakdown]' => '[vatBreakdown]',
                '[taxes][summary]' => '[vatSummary]',
                '[shopId]' => '[shopId]',
                '[customer][languageId]' => '[langId]',
                '[customer][id]' => '[customerId]',
                '[shippingAddress][addressId]' => '[deliveryAddressId]',
                '[invoiceAddress][addressId]' => '[invoiceAddressId]',
                '[shipping][carrierId]' => '[carrierId]',
                '[createdAt]' => '[dateAdd]',
                // products list mapping - accessing the products collection directly
                '[products]' => '[items]',
            ],
        ),
        new CQRSCreate(
            uriTemplate: '/orders',
            scopes: ['order_write'],
            CQRSCommand: \PrestaShop\PrestaShop\Core\Domain\Order\Command\AddOrderFromBackOfficeCommand::class,
            CQRSCommandMapping: [
                '[cartId]' => '[cartId]',
                '[employeeId]' => '[employeeId]',
                '[paymentModuleName]' => '[paymentModuleName]',
                '[orderStateId]' => '[orderStateId]',
                '[orderMessage]' => '[orderMessage]',
            ],
            openapiContext: [
                'summary' => 'Create a new order',
                'description' => 'Create a new order from an existing cart using CQRS command pattern.',
                'requestBody' => [
                    'required' => true,
                    'content' => [
                        'application/json' => [
                            'schema' => [
                                'type' => 'object',
                                'required' => ['cartId', 'employeeId'],
                                'properties' => [
                                    'cartId' => [
                                        'type' => 'integer',
                                        'minimum' => 1,
                                        'description' => 'ID of the cart to convert to order',
                                    ],
                                    'employeeId' => [
                                        'type' => 'integer',
                                        'minimum' => 1,
                                        'description' => 'ID of the employee creating the order',
                                    ],
                                    'paymentModuleName' => [
                                        'type' => 'string',
                                        'maxLength' => 255,
                                        'description' => 'Name of the payment module',
                                    ],
                                    'orderStateId' => [
                                        'type' => 'integer',
                                        'minimum' => 1,
                                        'description' => 'Initial order status ID',
                                    ],
                                    'orderMessage' => [
                                        'type' => 'string',
                                        'maxLength' => 1000,
                                        'description' => 'Optional message for the order',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ),
        new CQRSPartialUpdate(
            uriTemplate: '/orders/{orderId}',
            requirements: ['orderId' => '\d+'],
            scopes: ['order_write'],
            CQRSCommand: \PrestaShop\PrestaShop\Core\Domain\Order\Command\UpdateOrderStatusCommand::class,
            CQRSQuery: \PrestaShop\PrestaShop\Core\Domain\Order\Query\GetOrderForViewing::class,
            CQRSCommandMapping: [
                '[statusId]' => '[newOrderStatusId]',
            ],
            CQRSQueryMapping: [
                '[orderId]' => '[orderId]',
                '[reference]' => '[reference]',
                '[history][currentOrderStatusId]' => '[statusId]',
                '[history][statuses][0][name]' => '[status]',
                '[shopId]' => '[shopId]',
                '[customer][id]' => '[customerId]',
                '[createdAt]' => '[dateAdd]',
            ],
            openapiContext: [
                'summary' => 'Update order status',
                'description' => 'Update the status of an order by providing a new status ID. This operation follows CQRS patterns for safe order state transitions.',
                'requestBody' => [
                    'required' => true,
                    'content' => [
                        'application/json' => [
                            'schema' => [
                                'type' => 'object',
                                'required' => ['statusId'],
                                'properties' => [
                                    'statusId' => [
                                        'type' => 'integer',
                                        'minimum' => 1,
                                        'description' => 'New status ID for the order',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ),
    ],
    exceptionToStatus: [
        \PrestaShop\PrestaShop\Core\Domain\Order\Exception\OrderNotFoundException::class => Response::HTTP_NOT_FOUND,
        \PrestaShop\PrestaShop\Core\Domain\Order\Exception\OrderException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException::class => Response::HTTP_FORBIDDEN,
        \Symfony\Component\Validator\Exception\ValidationFailedException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        \Symfony\Component\Serializer\Exception\NotNormalizableValueException::class => Response::HTTP_BAD_REQUEST,
        \RuntimeException::class => Response::HTTP_NOT_FOUND,
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

    #[Assert\Callback]
    public function validate(ExecutionContextInterface $context): void
    {
        // Validate cart exists and is valid for order creation
        if ($this->cartId > 0) {
            if (!\Validate::isLoadedObject(new \Cart($this->cartId))) {
                $context->buildViolation('The cart with ID {{ cart_id }} does not exist or is invalid.')
                    ->setParameter('{{ cart_id }}', (string) $this->cartId)
                    ->atPath('cartId')
                    ->addViolation();
            }
        }

        // Validate employee exists
        if ($this->employeeId > 0) {
            if (!\Validate::isLoadedObject(new \Employee($this->employeeId))) {
                $context->buildViolation('The employee with ID {{ employee_id }} does not exist or is invalid.')
                    ->setParameter('{{ employee_id }}', (string) $this->employeeId)
                    ->atPath('employeeId')
                    ->addViolation();
            }
        }

        // Validate order state exists
        if ($this->orderStateId > 0) {
            if (!\Validate::isLoadedObject(new \OrderState($this->orderStateId))) {
                $context->buildViolation('The order state with ID {{ state_id }} does not exist or is invalid.')
                    ->setParameter('{{ state_id }}', (string) $this->orderStateId)
                    ->atPath('orderStateId')
                    ->addViolation();
            }
        }
    }
}
