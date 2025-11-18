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

namespace PrestaShop\Module\APIResources\ApiPlatform\Resources\Contact;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use PrestaShop\PrestaShop\Core\ConstraintValidator\Constraints\DefaultLanguage;
use PrestaShop\PrestaShop\Core\ConstraintValidator\Constraints\TypedRegex;
use PrestaShop\PrestaShop\Core\Domain\Contact\Command\AddContactCommand;
use PrestaShop\PrestaShop\Core\Domain\Contact\Command\EditContactCommand;
use PrestaShop\PrestaShop\Core\Domain\Contact\Exception\ContactConstraintException;
use PrestaShop\PrestaShop\Core\Domain\Contact\Exception\ContactNotFoundException;
use PrestaShop\PrestaShop\Core\Domain\Contact\Query\GetContactForEditing;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSCreate;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSGet;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSPartialUpdate;
use PrestaShopBundle\ApiPlatform\Metadata\LocalizedValue;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    operations: [
        new CQRSGet(
            uriTemplate: '/contacts/{contactId}',
            CQRSQuery: GetContactForEditing::class,
            scopes: [
                'contact_read',
            ],
            CQRSQueryMapping: self::QUERY_MAPPING,
        ),
        new CQRSCreate(
            uriTemplate: '/contacts',
            validationContext: ['groups' => ['Default', 'Create']],
            CQRSCommand: AddContactCommand::class,
            CQRSQuery: GetContactForEditing::class,
            scopes: [
                'contact_write',
            ],
            CQRSQueryMapping: self::QUERY_MAPPING,
            CQRSCommandMapping: self::CREATE_COMMAND_MAPPING,
        ),
        new CQRSPartialUpdate(
            uriTemplate: '/contacts/{contactId}',
            validationContext: ['groups' => ['Default', 'Update']],
            CQRSCommand: EditContactCommand::class,
            CQRSQuery: GetContactForEditing::class,
            scopes: [
                'contact_write',
            ],
            CQRSQueryMapping: self::QUERY_MAPPING,
            CQRSCommandMapping: self::UPDATE_COMMAND_MAPPING,
        ),
    ],
    exceptionToStatus: [
        ContactConstraintException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        ContactNotFoundException::class => Response::HTTP_NOT_FOUND,
    ],
)]
class Contact
{
    #[ApiProperty(identifier: true)]
    public int $contactId;

    #[LocalizedValue]
    #[DefaultLanguage(groups: ['Create'], fieldName: 'names')]
    #[DefaultLanguage(groups: ['Update'], fieldName: 'names', allowNull: true)]
    #[Assert\All(constraints: [
        new TypedRegex([
            'type' => TypedRegex::TYPE_CATALOG_NAME,
        ]),
    ])]
    public array $names;

    #[Assert\Email(mode: Assert\Email::VALIDATION_MODE_STRICT)]
    public string $email;

    #[LocalizedValue]
    #[DefaultLanguage(groups: ['Create'], fieldName: 'descriptions', allowNull: true)]
    #[DefaultLanguage(groups: ['Update'], fieldName: 'descriptions', allowNull: true)]
    #[Assert\All(constraints: [
        new TypedRegex([
            'type' => TypedRegex::TYPE_CATALOG_NAME,
        ]),
    ])]
    public array $descriptions;

    public bool $messagesSavingEnabled;

    #[ApiProperty(openapiContext: ['type' => 'array', 'items' => ['type' => 'integer'], 'example' => [1, 3]])]
    #[Assert\NotBlank(allowNull: true)]
    public array $shopIds;

    public const QUERY_MAPPING = [
        '[localisedTitles]' => '[names]',
        '[localisedDescription]' => '[descriptions]',
        '[messagesSavingEnabled]' => '[isMessagesSavingEnabled]',
        '[shopAssociation]' => '[shopIds]',
    ];

    public const CREATE_COMMAND_MAPPING = [
        '[names]' => '[localisedTitles]',
        '[descriptions]' => '[localisedDescription]',
        '[messagesSavingEnabled]' => '[isMessageSavingEnabled]',
    ];

    public const UPDATE_COMMAND_MAPPING = [
        '[names]' => '[localisedTitles]',
        '[descriptions]' => '[localisedDescription]',
        '[messagesSavingEnabled]' => '[isMessagesSavingEnabled]',
    ];
}
