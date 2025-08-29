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

namespace PrestaShop\Module\APIResources\ApiPlatform\Resources\Manufacturer;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use PrestaShop\PrestaShop\Core\Domain\Manufacturer\Exception\ManufacturerConstraintException;
use PrestaShop\PrestaShop\Core\Domain\Manufacturer\Exception\ManufacturerException;
use PrestaShop\PrestaShop\Core\Domain\Manufacturer\Exception\ManufacturerNotFoundException;
use PrestaShop\PrestaShop\Core\Domain\Manufacturer\Query\GetManufacturerForViewing;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSGet;
use Symfony\Component\HttpFoundation\Response;

#[ApiResource(
    operations: [
        // GET /manufacturer/{manufacturerId}/details/{languageId}
        new CQRSGet(
            uriTemplate: '/manufacturer/{manufacturerId}/details/{languageId}',
            requirements: [
                'manufacturerId' => '\d+',
                'languageId' => '\d+',
            ],
            CQRSQuery: GetManufacturerForViewing::class,
            scopes: ['manufacturer_read'],
            CQRSQueryMapping: self::QUERY_MAPPING,
        ),
    ],
    exceptionToStatus: [
        ManufacturerConstraintException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        ManufacturerNotFoundException::class => Response::HTTP_NOT_FOUND,
        ManufacturerException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
    ],
)]
class ManufacturerDetail
{
    #[ApiProperty(identifier: true, readable: false)]
    public int $manufacturerId = 0;

    #[ApiProperty(identifier: true, readable: false)]
    public int $languageId = 0;

    public string $name;

    public array $products = [];

    public array $addresses = [];

    public const QUERY_MAPPING = [
        'manufacturerProducts' => 'products',
        'manufacturerAddresses' => 'addresses',
    ];
}
