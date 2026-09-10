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
use ApiPlatform\Metadata\Link;
use PrestaShop\PrestaShop\Core\Domain\Cart\Command\AddProductToCartCommand;
use PrestaShop\PrestaShop\Core\Domain\Cart\Command\RemoveProductFromCartCommand;
use PrestaShop\PrestaShop\Core\Domain\Cart\Command\UpdateProductQuantityInCartCommand;
use PrestaShop\PrestaShop\Core\Domain\Cart\Exception\CartConstraintException;
use PrestaShop\PrestaShop\Core\Domain\Cart\Exception\CartNotFoundException;
use PrestaShop\PrestaShop\Core\Domain\Cart\Exception\MinimalQuantityException;
use PrestaShop\PrestaShop\Core\Domain\Product\Exception\ProductNotFoundException;
use PrestaShop\PrestaShop\Core\Domain\Product\Exception\ProductOutOfStockException;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSDelete;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSUpdate;
use Symfony\Component\HttpFoundation\Response;

/**
 * A product line of a cart: adding one, changing its quantity and removing it are three
 * operations on the same structure.
 *
 * None of them returns content. The cart line has no read side of its own — the cart is read
 * through GetCartForViewing, which builds the whole CartDetails view — so there is no query to
 * replay here. The result of a write is observed through GET /carts/{cartId}/details.
 */
#[ApiResource(
    operations: [
        new CQRSUpdate(
            uriTemplate: '/carts/{cartId}/products',
            requirements: ['cartId' => '\d+'],
            uriVariables: [
                'cartId' => new Link(identifiers: ['cartId']),
            ],
            read: false,
            output: false,
            CQRSCommand: AddProductToCartCommand::class,
            scopes: ['cart_write'],
        ),
        new CQRSUpdate(
            uriTemplate: '/carts/{cartId}/products/{productId}',
            requirements: ['cartId' => '\d+', 'productId' => '\d+'],
            uriVariables: [
                'cartId' => new Link(identifiers: ['cartId']),
                'productId' => new Link(identifiers: ['productId']),
            ],
            read: false,
            output: false,
            CQRSCommand: UpdateProductQuantityInCartCommand::class,
            scopes: ['cart_write'],
        ),
        new CQRSDelete(
            uriTemplate: '/carts/{cartId}/products/{productId}',
            requirements: ['cartId' => '\d+', 'productId' => '\d+'],
            uriVariables: [
                'cartId' => new Link(identifiers: ['cartId']),
                'productId' => new Link(identifiers: ['productId']),
            ],
            CQRSCommand: RemoveProductFromCartCommand::class,
            scopes: ['cart_write'],
        ),
    ],
    exceptionToStatus: [
        CartNotFoundException::class => Response::HTTP_NOT_FOUND,
        ProductNotFoundException::class => Response::HTTP_NOT_FOUND,
        CartConstraintException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        MinimalQuantityException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        ProductOutOfStockException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
    ],
)]
class CartProduct
{
    #[ApiProperty(identifier: true)]
    public int $cartId;

    #[ApiProperty(identifier: true)]
    public int $productId;

    public int $quantity;

    public ?int $combinationId;
}
