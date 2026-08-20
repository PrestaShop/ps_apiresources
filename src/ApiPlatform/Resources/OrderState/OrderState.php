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

namespace PrestaShop\Module\APIResources\ApiPlatform\Resources\OrderState;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use PrestaShop\PrestaShop\Core\ConstraintValidator\Constraints\DefaultLanguage;
use PrestaShop\PrestaShop\Core\Domain\OrderState\Command\AddOrderStateCommand;
use PrestaShop\PrestaShop\Core\Domain\OrderState\Command\DeleteOrderStateCommand;
use PrestaShop\PrestaShop\Core\Domain\OrderState\Command\EditOrderStateCommand;
use PrestaShop\PrestaShop\Core\Domain\OrderState\Exception\DuplicateOrderStateNameException;
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
            scopes: [
                'order_state_read',
            ],
            CQRSQueryMapping: self::QUERY_MAPPING,
        ),
        new CQRSCreate(
            uriTemplate: '/order-states',
            validationContext: ['groups' => ['Default', 'Create']],
            CQRSCommand: AddOrderStateCommand::class,
            CQRSQuery: GetOrderStateForEditing::class,
            scopes: [
                'order_state_write',
            ],
            CQRSQueryMapping: self::QUERY_MAPPING,
            CQRSCommandMapping: self::CREATE_COMMAND_MAPPING,
        ),
        new CQRSPartialUpdate(
            uriTemplate: '/order-states/{orderStateId}',
            requirements: ['orderStateId' => '\d+'],
            CQRSCommand: EditOrderStateCommand::class,
            CQRSQuery: GetOrderStateForEditing::class,
            scopes: [
                'order_state_write',
            ],
            CQRSQueryMapping: self::QUERY_MAPPING,
            CQRSCommandMapping: self::UPDATE_COMMAND_MAPPING,
        ),
        new CQRSDelete(
            uriTemplate: '/order-states/{orderStateId}',
            requirements: ['orderStateId' => '\d+'],
            output: false,
            CQRSCommand: DeleteOrderStateCommand::class,
            scopes: [
                'order_state_write',
            ],
        ),
    ],
    exceptionToStatus: [
        OrderStateNotFoundException::class => Response::HTTP_NOT_FOUND,
        OrderStateConstraintException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        DuplicateOrderStateNameException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
    ],
)]
class OrderState
{
    #[ApiProperty(identifier: true)]
    public int $orderStateId;

    #[LocalizedValue]
    #[DefaultLanguage(groups: ['Create'], fieldName: 'names')]
    #[DefaultLanguage(groups: ['Update'], fieldName: 'names', allowNull: true)]
    public array $names;

    #[LocalizedValue]
    public array $templates;

    #[Assert\NotBlank(groups: ['Create'])]
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

    public const QUERY_MAPPING = [
        '[localizedNames]' => '[names]',
        '[localizedTemplates]' => '[templates]',
        '[sendEmailEnabled]' => '[sendEmail]',
    ];

    public const CREATE_COMMAND_MAPPING = [
        '[names]' => '[localizedNames]',
        '[templates]' => '[localizedTemplates]',
    ];

    public const UPDATE_COMMAND_MAPPING = [
        '[names]' => '[name]',
        '[templates]' => '[template]',
    ];
}
