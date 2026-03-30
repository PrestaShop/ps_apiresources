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
use PrestaShop\PrestaShop\Core\Domain\Cart\Exception\CartConstraintException;
use PrestaShop\PrestaShop\Core\Domain\Cart\Exception\CartNotFoundException;
use PrestaShop\PrestaShop\Core\Domain\Cart\Query\GetCartForOrderCreation;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSGet;
use Symfony\Component\HttpFoundation\Response;

#[ApiResource(
    operations: [
        new CQRSGet(
            uriTemplate: '/carts/{cartId}/order-creations',
            requirements: ['cartId' => '\d+'],
            CQRSQuery: GetCartForOrderCreation::class,
            scopes: ['cart_read'],
        ),
    ],
    exceptionToStatus: [
        CartNotFoundException::class => Response::HTTP_NOT_FOUND,
        CartConstraintException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
    ],
)]
class CartForOrderCreation
{
    #[ApiProperty(identifier: true)]
    public int $cartId;

    public array $products;

    public int $currencyId;

    public int $langId;

    public array $cartRules;

    public array $addresses;

    public array $summary;

    public ?array $shipping = null;
}
