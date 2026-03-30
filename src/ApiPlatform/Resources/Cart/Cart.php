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
use PrestaShop\PrestaShop\Core\Domain\Cart\Command\CreateEmptyCustomerCartCommand;
use PrestaShop\PrestaShop\Core\Domain\Cart\Command\DeleteCartCommand;
use PrestaShop\PrestaShop\Core\Domain\Cart\Exception\CannotDeleteCartException;
use PrestaShop\PrestaShop\Core\Domain\Cart\Exception\CannotDeleteOrderedCartException;
use PrestaShop\PrestaShop\Core\Domain\Cart\Exception\CartConstraintException;
use PrestaShop\PrestaShop\Core\Domain\Cart\Exception\CartNotFoundException;
use PrestaShop\PrestaShop\Core\Domain\Cart\Query\GetCartForViewing;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSCreate;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSDelete;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSGet;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    operations: [
        new CQRSGet(
            uriTemplate: '/carts/{cartId}',
            requirements: ['cartId' => '\d+'],
            CQRSQuery: GetCartForViewing::class,
            scopes: ['cart_read'],
        ),
        new CQRSCreate(
            uriTemplate: '/carts',
            CQRSCommand: CreateEmptyCustomerCartCommand::class,
            scopes: ['cart_write'],
        ),
        new CQRSDelete(
            uriTemplate: '/carts/{cartId}',
            requirements: ['cartId' => '\d+'],
            CQRSCommand: DeleteCartCommand::class,
            scopes: ['cart_write'],
        ),
    ],
    exceptionToStatus: [
        CartNotFoundException::class => Response::HTTP_NOT_FOUND,
        CartConstraintException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        CannotDeleteCartException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        CannotDeleteOrderedCartException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
    ],
)]
class Cart
{
    #[ApiProperty(identifier: true)]
    public int $cartId;

    #[Assert\NotBlank]
    public int $customerId;

    public int $currencyId;

    public array $customerInformation;

    public array $orderInformation;

    public array $cartSummary;
}
