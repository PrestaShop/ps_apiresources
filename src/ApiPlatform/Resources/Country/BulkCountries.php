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
use PrestaShop\PrestaShop\Core\Domain\Country\Command\BulkDeleteCountriesCommand;
use PrestaShop\PrestaShop\Core\Domain\Country\Command\BulkToggleCountriesStatusCommand;
use PrestaShop\PrestaShop\Core\Domain\Country\Command\BulkUpdateCountryZoneCommand;
use PrestaShop\PrestaShop\Core\Domain\Country\Exception\CannotDeleteCountryException;
use PrestaShop\PrestaShop\Core\Domain\Country\Exception\CountryConstraintException;
use PrestaShop\PrestaShop\Core\Domain\Country\Exception\CountryException;
use PrestaShop\PrestaShop\Core\Domain\Country\Exception\CountryNotFoundException;
use PrestaShop\PrestaShop\Core\Domain\Zone\Exception\ZoneException;
use PrestaShop\PrestaShop\Core\Domain\Zone\Exception\ZoneNotFoundException;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSDelete;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSUpdate;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * The bulk operations of the domain share this class: the body of each operation is documented from its
 * command, so only the fields that command takes appear; the validation groups keep the field of one
 * operation optional for the others.
 */
#[ApiResource(
    operations: [
        new CQRSDelete(
            uriTemplate: '/countries/bulk-delete',
            CQRSCommand: BulkDeleteCountriesCommand::class,
            scopes: ['country_write'],
            allowEmptyBody: false,
            extraProperties: [
                // The bulk commands exist from PrestaShop 9.2.0
                'minVersion' => '9.2.0',
            ],
            openapi: new OpenApiOperation(
                summary: 'Delete several countries.',
                description: 'Deletes the listed countries. A country that cannot be deleted (unknown, or the default '
                    . 'country of the shop from PrestaShop 9.2.1) does not stop the others: the response is a 207 '
                    . 'with one error per failed country, each with its own status (404 unknown, 422 refused).',
            ),
        ),
        new CQRSUpdate(
            uriTemplate: '/countries/bulk-update-status',
            output: false,
            validationContext: ['groups' => ['Default', 'BulkUpdateStatus']],
            CQRSCommand: BulkToggleCountriesStatusCommand::class,
            CQRSCommandMapping: [
                '[enabled]' => '[expectedStatus]',
            ],
            scopes: ['country_write'],
            extraProperties: [
                'minVersion' => '9.2.0',
            ],
            openapi: new OpenApiOperation(
                summary: 'Enable or disable several countries.',
                description: 'Sets the status of the listed countries. An unknown country does not stop the '
                    . 'others: the response is a 207 with one 404 error per unknown country.',
            ),
        ),
        new CQRSUpdate(
            uriTemplate: '/countries/bulk-update-zone',
            output: false,
            validationContext: ['groups' => ['Default', 'BulkUpdateZone']],
            CQRSCommand: BulkUpdateCountryZoneCommand::class,
            CQRSCommandMapping: [
                '[zoneId]' => '[newZoneId]',
            ],
            scopes: ['country_write'],
            extraProperties: [
                'minVersion' => '9.2.0',
            ],
            openapi: new OpenApiOperation(
                summary: 'Move several countries to a zone.',
                description: 'Sets the zone of the listed countries. An unknown zone answers 404 and nothing is '
                    . 'changed; an unknown country does not stop the others: the response is a 207 with one 404 '
                    . 'error per unknown country.',
            ),
        ),
    ],
    exceptionToStatus: [
        CountryNotFoundException::class => Response::HTTP_NOT_FOUND,
        // The first matching class wins: ZoneNotFoundException extends ZoneException
        ZoneNotFoundException::class => Response::HTTP_NOT_FOUND,
        ZoneException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        CountryConstraintException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        CannotDeleteCountryException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        CountryException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
    ],
)]
class BulkCountries
{
    /**
     * @var int[]
     */
    #[ApiProperty(openapiContext: ['example' => [1, 3]])]
    #[Assert\NotBlank]
    #[Assert\All([new Assert\Type('integer'), new Assert\Positive()])]
    public array $countryIds;

    #[ApiProperty(openapiContext: ['description' => 'Status to set on every listed country.'])]
    #[Assert\NotNull(groups: ['BulkUpdateStatus'])]
    public bool $enabled;

    #[ApiProperty(openapiContext: ['description' => 'Zone to set on every listed country.'])]
    #[Assert\NotNull(groups: ['BulkUpdateZone'])]
    #[Assert\Positive]
    public int $zoneId;
}
