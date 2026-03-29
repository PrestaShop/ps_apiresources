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
 * @author    Pascal Cescon <pascal.cescon@gmail.com>
 * @copyright Since 2007 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License version 3.0
 */

declare(strict_types=1);

namespace PrestaShop\Module\APIResources\ApiPlatform\Resources\Cart;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use PrestaShop\PrestaShop\Core\Domain\Cart\Command\UpdateCartAddressesCommand;
use PrestaShop\PrestaShop\Core\Domain\Cart\Command\UpdateCartCarrierCommand;
use PrestaShop\PrestaShop\Core\Domain\Cart\Command\UpdateCartCurrencyCommand;
use PrestaShop\PrestaShop\Core\Domain\Cart\Command\UpdateCartDeliverySettingsCommand;
use PrestaShop\PrestaShop\Core\Domain\Cart\Command\UpdateCartLanguageCommand;
use PrestaShop\PrestaShop\Core\Domain\Cart\Exception\CannotUpdateCartException;
use PrestaShop\PrestaShop\Core\Domain\Cart\Exception\CartConstraintException;
use PrestaShop\PrestaShop\Core\Domain\Cart\Exception\CartNotFoundException;
use PrestaShop\PrestaShop\Core\Domain\Cart\Exception\InvalidAddressTypeException;
use PrestaShop\PrestaShop\Core\Domain\Cart\Exception\InvalidGiftMessageException;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSUpdate;
use Symfony\Component\HttpFoundation\Response;

#[ApiResource(
    operations: [
        new CQRSUpdate(
            uriTemplate: '/carts/{cartId}/addresses',
            requirements: ['cartId' => '\d+'],
            output: false,
            CQRSCommand: UpdateCartAddressesCommand::class,
            scopes: ['cart_write'],
            CQRSCommandMapping: [
                '[deliveryAddressId]' => '[newDeliveryAddressId]',
                '[invoiceAddressId]' => '[newInvoiceAddressId]',
            ],
        ),
        new CQRSUpdate(
            uriTemplate: '/carts/{cartId}/carrier',
            requirements: ['cartId' => '\d+'],
            output: false,
            CQRSCommand: UpdateCartCarrierCommand::class,
            scopes: ['cart_write'],
            CQRSCommandMapping: [
                '[carrierId]' => '[newCarrierId]',
            ],
        ),
        new CQRSUpdate(
            uriTemplate: '/carts/{cartId}/currency',
            requirements: ['cartId' => '\d+'],
            output: false,
            CQRSCommand: UpdateCartCurrencyCommand::class,
            scopes: ['cart_write'],
            CQRSCommandMapping: [
                '[currencyId]' => '[newCurrencyId]',
            ],
        ),
        new CQRSUpdate(
            uriTemplate: '/carts/{cartId}/language',
            requirements: ['cartId' => '\d+'],
            output: false,
            CQRSCommand: UpdateCartLanguageCommand::class,
            scopes: ['cart_write'],
            CQRSCommandMapping: [
                '[languageId]' => '[newLanguageId]',
            ],
        ),
        new CQRSUpdate(
            uriTemplate: '/carts/{cartId}/delivery-settings',
            requirements: ['cartId' => '\d+'],
            output: false,
            CQRSCommand: UpdateCartDeliverySettingsCommand::class,
            scopes: ['cart_write'],
        ),
    ],
    exceptionToStatus: [
        CartNotFoundException::class => Response::HTTP_NOT_FOUND,
        CartConstraintException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        CannotUpdateCartException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        InvalidAddressTypeException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        InvalidGiftMessageException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
    ],
)]
class CartSettings
{
    #[ApiProperty(identifier: true)]
    public int $cartId;

    public int $deliveryAddressId;

    public int $invoiceAddressId;

    public int $carrierId;

    public int $currencyId;

    public int $languageId;

    public bool $allowFreeShipping;

    public ?bool $isAGift = null;

    public ?bool $useRecycledPackaging = null;

    public ?string $giftMessage = null;
}
