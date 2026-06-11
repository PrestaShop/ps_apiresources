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
        new CQRSCreate(
            uriTemplate: '/order-states',
            validationContext: ['groups' => ['Default', 'Create']],
            CQRSCommand: AddOrderStateCommand::class,
            CQRSCommandMapping: self::CREATE_COMMAND_MAPPING,
            scopes: ['order_state_write'],
        ),
        new CQRSDelete(
            uriTemplate: '/order-states/{orderStateId}',
            requirements: ['orderStateId' => '\d+'],
            output: false,
            CQRSCommand: DeleteOrderStateCommand::class,
            scopes: ['order_state_write'],
        ),
        new CQRSGet(
            uriTemplate: '/order-states/{orderStateId}',
            requirements: ['orderStateId' => '\d+'],
            CQRSQuery: GetOrderStateForEditing::class,
            CQRSQueryMapping: self::QUERY_MAPPING,
            scopes: ['order_state_read'],
        ),
        new CQRSPartialUpdate(
            uriTemplate: '/order-states/{orderStateId}',
            requirements: ['orderStateId' => '\d+'],
            read: false,
            CQRSCommand: EditOrderStateCommand::class,
            CQRSCommandMapping: self::UPDATE_COMMAND_MAPPING,
            CQRSQuery: GetOrderStateForEditing::class,
            CQRSQueryMapping: self::QUERY_MAPPING,
            scopes: ['order_state_write'],
        ),
    ],
    normalizationContext: ['skip_null_values' => false],
    exceptionToStatus: [
        OrderStateNotFoundException::class => Response::HTTP_NOT_FOUND,
        OrderStateConstraintException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
    ],
)]
class OrderState
{
    #[ApiProperty(identifier: true)]
    public int $orderStateId;

    #[LocalizedValue]
    #[Assert\NotBlank(groups: ['Create'])]
    public array $names;

    #[Assert\NotBlank(groups: ['Create'])]
    public string $color;

    #[Assert\NotNull(groups: ['Create'])]
    public bool $loggable;

    #[Assert\NotNull(groups: ['Create'])]
    public bool $invoice;

    #[Assert\NotNull(groups: ['Create'])]
    public bool $hidden;

    #[Assert\NotNull(groups: ['Create'])]
    public bool $sendEmail;

    #[Assert\NotNull(groups: ['Create'])]
    public bool $pdfInvoice;

    #[Assert\NotNull(groups: ['Create'])]
    public bool $pdfDelivery;

    #[Assert\NotNull(groups: ['Create'])]
    public bool $shipped;

    #[Assert\NotNull(groups: ['Create'])]
    public bool $paid;

    #[Assert\NotNull(groups: ['Create'])]
    public bool $delivery;

    #[LocalizedValue]
    #[Assert\NotNull(groups: ['Create'])]
    public array $templates;

    public const QUERY_MAPPING = [
        '[localizedNames]' => '[names]',
        '[localizedTemplates]' => '[templates]',
    ];

    // AddOrderStateCommand expects "localizedNames" / "localizedTemplates"
    public const CREATE_COMMAND_MAPPING = [
        '[names]' => '[localizedNames]',
        '[templates]' => '[localizedTemplates]',
    ];

    // EditOrderStateCommand expects "name" / "template"
    public const UPDATE_COMMAND_MAPPING = [
        '[names]' => '[name]',
        '[templates]' => '[template]',
    ];
}
