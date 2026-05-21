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
use PrestaShop\PrestaShop\Core\Domain\Cart\Command\UpdateProductPriceInCartCommand;
use PrestaShop\PrestaShop\Core\Domain\Cart\Exception\CartConstraintException;
use PrestaShop\PrestaShop\Core\Domain\Cart\Exception\CartException;
use PrestaShop\PrestaShop\Core\Domain\Cart\Exception\CartNotFoundException;
use PrestaShop\Decimal\DecimalNumber;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSPartialUpdate;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    operations: [
        new CQRSPartialUpdate(
            uriTemplate: '/carts/{cartId}/products/price',
            requirements: ['cartId' => '\d+'],
            CQRSCommand: UpdateProductPriceInCartCommand::class,
            scopes: ['cart_write'],
        ),
    ],
    exceptionToStatus: [
        CartConstraintException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        CartException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        CartNotFoundException::class => Response::HTTP_NOT_FOUND,
    ],
)]
class CartProductPrice
{
    #[ApiProperty(identifier: true)]
    public int $cartId;

    #[Assert\NotBlank]
    #[Assert\Positive]
    #[ApiProperty(openapiContext: ['type' => 'integer', 'example' => 1])]
    public int $productId;

    #[Assert\NotBlank]
    #[ApiProperty(openapiContext: ['type' => 'integer', 'example' => 0])]
    public int $combinationId;

    #[Assert\NotBlank]
    #[ApiProperty(openapiContext: ['type' => 'string', 'example' => '19.99'])]
    public DecimalNumber $price;
}
