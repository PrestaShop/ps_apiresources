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
use PrestaShop\PrestaShop\Core\Domain\Country\Exception\CountryNotFoundException;
use PrestaShop\PrestaShop\Core\Domain\Country\Query\GetCountryForEditing;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSGet;
use PrestaShopBundle\ApiPlatform\Metadata\LocalizedValue;
use Symfony\Component\HttpFoundation\Response;

#[ApiResource(
    operations: [
        new CQRSGet(
            uriTemplate: '/countries/{countryId}',
            requirements: ['countryId' => '\d+'],
            CQRSQuery: GetCountryForEditing::class,
            scopes: ['country_read'],
            CQRSQueryMapping: self::QUERY_MAPPING,
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
    public array $names;

    public string $isoCode;

    public int $callPrefix;

    public int $defaultCurrencyId;

    public int $zoneId;

    public bool $needZipCode;

    public ?string $zipCodeFormat;

    public string $addressFormat;

    public bool $enabled;

    public bool $containsStates;

    public bool $needIdNumber;

    public bool $displayTaxLabel;

    public array $shopIds;

    public const QUERY_MAPPING = [
        '[localizedNames]' => '[names]',
        '[defaultCurrency]' => '[defaultCurrencyId]',
        '[zone]' => '[zoneId]',
        '[zipCodeFormat][value]' => '[zipCodeFormat]',
        '[shopAssociation]' => '[shopIds]',
    ];
}
