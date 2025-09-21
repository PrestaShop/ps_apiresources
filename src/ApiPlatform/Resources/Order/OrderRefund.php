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

use ApiPlatform\Metadata\ApiResource;
use PrestaShop\Decimal\DecimalNumber;
use PrestaShop\Module\APIResources\ApiPlatform\Serializer\Callbacks;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSCreate;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ApiResource(
    operations: [
        new CQRSCreate(
            uriTemplate: '/orders/{orderId}/refunds',
            requirements: ['orderId' => '\\d+'],
            scopes: ['order_write'],
            CQRSCommand: \PrestaShop\PrestaShop\Core\Domain\Order\Command\IssueStandardRefundCommand::class,
            CQRSCommandMapping: [
                '[orderId]' => '[orderId]',
                '[orderDetailRefunds]' => '[orderDetailRefunds]',
                '[refundAmount]' => '[refundAmount]',
                '[shippingRefundAmount]' => '[shippingRefundAmount]',
                '[refundShippingCost]' => '[refundShippingCost]',
                '[generateCreditSlip]' => '[generateCreditSlip]',
                '[generateVoucher]' => '[generateVoucher]',
                '[voucherRefundType]' => '[voucherRefundType]',
            ],
            openapiContext: [
                'summary' => 'Issue standard refund for an order',
            ],
            allowEmptyBody: false,
        ),
    ],
    denormalizationContext: [
        'skip_null_values' => false,
        'disable_type_enforcement' => true,
        'allow_extra_attributes' => true,
        'callbacks' => [
            'orderId' => [Callbacks::class, 'toInt'],
            'orderDetailRefunds' => [Callbacks::class, 'toOrderDetailRefunds'],
            'refundAmount' => [Callbacks::class, 'toDecimalNumber'],
            'shippingRefundAmount' => [Callbacks::class, 'toDecimalNumber'],
            'voucherRefundType' => [Callbacks::class, 'toInt'],
        ],
    ],
    exceptionToStatus: [
        \PrestaShop\PrestaShop\Core\Domain\Order\Exception\OrderNotFoundException::class => Response::HTTP_NOT_FOUND,
        \PrestaShop\PrestaShop\Core\Domain\Order\Exception\OrderException::class => Response::HTTP_NOT_FOUND,
        \PrestaShop\PrestaShop\Core\Domain\Order\Exception\InvalidCancelProductException::class => Response::HTTP_BAD_REQUEST,
        \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException::class => Response::HTTP_FORBIDDEN,
        \Symfony\Component\Validator\Exception\ValidationFailedException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        \Symfony\Component\Serializer\Exception\NotNormalizableValueException::class => Response::HTTP_BAD_REQUEST,
        \InvalidArgumentException::class => Response::HTTP_BAD_REQUEST,
    ],
)]
/**
 * API Resource handling standard order refunds.
 */
class OrderRefund
{
    /**
     * @var int|null Order ID from URI
     */
    public ?int $orderId = null;

    /**
     * @var array<int,int>|null Map of order detail identifiers to quantity to refund
     */
    public ?array $orderDetailRefunds = null;

    /**
     * @var DecimalNumber|null Total amount to refund (calculated from orderDetailRefunds)
     */
    #[Assert\GreaterThanOrEqual(0)]
    public ?DecimalNumber $refundAmount = null;

    /**
     * @var DecimalNumber|null Shipping cost refund amount
     */
    #[Assert\GreaterThanOrEqual(0)]
    public ?DecimalNumber $shippingRefundAmount = null;

    /**
     * @var bool|null Whether the shipping cost should be refunded
     */
    public ?bool $refundShippingCost = null;

    /**
     * @var bool|null Whether a credit slip should be generated
     */
    public ?bool $generateCreditSlip = null;

    /**
     * @var bool|null Whether a voucher should be generated
     */
    public ?bool $generateVoucher = null;

    /**
     * @var int|null Voucher refund type
     */
    #[Assert\Type('int')]
    #[Assert\GreaterThanOrEqual(0)]
    public ?int $voucherRefundType = null;

    #[Assert\Callback]
    public function validateRefundConsistency(ExecutionContextInterface $context): void
    {
        // Validation de cohérence des montants de remboursement
        if ($this->refundAmount !== null && $this->shippingRefundAmount !== null) {
            // Vérifier que les montants individuels sont cohérents avec le total
            // Cette validation nécessiterait de connaître le montant total de la commande
            // Pour l'instant, on valide seulement que les montants sont positifs
        }

        // Validation du montant d'expédition si demandé
        if ($this->refundShippingCost === true && $this->shippingRefundAmount === null) {
            $context->buildViolation('Le montant de remboursement des frais de port est requis quand refundShippingCost est true')
                ->atPath('shippingRefundAmount')
                ->addViolation();
        }

        if ($this->refundShippingCost === false && $this->shippingRefundAmount !== null) {
            $context->buildViolation('Le montant de remboursement des frais de port ne doit pas être fourni quand refundShippingCost est false')
                ->atPath('shippingRefundAmount')
                ->addViolation();
        }
    }
}
