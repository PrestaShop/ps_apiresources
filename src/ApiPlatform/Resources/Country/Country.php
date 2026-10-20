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

namespace PrestaShop\Module\APIResources\ApiPlatform\Resources\Country;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use PrestaShop\Module\APIResources\Validation\Constraints\ValidAddressFormat;
use PrestaShop\PrestaShop\Core\ConstraintValidator\Constraints\DefaultLanguage;
use PrestaShop\PrestaShop\Core\ConstraintValidator\Constraints\TypedRegex;
use PrestaShop\PrestaShop\Core\Domain\Country\Command\AddCountryCommand;
use PrestaShop\PrestaShop\Core\Domain\Country\Command\DeleteCountryCommand;
use PrestaShop\PrestaShop\Core\Domain\Country\Command\EditCountryCommand;
use PrestaShop\PrestaShop\Core\Domain\Country\Command\ToggleCountryStatusCommand;
use PrestaShop\PrestaShop\Core\Domain\Country\Exception\CannotDeleteCountryException;
use PrestaShop\PrestaShop\Core\Domain\Country\Exception\CountryConstraintException;
use PrestaShop\PrestaShop\Core\Domain\Country\Exception\CountryNotFoundException;
use PrestaShop\PrestaShop\Core\Domain\Country\Exception\DuplicateCountryIsoCodeException;
use PrestaShop\PrestaShop\Core\Domain\Country\Query\GetCountryForEditing;
use PrestaShop\PrestaShop\Core\Domain\Zone\Exception\ZoneException;
use PrestaShop\PrestaShop\Core\Domain\Zone\Exception\ZoneNotFoundException;
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
            uriTemplate: '/countries',
            validationContext: ['groups' => ['Default', 'Create']],
            CQRSCommand: AddCountryCommand::class,
            CQRSQuery: GetCountryForEditing::class,
            scopes: ['country_write'],
            CQRSQueryMapping: self::QUERY_MAPPING,
            CQRSCommandMapping: self::COMMAND_MAPPING,
            extraProperties: [
                // The defaults of the back office form, applied before the validation from PrestaShop 9.2.0
                // (the fields stay required on older versions)
                'defaultValues' => self::CREATE_DEFAULT_VALUES,
            ],
        ),
        new CQRSGet(
            uriTemplate: '/countries/{countryId}',
            requirements: ['countryId' => '\d+'],
            CQRSQuery: GetCountryForEditing::class,
            scopes: ['country_read'],
            CQRSQueryMapping: self::QUERY_MAPPING,
        ),
        new CQRSPartialUpdate(
            uriTemplate: '/countries/{countryId}',
            requirements: ['countryId' => '\d+'],
            validationContext: ['groups' => ['Default', 'Update']],
            CQRSCommand: EditCountryCommand::class,
            CQRSQuery: GetCountryForEditing::class,
            scopes: ['country_write'],
            CQRSQueryMapping: self::QUERY_MAPPING,
            CQRSCommandMapping: self::COMMAND_MAPPING,
            openapi: new OpenApiOperation(
                summary: 'Update a country.',
                description: 'Partial update: only the sent fields change. Before PrestaShop 9.2.0 the body must also '
                    . 'carry names, the core edit command does not accept a partial body without it.',
            ),
        ),
        new CQRSUpdate(
            uriTemplate: '/countries/{countryId}/toggle-status',
            requirements: ['countryId' => '\d+'],
            allowEmptyBody: true,
            CQRSCommand: ToggleCountryStatusCommand::class,
            CQRSQuery: GetCountryForEditing::class,
            scopes: ['country_write'],
            CQRSQueryMapping: self::QUERY_MAPPING,
            extraProperties: [
                // The toggle command exists from PrestaShop 9.2.0
                'minVersion' => '9.2.0',
            ],
            openapi: new OpenApiOperation(
                summary: 'Toggle the status of a country.',
                description: 'Flips the enabled status without a body and returns the updated country. To set a '
                    . 'given status, send enabled to PATCH /countries/{countryId}.',
            ),
        ),
        new CQRSDelete(
            uriTemplate: '/countries/{countryId}',
            requirements: ['countryId' => '\d+'],
            CQRSCommand: DeleteCountryCommand::class,
            scopes: ['country_write'],
        ),
    ],
    normalizationContext: ['skip_null_values' => false],
    exceptionToStatus: [
        CountryNotFoundException::class => Response::HTTP_NOT_FOUND,
        // The first matching class wins: ZoneNotFoundException extends ZoneException; the core checks
        // that the zone exists from PrestaShop 9.2.1, older versions store an unknown zone id
        ZoneNotFoundException::class => Response::HTTP_NOT_FOUND,
        ZoneException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        // Invalid ISO code, call prefix, zip code format or address format
        CountryConstraintException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        // The ISO code is unique from PrestaShop 9.2.0 (older versions create the duplicate silently)
        DuplicateCountryIsoCodeException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        // The default country of the shop cannot be deleted (PrestaShop 9.2.1, a 500 before)
        CannotDeleteCountryException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
    ],
)]
class Country
{
    #[ApiProperty(identifier: true)]
    public int $countryId;

