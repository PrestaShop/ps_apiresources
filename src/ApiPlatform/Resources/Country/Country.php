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
use PrestaShop\PrestaShop\Core\Domain\Country\Command\EditCountryCommand;
use PrestaShop\PrestaShop\Core\Domain\Country\Exception\CountryConstraintException;
use PrestaShop\PrestaShop\Core\Domain\Country\Exception\CountryNotFoundException;
use PrestaShop\PrestaShop\Core\Domain\Country\Query\GetCountryForEditing;
use PrestaShop\PrestaShop\Core\Domain\Country\Query\GetCountryRequiredFields;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSGet;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSPartialUpdate;
use PrestaShopBundle\ApiPlatform\Metadata\LocalizedValue;
use Symfony\Component\HttpFoundation\Response;

#[ApiResource(
    operations: [
        new CQRSGet(
            uriTemplate: '/countries/{countryId}/required-fields',
            requirements: ['countryId' => '\d+'],
            openapiContext: ['summary' => 'Get country required fields', 'description' => 'Retrieves the required fields for a country'],
            CQRSQuery: GetCountryRequiredFields::class,
            scopes: [
                'country_read',
            ],
        ),
        new CQRSPartialUpdate(
            uriTemplate: '/countries/{countryId}',
            requirements: ['countryId' => '\d+'],
            openapiContext: ['summary' => 'Edit country', 'description' => 'Updates a country configuration'],
            CQRSCommand: EditCountryCommand::class,
            CQRSQuery: GetCountryForEditing::class,
            scopes: [
                'country_write',
            ],
        ),
    ],
    exceptionToStatus: [
        CountryNotFoundException::class => Response::HTTP_NOT_FOUND,
        CountryConstraintException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
    ],
)]
class Country
{
    #[ApiProperty(identifier: true)]
    public int $countryId;

    #[LocalizedValue]
    public array $localizedNames;

    public string $isoCode;

    public int $callPrefix;

    public int $defaultCurrency;

    public ?int $zoneId = null;

    public bool $needZipCode;

    public ?string $zipCodeFormat = null;

    public string $addressFormat;

    public bool $enabled;

    public bool $containsStates;

    public bool $needIdNumber;

    public bool $displayTaxLabel;

    #[ApiProperty(openapiContext: ['type' => 'array', 'items' => ['type' => 'integer'], 'example' => [1, 2]])]
    public array $shopAssociation;
}
