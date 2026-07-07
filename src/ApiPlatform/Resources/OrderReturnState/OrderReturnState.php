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

namespace PrestaShop\Module\APIResources\ApiPlatform\Resources\OrderReturnState;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use PrestaShop\PrestaShop\Core\Domain\OrderReturnState\Command\AddOrderReturnStateCommand;
use PrestaShop\PrestaShop\Core\Domain\OrderReturnState\Command\DeleteOrderReturnStateCommand;
use PrestaShop\PrestaShop\Core\Domain\OrderReturnState\Command\EditOrderReturnStateCommand;
use PrestaShop\PrestaShop\Core\Domain\OrderReturnState\Exception\OrderReturnStateConstraintException;
use PrestaShop\PrestaShop\Core\Domain\OrderReturnState\Exception\OrderReturnStateNotFoundException;
use PrestaShop\PrestaShop\Core\Domain\OrderReturnState\Query\GetOrderReturnStateForEditing;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSCreate;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSDelete;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSGet;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSPartialUpdate;
use PrestaShopBundle\ApiPlatform\Metadata\LocalizedValue;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    operations: [
        new CQRSCreate(
            uriTemplate: '/order-return-states',
            validationContext: ['groups' => ['Default', 'Create']],
            CQRSCommand: AddOrderReturnStateCommand::class,
            scopes: ['order_return_state_write'],
        ),
        new CQRSGet(
            uriTemplate: '/order-return-states/{orderReturnStateId}',
            requirements: ['orderReturnStateId' => '\d+'],
            CQRSQuery: GetOrderReturnStateForEditing::class,
            scopes: ['order_return_state_read'],
        ),
        new CQRSPartialUpdate(
            uriTemplate: '/order-return-states/{orderReturnStateId}',
            requirements: ['orderReturnStateId' => '\d+'],
            CQRSCommand: EditOrderReturnStateCommand::class,
            CQRSQuery: GetOrderReturnStateForEditing::class,
            scopes: ['order_return_state_write'],
            CQRSCommandMapping: self::UPDATE_COMMAND_MAPPING,
        ),
        new CQRSDelete(
            uriTemplate: '/order-return-states/{orderReturnStateId}',
            requirements: ['orderReturnStateId' => '\d+'],
            CQRSCommand: DeleteOrderReturnStateCommand::class,
            scopes: ['order_return_state_write'],
        ),
    ],
    normalizationContext: ['skip_null_values' => false],
    exceptionToStatus: [
        OrderReturnStateConstraintException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        OrderReturnStateNotFoundException::class => Response::HTTP_NOT_FOUND,
    ],
)]
class OrderReturnState
{
    #[ApiProperty(identifier: true)]
    public int $orderReturnStateId;

    #[LocalizedValue]
    #[Assert\NotBlank(groups: ['Create'])]
    public array $localizedNames;

    #[Assert\NotBlank(groups: ['Create'])]
    public string $color;

    /**
     * The Edit command stores the localized names through setName(), so the
     * normalized [localizedNames] field must be mapped to the command's [name] field.
     */
    public const UPDATE_COMMAND_MAPPING = [
        '[localizedNames]' => '[name]',
    ];
}
