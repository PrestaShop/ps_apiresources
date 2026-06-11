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
            CQRSCommandMapping: self::CREATE_COMMAND_MAPPING,
            scopes: ['order_return_state_write'],
        ),
        new CQRSDelete(
            uriTemplate: '/order-return-states/{orderReturnStateId}',
            requirements: ['orderReturnStateId' => '\d+'],
            output: false,
            CQRSCommand: DeleteOrderReturnStateCommand::class,
            scopes: ['order_return_state_write'],
        ),
        new CQRSGet(
            uriTemplate: '/order-return-states/{orderReturnStateId}',
            requirements: ['orderReturnStateId' => '\d+'],
            CQRSQuery: GetOrderReturnStateForEditing::class,
            CQRSQueryMapping: self::QUERY_MAPPING,
            scopes: ['order_return_state_read'],
        ),
        new CQRSPartialUpdate(
            uriTemplate: '/order-return-states/{orderReturnStateId}',
            requirements: ['orderReturnStateId' => '\d+'],
            read: false,
            CQRSCommand: EditOrderReturnStateCommand::class,
            CQRSCommandMapping: self::UPDATE_COMMAND_MAPPING,
            CQRSQuery: GetOrderReturnStateForEditing::class,
            CQRSQueryMapping: self::QUERY_MAPPING,
            scopes: ['order_return_state_write'],
        ),
    ],
    normalizationContext: ['skip_null_values' => false],
    exceptionToStatus: [
        OrderReturnStateNotFoundException::class => Response::HTTP_NOT_FOUND,
        OrderReturnStateConstraintException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
    ],
)]
class OrderReturnState
{
    #[ApiProperty(identifier: true)]
    public int $orderReturnStateId;

    #[LocalizedValue]
    #[Assert\NotBlank(groups: ['Create'])]
    public array $names;

    #[Assert\NotBlank(groups: ['Create'])]
    public string $color;

    public const QUERY_MAPPING = [
        '[localizedNames]' => '[names]',
    ];

    // AddOrderReturnStateCommand expects "localizedNames"
    public const CREATE_COMMAND_MAPPING = [
        '[names]' => '[localizedNames]',
    ];

    // EditOrderReturnStateCommand expects "name"
    public const UPDATE_COMMAND_MAPPING = [
        '[names]' => '[name]',
    ];
}
