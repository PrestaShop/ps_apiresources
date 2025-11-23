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

namespace PrestaShop\Module\APIResources\ApiPlatform\Resources\Supplier;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use PrestaShop\PrestaShop\Core\Domain\Supplier\Exception\SupplierNotFoundException;
use PrestaShop\PrestaShop\Core\Search\Filters\SupplierFilters;
use PrestaShopBundle\ApiPlatform\Metadata\PaginatedList;
use PrestaShopBundle\ApiPlatform\Provider\QueryListProvider;
use Symfony\Component\HttpFoundation\Response;

#[ApiResource(
    operations: [
        new PaginatedList(
            uriTemplate: '/suppliers',
            provider: QueryListProvider::class,
            scopes: ['supplier_read'],
            ApiResourceMapping: [
                '[id_supplier]' => '[supplierId]',
                '[active]' => '[enabled]',
                '[products_count]' => '[productsCount]',
            ],
            gridDataFactory: 'prestashop.core.grid.factory.supplier_decorator',
            filtersClass: SupplierFilters::class,
        ),
    ],
    exceptionToStatus: [
        SupplierNotFoundException::class => Response::HTTP_NOT_FOUND,
    ],
)]
class SupplierList
{
    #[ApiProperty(identifier: true)]
    public int $supplierId;

    public string $name;

    public bool $enabled;

    public int $productsCount;

    public ?string $logo;
}
