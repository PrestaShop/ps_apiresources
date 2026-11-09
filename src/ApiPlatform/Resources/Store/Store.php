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

namespace PrestaShop\Module\APIResources\ApiPlatform\Resources\Store;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use PrestaShop\Decimal\DecimalNumber;
use PrestaShop\PrestaShop\Core\ConstraintValidator\Constraints\CleanHtml;
use PrestaShop\PrestaShop\Core\ConstraintValidator\Constraints\DefaultLanguage;
use PrestaShop\PrestaShop\Core\ConstraintValidator\Constraints\TypedRegex;
use PrestaShop\PrestaShop\Core\Domain\Store\Command\AddStoreCommand;
use PrestaShop\PrestaShop\Core\Domain\Store\Command\DeleteStoreCommand;
use PrestaShop\PrestaShop\Core\Domain\Store\Command\EditStoreCommand;
use PrestaShop\PrestaShop\Core\Domain\Store\Command\ToggleStoreStatusCommand;
use PrestaShop\PrestaShop\Core\Domain\Store\Exception\StoreConstraintException;
use PrestaShop\PrestaShop\Core\Domain\Store\Exception\StoreNotFoundException;
use PrestaShop\PrestaShop\Core\Domain\Store\Query\GetStoreForEditing;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSCreate;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSDelete;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSGet;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSPartialUpdate;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSUpdate;
use PrestaShopBundle\ApiPlatform\Metadata\LocalizedValue;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    operations: [
        new CQRSCreate(
            uriTemplate: '/stores',
            validationContext: ['groups' => ['Default', 'Create']],
            CQRSCommand: AddStoreCommand::class,
            CQRSQuery: GetStoreForEditing::class,
            scopes: ['store_write'],
            CQRSQueryMapping: self::QUERY_MAPPING,
            CQRSCommandMapping: self::COMMAND_MAPPING,
            extraProperties: [
                // The store commands exist from PrestaShop 9.3.0 (PrestaShop#41414)
                'minVersion' => '9.3.0',
                // The defaults of the back office form, applied before the validation
                'defaultValues' => self::CREATE_DEFAULT_VALUES,
            ],
            openapi: new OpenApiOperation(
                summary: 'Create a store.',
                description: 'Requires PrestaShop 9.3.0. The country must exist and its state must be consistent with '
                    . 'it: a country containing states requires a stateId belonging to it, a country without states '
                    . 'refuses one; an unknown country or state answers 422. The postcode format is not checked '
                    . 'against the country. The shops of the context are the default of shopIds; the existence of '
                    . 'the shops is not checked. The picture of the store cannot be sent yet.',
            ),
        ),
        new CQRSGet(
            uriTemplate: '/stores/{storeId}',
            requirements: ['storeId' => '\d+'],
            CQRSQuery: GetStoreForEditing::class,
            scopes: ['store_read'],
            CQRSQueryMapping: self::QUERY_MAPPING,
            openapi: new OpenApiOperation(
                summary: 'Get a store.',
                description: 'Before PrestaShop 9.3.0 the core query only returns storeId and enabled: imageUrl is null '
                    . 'and the other fields are absent from the response.',
            ),
        ),
        new CQRSPartialUpdate(
            uriTemplate: '/stores/{storeId}',
            requirements: ['storeId' => '\d+'],
            validationContext: ['groups' => ['Default', 'Update']],
            CQRSCommand: EditStoreCommand::class,
            CQRSQuery: GetStoreForEditing::class,
            scopes: ['store_write'],
            CQRSQueryMapping: self::QUERY_MAPPING,
            CQRSCommandMapping: self::COMMAND_MAPPING,
            extraProperties: [
                // The store commands exist from PrestaShop 9.3.0 (PrestaShop#41414)
                'minVersion' => '9.3.0',
            ],
            openapi: new OpenApiOperation(
                summary: 'Update a store.',
                description: 'Requires PrestaShop 9.3.0. Partial update: only the sent fields change, except the '
                    . 'localized fields, which replace every language when sent (a language left out gets the value '
                    . 'of the default language for names and addresses, an empty value for the others): send all of '
                    . 'them, the default language included. A null stateId removes the state, a null phone, fax or '
                    . 'email leaves the field unchanged (an empty string clears it). The state consistency rules '
                    . 'of the creation apply to the resulting country and state. shopIds replaces the whole '
                    . 'association.',
            ),
        ),
        new CQRSUpdate(
            uriTemplate: '/stores/{storeId}/toggle-status',
            requirements: ['storeId' => '\d+'],
            output: false,
            allowEmptyBody: true,
            CQRSCommand: ToggleStoreStatusCommand::class,
            scopes: ['store_write'],
        ),
        new CQRSDelete(
            uriTemplate: '/stores/{storeId}',
            requirements: ['storeId' => '\d+'],
            CQRSCommand: DeleteStoreCommand::class,
            scopes: ['store_write'],
        ),
    ],
    normalizationContext: ['skip_null_values' => false],
    exceptionToStatus: [
        StoreNotFoundException::class => Response::HTTP_NOT_FOUND,
        // Invalid store id, unknown country, state inconsistent with the country or an empty shop association: the
        // core answers the same constraint exception for an unknown country or state as for an invalid one
        StoreConstraintException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
    ],
)]
class Store
{
    #[ApiProperty(identifier: true)]
    public int $storeId;

