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
use PrestaShop\PrestaShop\Core\Domain\Cart\Command\UpdateProductQuantityInCartCommand;
use PrestaShop\PrestaShop\Core\Domain\Cart\Exception\CannotUpdateCartException;
use PrestaShop\PrestaShop\Core\Domain\Cart\Exception\CartConstraintException;
use PrestaShop\PrestaShop\Core\Domain\Cart\Exception\CartNotFoundException;
use PrestaShop\PrestaShop\Core\Domain\Cart\Exception\MinimalQuantityException;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSCreate;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSDelete;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSUpdate;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    operations: [
        new CQRSCreate(
            uriTemplate: '/carts/{cartId}/products',
            requirements: ['cartId' => '\d+'],
            output: false,
            CQRSCommand: AddProductToCartCommand::class,
            scopes: ['cart_write'],
        ),
        new CQRSDelete(
            uriTemplate: '/carts/{cartId}/products',
            requirements: ['cartId' => '\d+'],
            CQRSCommand: RemoveProductFromCartCommand::class,
            scopes: ['cart_write'],
        ),
        new CQRSUpdate(
            uriTemplate: '/carts/{cartId}/products/quantities',
            requirements: ['cartId' => '\d+'],
            output: false,
            CQRSCommand: UpdateProductQuantityInCartCommand::class,
            scopes: ['cart_write'],
        ),
    ],
    exceptionToStatus: [
        CartNotFoundException::class => Response::HTTP_NOT_FOUND,
        CartConstraintException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        CannotUpdateCartException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        MinimalQuantityException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
    ],
)]
class CartProduct
{
    #[ApiProperty(identifier: true)]
    public int $cartId;

    #[Assert\NotBlank]
    public int $productId;

    public int $quantity;

    public ?int $combinationId = null;

    public ?int $customizationId = null;

    public array $customizationsByFieldIds = [];
}
