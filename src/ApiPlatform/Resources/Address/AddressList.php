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

namespace PrestaShop\Module\APIResources\ApiPlatform\Resources\Address;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use PrestaShop\PrestaShop\Core\Domain\Address\Exception\AddressNotFoundException;
use PrestaShop\PrestaShop\Core\Search\Filters\AddressFilters;
use PrestaShopBundle\ApiPlatform\Metadata\PaginatedList;
use Symfony\Component\HttpFoundation\Response;

#[ApiResource(
    operations: [
        new PaginatedList(
            uriTemplate: '/addresses',
            scopes: [
                'address_read',
            ],
            ApiResourceMapping: self::MAPPING,
            gridDataFactory: 'prestashop.core.grid.data.factory.address',
            filtersClass: AddressFilters::class,
            filtersMapping: [
                '[addressId]' => '[id_address]',
            ],
        ),
    ],
    exceptionToStatus: [
        AddressNotFoundException::class => Response::HTTP_NOT_FOUND,
    ],
)]
class AddressList
{
    #[ApiProperty(identifier: true)]
    public int $addressId;

    public string $firstname;

    public string $lastname;

    public string $address1;

    public string $postcode;

    public string $city;

    public string $country_name;

    public const MAPPING = [
        '[id_address]' => '[addressId]',
    ];
}
