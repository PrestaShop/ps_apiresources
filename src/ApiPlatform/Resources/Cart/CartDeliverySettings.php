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
use PrestaShop\PrestaShop\Core\Domain\Cart\Command\UpdateCartDeliverySettingsCommand;
use PrestaShop\PrestaShop\Core\Domain\Cart\Exception\CartConstraintException;
use PrestaShop\PrestaShop\Core\Domain\Cart\Exception\CartNotFoundException;
use PrestaShop\PrestaShop\Core\Domain\Cart\Exception\InvalidGiftMessageException;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSPartialUpdate;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    operations: [
        new CQRSPartialUpdate(
            uriTemplate: '/carts/{cartId}/delivery-settings',
            requirements: ['cartId' => '\d+'],
            CQRSCommand: UpdateCartDeliverySettingsCommand::class,
            scopes: ['cart_write'],
            CQRSCommandMapping: self::COMMAND_MAPPING,
        ),
    ],
    exceptionToStatus: [
        CartConstraintException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        CartNotFoundException::class => Response::HTTP_NOT_FOUND,
        InvalidGiftMessageException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
    ],
)]
class CartDeliverySettings
{
    #[ApiProperty(identifier: true)]
    public int $cartId;

    #[Assert\NotNull]
    #[ApiProperty(openapiContext: ['type' => 'boolean', 'example' => false])]
    public bool $allowFreeShipping;

    #[ApiProperty(openapiContext: ['type' => 'boolean', 'example' => false, 'nullable' => true])]
    public ?bool $gift = null;

    #[ApiProperty(openapiContext: ['type' => 'boolean', 'example' => false, 'nullable' => true])]
    public ?bool $recycledPackaging = null;

    #[ApiProperty(openapiContext: ['type' => 'string', 'example' => null, 'nullable' => true])]
    public ?string $giftMessage = null;

    public const COMMAND_MAPPING = [
        '[gift]' => '[isAGift]',
        '[recycledPackaging]' => '[useRecycledPackaging]',
    ];
}
