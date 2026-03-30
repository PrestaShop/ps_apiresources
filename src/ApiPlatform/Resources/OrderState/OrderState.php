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

namespace PrestaShop\Module\APIResources\ApiPlatform\Resources\OrderState;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use PrestaShop\PrestaShop\Core\Domain\OrderState\Command\AddOrderStateCommand;
use PrestaShop\PrestaShop\Core\Domain\OrderState\Command\DeleteOrderStateCommand;
use PrestaShop\PrestaShop\Core\Domain\OrderState\Command\EditOrderStateCommand;
use PrestaShop\PrestaShop\Core\Domain\OrderState\Exception\DeleteOrderStateException;
use PrestaShop\PrestaShop\Core\Domain\OrderState\Exception\OrderStateConstraintException;
use PrestaShop\PrestaShop\Core\Domain\OrderState\Exception\OrderStateNotFoundException;
use PrestaShop\PrestaShop\Core\Domain\OrderState\Query\GetOrderStateForEditing;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSCreate;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSDelete;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSGet;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSPartialUpdate;
use PrestaShopBundle\ApiPlatform\Metadata\LocalizedValue;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    operations: [
        new CQRSGet(
            uriTemplate: '/order-states/{orderStateId}',
            requirements: ['orderStateId' => '\d+'],
            CQRSQuery: GetOrderStateForEditing::class,
            scopes: ['order_state_read'],
            CQRSQueryMapping: self::QUERY_MAPPING,
        ),
        new CQRSCreate(
            uriTemplate: '/order-states',
            CQRSCommand: AddOrderStateCommand::class,
            CQRSQuery: GetOrderStateForEditing::class,
            scopes: ['order_state_write'],
            CQRSQueryMapping: self::QUERY_MAPPING,
            CQRSCommandMapping: self::COMMAND_MAPPING,
        ),
        new CQRSPartialUpdate(
            uriTemplate: '/order-states/{orderStateId}',
            requirements: ['orderStateId' => '\d+'],
            CQRSCommand: EditOrderStateCommand::class,
            CQRSQuery: GetOrderStateForEditing::class,
            scopes: ['order_state_write'],
            CQRSQueryMapping: self::QUERY_MAPPING,
            CQRSCommandMapping: self::EDIT_COMMAND_MAPPING,
        ),
        new CQRSDelete(
            uriTemplate: '/order-states/{orderStateId}',
            requirements: ['orderStateId' => '\d+'],
            CQRSCommand: DeleteOrderStateCommand::class,
            scopes: ['order_state_write'],
        ),
    ],
    exceptionToStatus: [
        OrderStateConstraintException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        OrderStateNotFoundException::class => Response::HTTP_NOT_FOUND,
        DeleteOrderStateException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
    ],
)]
class OrderState
{
    #[ApiProperty(identifier: true)]
    public int $orderStateId;

    #[LocalizedValue]
    #[Assert\NotBlank]
    public array $names;

    #[Assert\Length(max: 7)]
    public string $color;

    public bool $loggable;

    public bool $invoice;

    public bool $hidden;

    public bool $sendEmail;

    public bool $pdfInvoice;

    public bool $pdfDelivery;

    public bool $shipped;

    public bool $paid;

    public bool $delivery;

    #[LocalizedValue]
    public array $templates;

    public bool $deleted;

    public const QUERY_MAPPING = [
        '[localizedNames]' => '[names]',
        '[localizedTemplates]' => '[templates]',
        '[isSendEmailEnabled]' => '[sendEmail]',
        '[isDeleted]' => '[deleted]',
    ];

    public const COMMAND_MAPPING = [
        '[names]' => '[localizedNames]',
        '[templates]' => '[localizedTemplates]',
    ];

    public const EDIT_COMMAND_MAPPING = [
        '[names]' => '[name]',
        '[templates]' => '[template]',
    ];
}
