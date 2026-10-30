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
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use PrestaShop\PrestaShop\Core\ConstraintValidator\Constraints\DefaultLanguage;
use PrestaShop\PrestaShop\Core\ConstraintValidator\Constraints\TypedRegex;
use PrestaShop\PrestaShop\Core\Domain\OrderState\Command\AddOrderStateCommand;
use PrestaShop\PrestaShop\Core\Domain\OrderState\Command\DeleteOrderStateCommand;
use PrestaShop\PrestaShop\Core\Domain\OrderState\Command\EditOrderStateCommand;
use PrestaShop\PrestaShop\Core\Domain\OrderState\Exception\OrderStateException;
use PrestaShop\PrestaShop\Core\Domain\OrderState\Exception\OrderStateNotFoundException;
use PrestaShop\PrestaShop\Core\Domain\OrderState\OrderStateSettings;
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
            extraProperties: [
                // The defaults of the back office form, applied before the validation from PrestaShop 9.2.0
                // (the fields stay required on older versions)
                'defaultValues' => self::CREATE_DEFAULT_VALUES,
            ],
        ),
        new CQRSPartialUpdate(
            uriTemplate: '/order-states/{orderStateId}',
            requirements: ['orderStateId' => '\d+'],
            validationContext: ['groups' => ['Default', 'Update']],
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
            CQRSCommand: DeleteOrderStateCommand::class,
            scopes: [
                'order_state_write',
            ],
            openapi: new OpenApiOperation(
                description: 'Order states are soft deleted: the state stays readable and editable with "deleted": true, leaves '
                    . 'the list. The native states of the shop (unremovable) cannot be deleted and answer 422.',
            ),
        ),
    ],
    normalizationContext: ['skip_null_values' => false],
    exceptionToStatus: [
        OrderStateNotFoundException::class => Response::HTTP_NOT_FOUND,
        // Every exception of the domain extends it, and the add / edit handlers and OrderStateId throw it directly on invalid values
        OrderStateException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
    ],
)]
class OrderState
{
    #[ApiProperty(identifier: true)]
    public int $orderStateId;

    #[LocalizedValue]
    #[DefaultLanguage(groups: ['Create'], fieldName: 'names')]
    #[DefaultLanguage(groups: ['Update'], fieldName: 'names', allowNull: true)]
    // Same rules as the back office form (OrderStateType)
    #[Assert\All(constraints: [
        new TypedRegex([
            'type' => TypedRegex::TYPE_GENERIC_NAME,
        ]),
        new Assert\Length(max: OrderStateSettings::NAME_MAX_LENGTH),
    ])]
    public array $names;

    #[ApiProperty(openapiContext: [
        'description' => 'Mail template sent to the customer when an order gets this state, per language. Only '
            . 'stored when the same request sends "sendEmail": true; ignored otherwise. The name is not checked '
            . 'against the existing mail templates.',
        'example' => ['en-US' => 'payment', 'fr-FR' => 'payment'],
    ])]
    #[LocalizedValue]
    #[Assert\NotNull(groups: ['Create'])]
    // Mail template names, validated by OrderState::$definition (isTplName, 64 characters)
    #[Assert\All(constraints: [
        new Assert\Regex(pattern: '/^[a-zA-Z0-9_-]*$/'),
        new Assert\Length(max: 64),
    ])]
    public array $templates;

    // Validated by OrderState::$definition (isColor, 32 characters)
    #[Assert\NotBlank(groups: ['Create'])]
    #[Assert\Regex(pattern: '/^(#[0-9a-fA-F]{6}|[a-zA-Z0-9-]*)$/')]
    #[Assert\Length(max: 32)]
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

    #[ApiProperty(openapiContext: [
        'description' => 'Read only: order states are soft deleted, a deleted state stays readable with true.',
    ])]
    public bool $deleted;

    public const QUERY_MAPPING = [
        '[localizedNames]' => '[names]',
        '[localizedTemplates]' => '[templates]',
        '[sendEmailEnabled]' => '[sendEmail]',
    ];

    public const CREATE_DEFAULT_VALUES = [
        'templates' => [],
        'loggable' => false,
        'invoice' => false,
        'hidden' => false,
        'sendEmail' => false,
        'pdfInvoice' => false,
        'pdfDelivery' => false,
        'shipped' => false,
        'paid' => false,
        'delivery' => false,
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
