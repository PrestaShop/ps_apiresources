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

namespace PrestaShop\Module\APIResources\ApiPlatform\Resources\Tax;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use PrestaShop\Decimal\DecimalNumber;
use PrestaShop\PrestaShop\Core\Search\Filters\TaxFilters;
use PrestaShopBundle\ApiPlatform\Metadata\PaginatedList;
use PrestaShopBundle\ApiPlatform\Provider\QueryListProvider;

#[ApiResource(
    operations: [
        new PaginatedList(
            uriTemplate: '/taxes',
            scopes: ['tax_read'],
            ApiResourceMapping: [
                '[id_tax]' => '[taxId]',
                '[active]' => '[enabled]',
            ],
            gridDataFactory: 'prestashop.core.grid.data_factory.tax',
            provider: QueryListProvider::class,
            filtersClass: TaxFilters::class,
            filtersMapping: [
                '[taxId]' => '[id_tax]',
                '[enabled]' => '[active]',
            ],
        ),
    ],
    normalizationContext: ['skip_null_values' => false],
)]
class TaxList
{
    #[ApiProperty(identifier: true)]
    public int $taxId;

    public string $name;

    public DecimalNumber $rate;

    public bool $enabled;

    public function setRate(string $rate): self
    {
        $this->rate = new DecimalNumber($rate);

        return $this;
    }
}