    #[LocalizedValue]
    #[DefaultLanguage(groups: ['Create'], fieldName: 'names')]
    // On update the field is optional, but when sent it replaces every language: the default one must be there,
    // the legacy model refuses an empty default language with a 500 (allowNull would skip a body without it)
    #[Assert\When(expression: 'value !== null', constraints: [new DefaultLanguage(fieldName: 'names')], groups: ['Update'])]
    #[Assert\All(constraints: [
        new TypedRegex(['type' => TypedRegex::TYPE_GENERIC_NAME]),
        new Assert\Length(max: 255),
    ])]
    public array $names;

    #[ApiProperty(openapiContext: [
        'description' => 'First line of the address of the store, by locale.',
        'example' => ['en-US' => '3030 SW 8th St', 'fr-FR' => '3030 SW 8th St'],
    ])]
    #[LocalizedValue]
    #[DefaultLanguage(groups: ['Create'], fieldName: 'addresses')]
    #[Assert\When(expression: 'value !== null', constraints: [new DefaultLanguage(fieldName: 'addresses')], groups: ['Update'])]
    #[Assert\All(constraints: [
        new TypedRegex(['type' => TypedRegex::TYPE_ADDRESS]),
        new Assert\Length(max: 255),
    ])]
    public array $addresses;

    #[ApiProperty(openapiContext: [
        'description' => 'Second line of the address of the store, by locale.',
        'example' => ['en-US' => 'Suite 4', 'fr-FR' => 'Suite 4'],
    ])]
    #[LocalizedValue]
    #[Assert\All(constraints: [
        new TypedRegex(['type' => TypedRegex::TYPE_ADDRESS]),
        new Assert\Length(max: 255),
    ])]
    public array $secondaryAddresses;

    #[ApiProperty(openapiContext: [
        'description' => 'Opening hours by locale: a list of exactly 7 strings, Monday first, each one "HH:MM | HH:MM" '
            . '(opening | closing) or empty when the store is closed. The front office reads 7 days per language: '
            . 'send the hours of every language, a language left out or an omitted field stores an empty value the '
            . 'store page cannot display yet.',
        'example' => [
            'en-US' => ['09:00 | 18:00', '09:00 | 18:00', '09:00 | 18:00', '09:00 | 18:00', '09:00 | 18:00', '10:00 | 16:00', ''],
            'fr-FR' => ['09:00 | 18:00', '09:00 | 18:00', '09:00 | 18:00', '09:00 | 18:00', '09:00 | 18:00', '10:00 | 16:00', ''],
        ],
    ])]
    #[LocalizedValue]
    #[Assert\All(constraints: [
        // Sequentially: the count and the items are only checked on a list
        new Assert\Sequentially(constraints: [
            new Assert\NotNull(),
            new Assert\Type('array'),
            new Assert\Count(exactly: 7),
            new Assert\All(constraints: [new Assert\Type('string')]),
        ]),
    ])]
    public array $hours;

    #[ApiProperty(openapiContext: [
        'description' => 'Note displayed with the store, by locale. HTML is allowed, scripts and event attributes '
            . 'are refused.',
        'example' => ['en-US' => 'Free parking', 'fr-FR' => 'Parking gratuit'],
    ])]
    #[LocalizedValue]
    #[Assert\All(constraints: [new CleanHtml()])]
    public array $notes;

    #[Assert\NotNull(groups: ['Create'])]
    #[Assert\Positive]
    public int $countryId;

    #[ApiProperty(openapiContext: [
        'description' => 'State of the store, required when the country contains states and refused otherwise; null '
            . 'when the store has none.',
    ])]
    #[Assert\Positive]
    public ?int $stateId;

    #[Assert\NotBlank(groups: ['Create'])]
    #[Assert\NotBlank(allowNull: true, groups: ['Update'])]
    #[TypedRegex(['type' => TypedRegex::TYPE_CITY_NAME])]
    #[Assert\Length(max: 64)]
    public string $city;

    #[ApiProperty(openapiContext: [
        'description' => 'Zip or postal code of the store, empty when there is none. Its format is not checked against '
            . 'the country.',
        'example' => '33135',
    ])]
    #[TypedRegex(['type' => TypedRegex::TYPE_POST_CODE])]
    #[Assert\Length(max: 12)]
    public string $postcode;

    #[ApiProperty(openapiContext: [
        'description' => 'Latitude of the store, between -90 and 90, stored with 8 decimals. Required on creation like '
            . 'in the back office; null on read when a store created from the back office has no coordinates, never '
            . 'null in a request body (omit the field on update to keep the stored value). The core does not check '
            . 'the bounds yet: a value out of range is stored, a value of 5 digits or more before the decimal point '
            . 'answers 500.',
        'example' => 25.765,
    ])]
    #[Assert\NotNull(groups: ['Create'])]
    public ?DecimalNumber $latitude;

    #[ApiProperty(openapiContext: [
        'description' => 'Longitude of the store, between -180 and 180, stored with 8 decimals. Required on creation '
            . 'like in the back office; null on read when a store created from the back office has no coordinates, '
            . 'never null in a request body (omit the field on update to keep the stored value). The core does not '
            . 'check the bounds yet: a value out of range is stored, a value of 5 digits or more before the decimal '
            . 'point answers 500.',
        'example' => -80.2436,
    ])]
    #[Assert\NotNull(groups: ['Create'])]
    public ?DecimalNumber $longitude;

    #[ApiProperty(openapiContext: [
        'description' => 'Phone number of the store, null when there is none.',
        'example' => '+1 305 555 0100',
    ])]
    #[TypedRegex(['type' => TypedRegex::TYPE_PHONE_NUMBER])]
    #[Assert\Length(max: 16)]
    public ?string $phone;

    #[ApiProperty(openapiContext: [
        'description' => 'Fax number of the store, null when there is none.',
        'example' => '+1 305 555 0101',
    ])]
    #[TypedRegex(['type' => TypedRegex::TYPE_PHONE_NUMBER])]
    #[Assert\Length(max: 16)]
    public ?string $fax;

    #[ApiProperty(openapiContext: [
        'description' => 'Contact e-mail address of the store, null when there is none.',
        'example' => 'miami@example.com',
    ])]
    #[Assert\Email(mode: Assert\Email::VALIDATION_MODE_STRICT)]
    #[Assert\Length(max: 255)]
    public ?string $email;

    #[Assert\NotNull(groups: ['Create'])]
    public bool $enabled;

    /**
     * Read only: the picture of a store is uploaded from the back office, the API cannot receive it yet. The default
     * keeps the property initialized when the core maps no value (before 9.3.0, or a null mapped by the core).
     */
    #[ApiProperty(
        writable: false,
        openapiContext: [
            'description' => 'URL of the picture of the store, null when it has none. Read only.',
        ],
    )]
    public ?string $imageUrl = null;

    /**
     * @var int[]
     */
    #[ApiProperty(openapiContext: [
        'description' => 'Shops associated with the store. On creation, defaults to the shops of the context. An '
            . 'empty list is refused; the existence of the shops is not checked yet (an unknown shop is stored), and '
            . 'the core adds the shops of the context back on the next update.',
        'example' => [1, 3],
    ])]
    #[Assert\NotBlank(allowNull: true)]
    #[Assert\All([new Assert\Type('integer'), new Assert\Positive()])]
    public array $shopIds;

    /**
     * The values the back office form applies to the untouched fields, copied from the command signature.
     */
    public const CREATE_DEFAULT_VALUES = [
        'secondaryAddresses' => [],
        'hours' => [],
        'notes' => [],
        'stateId' => null,
        'postcode' => '',
        'phone' => null,
        'fax' => null,
        'email' => null,
        'enabled' => true,
        'shopIds' => '[_context][shopIds]',
    ];

    public const COMMAND_MAPPING = [
        '[names]' => '[localizedNames]',
        '[addresses]' => '[localizedAddress1]',
        '[secondaryAddresses]' => '[localizedAddress2]',
        '[hours]' => '[localizedHours]',
        '[notes]' => '[localizedNotes]',
        '[enabled]' => '[active]',
        '[shopIds]' => '[shopAssociation]',
    ];

    public const QUERY_MAPPING = [
        '[localizedNames]' => '[names]',
        '[localizedAddress1]' => '[addresses]',
        '[localizedAddress2]' => '[secondaryAddresses]',
        '[localizedHours]' => '[hours]',
        '[localizedNotes]' => '[notes]',
        '[active]' => '[enabled]',
        '[imagePath]' => '[imageUrl]',
        '[shopAssociation]' => '[shopIds]',
    ];
}
