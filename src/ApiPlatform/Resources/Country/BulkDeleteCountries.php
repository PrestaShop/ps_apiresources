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
use PrestaShop\PrestaShop\Core\Domain\Country\Exception\CannotDeleteCountryException;
use PrestaShop\PrestaShop\Core\Domain\Country\Exception\CountryConstraintException;
use PrestaShop\PrestaShop\Core\Domain\Country\Exception\CountryException;
use PrestaShop\PrestaShop\Core\Domain\Country\Exception\CountryNotFoundException;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSDelete;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    operations: [
        new CQRSDelete(
            uriTemplate: '/countries/bulk-delete',
            CQRSCommand: BulkDeleteCountriesCommand::class,
            scopes: ['country_write'],
            allowEmptyBody: false,
            extraProperties: [
                // The bulk command exists from PrestaShop 9.2.0
                'minVersion' => '9.2.0',
            ],
            openapi: new OpenApiOperation(
                summary: 'Delete several countries.',
                description: 'Deletes the listed countries. A country that cannot be deleted (unknown, or the default '
                    . 'country of the shop from PrestaShop 9.2.1) does not stop the others: the response is a 207 '
                    . 'with one error per failed country, each with its own status (404 unknown, 422 refused).',
            ),
        ),
    ],
    exceptionToStatus: [
        CountryNotFoundException::class => Response::HTTP_NOT_FOUND,
        CountryConstraintException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        CannotDeleteCountryException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        CountryException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
    ],
)]
class BulkDeleteCountries
{
    /**
     * @var int[]
     */
    #[ApiProperty(openapiContext: ['example' => [1, 3]])]
    #[Assert\NotBlank]
    #[Assert\All([new Assert\Type('integer'), new Assert\Positive()])]
    public array $countryIds;
}
