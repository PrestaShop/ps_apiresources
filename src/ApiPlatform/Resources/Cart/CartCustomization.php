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
use PrestaShop\PrestaShop\Core\Domain\Cart\Command\AddCustomizationCommand;
use PrestaShop\PrestaShop\Core\Domain\Cart\Exception\CartConstraintException;
use PrestaShop\PrestaShop\Core\Domain\Cart\Exception\CartNotFoundException;
use PrestaShop\PrestaShop\Core\Domain\Product\Customization\Exception\CustomizationConstraintException;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSCreate;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    operations: [
        new CQRSCreate(
            uriTemplate: '/carts/{cartId}/customizations',
            requirements: ['cartId' => '\d+'],
            validationContext: ['groups' => ['Default', 'Create']],
            CQRSCommand: AddCustomizationCommand::class,
            scopes: ['cart_write'],
        ),
    ],
    exceptionToStatus: [
        CartConstraintException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        CartNotFoundException::class => Response::HTTP_NOT_FOUND,
        CustomizationConstraintException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
    ],
)]
class CartCustomization
{
    #[ApiProperty(identifier: true)]
    public int $cartId;

    #[Assert\NotBlank]
    #[Assert\Positive]
    #[ApiProperty(openapiContext: ['type' => 'integer', 'example' => 1])]
    public int $productId;

    #[Assert\NotBlank]
    #[ApiProperty(openapiContext: [
        'type' => 'object',
        'description' => 'Key-value pairs where key is the customization field ID and value is the text customization value',
        'example' => ['1' => 'My custom text'],
    ])]
    public array $customizationValuesByFieldIds;
}
