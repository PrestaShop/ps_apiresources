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
use PrestaShop\PrestaShop\Core\Domain\Cart\Exception\CartConstraintException;
use PrestaShop\PrestaShop\Core\Domain\Cart\Exception\CartNotFoundException;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSCreate;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSDelete;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    operations: [
        new CQRSCreate(
            uriTemplate: '/carts/{cartId}/products',
            requirements: ['cartId' => '\d+'],
            validationContext: ['groups' => ['Default', 'Add']],
            CQRSCommand: AddProductToCartCommand::class,
            scopes: ['cart_write'],
        ),
        new CQRSDelete(
            uriTemplate: '/carts/{cartId}/products/{productId}',
            requirements: ['cartId' => '\d+', 'productId' => '\d+'],
            CQRSCommand: RemoveProductFromCartCommand::class,
            scopes: ['cart_write'],
        ),
    ],
    exceptionToStatus: [
        CartConstraintException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        CartNotFoundException::class => Response::HTTP_NOT_FOUND,
    ],
)]
class CartProducts
{
    #[ApiProperty(identifier: true)]
    public int $cartId;

    #[Assert\NotBlank(groups: ['Add'])]
    #[Assert\Positive(groups: ['Add'])]
    #[ApiProperty(openapiContext: ['type' => 'integer', 'example' => 1])]
    public int $productId;

    #[Assert\Positive(groups: ['Add'])]
    #[ApiProperty(openapiContext: ['type' => 'integer', 'example' => 1])]
    public int $quantity = 0;

    #[ApiProperty(openapiContext: ['type' => 'integer', 'example' => null, 'nullable' => true])]
    public ?int $combinationId = null;

    #[ApiProperty(openapiContext: ['type' => 'integer', 'example' => null, 'nullable' => true])]
    public ?int $customizationId = null;

    #[ApiProperty(openapiContext: [
        'type' => 'object',
        'nullable' => true,
        'description' => 'Key-value pairs where key is the customization field ID and value is the customization value (for AddProductToCartCommand)',
    ])]
    public array $customizationsByFieldIds = [];
}
