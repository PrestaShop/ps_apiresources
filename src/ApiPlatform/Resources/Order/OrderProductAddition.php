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
use PrestaShop\Decimal\DecimalNumber;
use PrestaShop\PrestaShop\Core\Domain\Order\Exception\InvalidAmountException;
use PrestaShop\PrestaShop\Core\Domain\Order\Exception\InvalidProductQuantityException;
use PrestaShop\PrestaShop\Core\Domain\Order\Exception\OrderException;
use PrestaShop\PrestaShop\Core\Domain\Order\Exception\OrderNotFoundException;
use PrestaShop\Module\APIResources\ApiPlatform\Processor\AddProductToOrderProcessor;
use PrestaShop\PrestaShop\Core\Domain\Order\Product\Command\AddProductToOrderCommand;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSCreate;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    operations: [
        new CQRSCreate(
            uriTemplate: '/orders/{orderId}/product-additions',
            requirements: ['orderId' => '\d+'],
            CQRSCommand: AddProductToOrderCommand::class,
            processor: AddProductToOrderProcessor::class,
            scopes: ['order_write'],
        ),
    ],
    exceptionToStatus: [
        OrderNotFoundException::class => Response::HTTP_NOT_FOUND,
        InvalidProductQuantityException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        InvalidAmountException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        OrderException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
    ],
)]
class OrderProductAddition
{
    public int $orderId;

    #[Assert\NotBlank]
    public int $productId;

    public int $combinationId = 0;

    #[Assert\NotBlank]
    public DecimalNumber $productPriceTaxIncluded;

    #[Assert\NotBlank]
    public DecimalNumber $productPriceTaxExcluded;

    #[Assert\NotBlank]
    #[Assert\GreaterThan(0)]
    public int $productQuantity;

    public ?int $orderInvoiceId = null;

    public ?bool $hasFreeShipping = null;
}