    #[LocalizedValue]
    #[DefaultLanguage(groups: ['Create'], fieldName: 'names')]
    #[DefaultLanguage(groups: ['Update'], fieldName: 'names', allowNull: true)]
    #[Assert\All(constraints: [
        new TypedRegex(['type' => TypedRegex::TYPE_GENERIC_NAME]),
        new Assert\Length(max: 64),
    ])]
    public array $names;

    #[ApiProperty(openapiContext: [
        'description' => 'Two-letter ISO 3166-1 code, stored upper-cased. PrestaShop 9.2.0 and later refuse a code '
            . 'already used by another country (422), older versions create the duplicate.',
        'example' => 'FR',
    ])]
    #[Assert\NotBlank(groups: ['Create'])]
    #[Assert\NotBlank(allowNull: true, groups: ['Update'])]
    #[TypedRegex(['type' => TypedRegex::TYPE_LANGUAGE_ISO_CODE])]
    #[Assert\Length(max: 2)]
    public string $isoCode;

    #[ApiProperty(openapiContext: [
        'description' => 'International call prefix of the country, 0 when there is none.',
        'example' => 33,
    ])]
    #[Assert\NotNull(groups: ['Create'])]
    #[Assert\PositiveOrZero]
    public int $callPrefix;

    #[ApiProperty(openapiContext: [
        'description' => 'Default currency of the country, 0 for the default currency of the shop. The existence of '
            . 'the currency is not checked.',
    ])]
    #[Assert\NotNull(groups: ['Create'])]
    #[Assert\PositiveOrZero]
    public int $defaultCurrencyId;

    #[ApiProperty(openapiContext: [
        'description' => 'Zone of the country. An unknown zone answers 404 from PrestaShop 9.2.1, older versions store '
            . 'it and the country disappears from the list.',
    ])]
    #[Assert\NotNull(groups: ['Create'])]
    #[Assert\Positive]
    public int $zoneId;

    #[Assert\NotNull(groups: ['Create'])]
    public bool $needZipCode;

    #[ApiProperty(openapiContext: [
        'description' => 'Format of the zip codes of the country: L for a letter, N for a digit, C for the ISO code '
            . 'of the country, spaces and dashes (NNNNN, LNNNN, C-NNNN); null when the zip code is not checked.',
        'example' => 'NNNNN',
    ])]
    #[TypedRegex(['type' => TypedRegex::TYPE_ZIP_CODE_FORMAT])]
    #[Assert\Length(max: 12)]
    public ?string $zipCodeFormat;

    #[ApiProperty(openapiContext: [
        'description' => 'Layout of the addresses of the country, one line per row of the address. The tokens are '
            . 'the address fields (firstname, lastname, company, vat_number, address1, address2, postcode, city, '
            . 'phone, dni...) and the pickers of a related entity (Country:name, State:name); firstname, lastname, '
            . 'address1, city and Country:name are required. Stored and returned from PrestaShop 9.2.0, ignored on '
            . 'write and empty on read before.',
        'example' => "firstname lastname\naddress1\npostcode city\nCountry:name",
    ])]
    #[Assert\NotBlank(groups: ['Create'])]
    #[Assert\Length(max: 255)]
    #[ValidAddressFormat(groups: ['Create', 'Update'])]
    public string $addressFormat;

    #[Assert\NotNull(groups: ['Create'])]
    public bool $enabled;

    #[Assert\NotNull(groups: ['Create'])]
    public bool $containsStates;

    #[ApiProperty(openapiContext: [
        'description' => 'Whether the addresses of the country require an identification number (DNI).',
    ])]
    #[Assert\NotNull(groups: ['Create'])]
    public bool $needIdNumber;

    #[Assert\NotNull(groups: ['Create'])]
    public bool $displayTaxLabel;

    /**
     * @var int[]
     */
    #[ApiProperty(openapiContext: [
        'description' => 'Shops associated with the country. On creation, optional from PrestaShop 9.2.0 where it '
            . 'defaults to the shops of the context, required on older versions. The existence of the shops is '
            . 'not checked.',
        'example' => [1, 3],
    ])]
    // Never fires from PrestaShop 9.2.0, the default value is set before the validation
    #[Assert\NotNull(groups: ['Create'])]
    #[Assert\NotBlank(allowNull: true)]
    #[Assert\All([new Assert\Type('integer'), new Assert\Positive()])]
    public array $shopIds;

    /**
     * The values the back office form applies to the untouched fields, copied from the command signature.
     */
    public const CREATE_DEFAULT_VALUES = [
        'defaultCurrencyId' => 0,
        'needZipCode' => false,
        'zipCodeFormat' => null,
        'enabled' => true,
        'containsStates' => false,
        'needIdNumber' => false,
        'displayTaxLabel' => true,
        'shopIds' => '[_context][shopIds]',
    ];

    public const COMMAND_MAPPING = [
        '[names]' => '[localizedNames]',
        '[defaultCurrencyId]' => '[defaultCurrency]',
        '[shopIds]' => '[shopAssociation]',
    ];

    public const QUERY_MAPPING = [
        '[localizedNames]' => '[names]',
        '[defaultCurrency]' => '[defaultCurrencyId]',
        '[zone]' => '[zoneId]',
        '[shopAssociation]' => '[shopIds]',
    ];
}
