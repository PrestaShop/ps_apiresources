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
use PrestaShop\PrestaShop\Core\Domain\Cart\Command\BulkDeleteCartCommand;
use PrestaShop\PrestaShop\Core\Domain\Cart\Exception\BulkCartException;
use PrestaShop\PrestaShop\Core\Domain\Cart\Exception\CartConstraintException;
use PrestaShop\PrestaShop\Core\Domain\Cart\Exception\CartException;
use PrestaShop\PrestaShop\Core\Domain\Cart\Exception\CartNotFoundException;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSDelete;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    operations: [
        new CQRSDelete(
            uriTemplate: '/carts/bulk-delete',
            extraProperties: self::VERSION_GATE,
            // ApiPlatform skips validation on DELETE unless it is explicitly enabled.
            validate: true,
            CQRSCommand: BulkDeleteCartCommand::class,
            scopes: ['cart_write'],
        ),
    ],
    // BulkCommandExceptionNormalizer resolves the status of every sub error of the 207 body against this
    // map and falls back to 500, so the per cart exceptions must be listed here, not only the bulk one.
    // Order matters, the first match wins: the two entries above extend CartException.
    exceptionToStatus: [
        CartNotFoundException::class => Response::HTTP_NOT_FOUND,
        CartConstraintException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        BulkCartException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        CartException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
    ],
)]
class BulkDeleteCarts
{
    public const VERSION_GATE = ['minVersion' => '9.2.0'];

    #[Assert\NotBlank]
    // Without this the invalid ids are only caught by the CartId value object, which answers a bare
    // constraint exception instead of the validation error body the rest of the API returns.
    #[Assert\All([
        new Assert\Type('integer'),
        new Assert\Positive(),
    ])]
    #[ApiProperty(openapiContext: ['type' => 'array', 'items' => ['type' => 'integer'], 'example' => [1, 2]])]
    public array $cartIds;
}
