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
use PrestaShop\PrestaShop\Core\ConstraintValidator\Constraints\DefaultLanguage;
use PrestaShop\PrestaShop\Core\Domain\Country\Command\AddCountryCommand;
use PrestaShop\PrestaShop\Core\Domain\Country\Exception\CountryNotFoundException;
use PrestaShop\PrestaShop\Core\Domain\Country\Query\GetCountryForEditing;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSCreate;
use PrestaShop\PrestaShop\Core\Domain\Country\Command\DeleteCountryCommand;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSGet;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSDelete;
use PrestaShopBundle\ApiPlatform\Metadata\LocalizedValue;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    operations: [
        new CQRSCreate(
            uriTemplate: '/countries',
            validationContext: ['groups' => ['Default', 'Create']],
            CQRSCommand: AddCountryCommand::class,
            scopes: ['country_write'],
            CQRSCommandMapping: self::COMMAND_MAPPING,
        ),
        new CQRSGet(
            uriTemplate: '/countries/{countryId}',
            requirements: ['countryId' => '\d+'],
            CQRSQuery: GetCountryForEditing::class,
            scopes: ['country_read'],
            CQRSQueryMapping: self::QUERY_MAPPING,
        ),
        new CQRSDelete(
            uriTemplate: '/countries/{countryId}',
            CQRSCommand: DeleteCountryCommand::class,
            scopes: ['country_read'],
        ),
    ],
    normalizationContext: ['skip_null_values' => false],
    exceptionToStatus: [
        CountryNotFoundException::class => Response::HTTP_NOT_FOUND,
    ],
)]
class Country
{
    #[ApiProperty(identifier: true)]
    public int $countryId;

    #[LocalizedValue]
    #[DefaultLanguage(groups: ['Create'], fieldName: 'names')]
    public array $names;

    #[Assert\NotBlank(groups: ['Create'])]
    #[Assert\Length(max: 3)]
    public string $isoCode;

    #[Assert\NotNull(groups: ['Create'])]
    public int $callPrefix;

    #[Assert\NotNull(groups: ['Create'])]
    public int $defaultCurrencyId;

    #[Assert\NotNull(groups: ['Create'])]
    public int $zoneId;

    #[Assert\NotNull(groups: ['Create'])]
    public bool $needZipCode;

    public ?string $zipCodeFormat;

    #[Assert\NotNull(groups: ['Create'])]
    public string $addressFormat;

    #[Assert\NotNull(groups: ['Create'])]
    public bool $enabled;

    #[Assert\NotNull(groups: ['Create'])]
    public bool $containsStates;

    #[Assert\NotNull(groups: ['Create'])]
    public bool $needIdNumber;

    #[Assert\NotNull(groups: ['Create'])]
    public bool $displayTaxLabel;

    #[ApiProperty(openapiContext: ['type' => 'array', 'items' => ['type' => 'integer'], 'example' => [1, 3]])]
    public array $shopIds;

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
