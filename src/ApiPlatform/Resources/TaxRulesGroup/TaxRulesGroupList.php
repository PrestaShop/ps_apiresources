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

namespace PrestaShop\Module\APIResources\ApiPlatform\Resources\TaxRulesGroup;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use PrestaShop\PrestaShop\Core\Domain\TaxRulesGroup\Exception\TaxRulesGroupNotFoundException;
use PrestaShop\PrestaShop\Core\Search\Filters\TaxRulesGroupFilters;
use PrestaShopBundle\ApiPlatform\Metadata\PaginatedList;
use PrestaShopBundle\ApiPlatform\Provider\QueryListProvider;
use Symfony\Component\HttpFoundation\Response;

#[ApiResource(
    operations: [
        new PaginatedList(
            uriTemplate: '/tax-rules-groups',
            provider: QueryListProvider::class,
            scopes: ['tax_rules_group_read'],
            ApiResourceMapping: [
                '[id_tax_rules_group]' => '[taxRulesGroupId]',
                '[active]' => '[enabled]',
            ],
            gridDataFactory: 'prestashop.core.grid.data.factory.tax_rules_group',
            filtersClass: TaxRulesGroupFilters::class,
        ),
    ],
    exceptionToStatus: [
        TaxRulesGroupNotFoundException::class => Response::HTTP_NOT_FOUND,
    ],
)]
class TaxRulesGroupList
{
    #[ApiProperty(identifier: true)]
    public int $taxRulesGroupId;

    public string $name;

    public bool $enabled;
}
