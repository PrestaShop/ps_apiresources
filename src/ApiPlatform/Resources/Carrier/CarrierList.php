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

namespace PrestaShop\Module\APIResources\ApiPlatform\Resources\Carrier;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use PrestaShop\PrestaShop\Core\Domain\Carrier\Exception\CarrierNotFoundException;
use PrestaShop\PrestaShop\Core\Search\Filters\CarrierFilters;
use PrestaShopBundle\ApiPlatform\Metadata\PaginatedList;
use PrestaShopBundle\ApiPlatform\Provider\QueryListProvider;
use Symfony\Component\HttpFoundation\Response;

#[ApiResource(
    operations: [
        new PaginatedList(
            uriTemplate: '/carriers',
            provider: QueryListProvider::class,
            scopes: ['carrier_read'],
            ApiResourceMapping: self::MAPPING,
            // The decorated factory is the one used by the carriers list of the BO: on top of the carrier columns it
            // resolves the logo of each carrier, and falls back to the shop name for the carriers named "0"
            gridDataFactory: 'prestashop.core.grid.data.factory.carrier_decorator',
            filtersClass: CarrierFilters::class,
            filtersMapping: self::FILTERS_MAPPING,
        ),
    ],
    // The nullable fields are part of the contract, they are listed with a null value rather than omitted
    normalizationContext: ['skip_null_values' => false],
    exceptionToStatus: [
        CarrierNotFoundException::class => Response::HTTP_NOT_FOUND,
    ],
)]
class CarrierList
{
    #[ApiProperty(identifier: true)]
    public int $carrierId;

    public string $name;

    /**
     * Shipping delay in the language of the list, null when the carrier has none in that language.
     */
    public ?string $delay;

    public bool $enabled;

    public bool $free;

    /**
     * The list is ordered by position by default, which is the order the carriers are offered in.
     */
    public int $position;

    /**
     * Technical name of the module providing the carrier, null for the carriers of the shop itself.
     */
    public ?string $moduleName;

    /**
     * Url of the carrier logo, null when it has none.
     */
    public ?string $logoUrl;

    /**
     * Maps the columns returned by CarrierQueryBuilder, and the logo added by the decorator, to the API field names.
     */
    public const MAPPING = [
        '[id_carrier]' => '[carrierId]',
        '[active]' => '[enabled]',
        '[is_free]' => '[free]',
        '[external_module_name]' => '[moduleName]',
        '[logo]' => '[logoUrl]',
    ];

    /**
     * Maps the API field names used in filters and orderBy back to the grid filter names.
     */
    public const FILTERS_MAPPING = [
        '[carrierId]' => '[id_carrier]',
        '[enabled]' => '[active]',
        '[free]' => '[is_free]',
        '[moduleName]' => '[external_module_name]',
    ];
}
