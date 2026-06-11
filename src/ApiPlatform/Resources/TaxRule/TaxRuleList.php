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

namespace PrestaShop\Module\APIResources\ApiPlatform\Resources\TaxRule;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use PrestaShop\Decimal\DecimalNumber;
use PrestaShop\PrestaShop\Core\Search\Filters\TaxRuleFilters;
use PrestaShopBundle\ApiPlatform\Metadata\PaginatedList;
use PrestaShopBundle\ApiPlatform\Provider\QueryListProvider;

#[ApiResource(
    operations: [
        new PaginatedList(
            uriTemplate: '/tax-rules',
            provider: QueryListProvider::class,
            scopes: ['tax_rule_read'],
            ApiResourceMapping: [
                '[id_tax_rule]' => '[taxRuleId]',
                '[id_tax_rules_group]' => '[taxRulesGroupId]',
                '[country_id]' => '[countryId]',
                '[country_name]' => '[countryName]',
                '[state_id]' => '[stateId]',
                '[state_name]' => '[stateName]',
                '[tax_name]' => '[taxName]',
            ],
            gridDataFactory: 'prestashop.core.grid.data.factory.tax_rule',
            filtersClass: TaxRuleFilters::class,
            filtersMapping: [
                '[taxRuleId]' => '[id_tax_rule]',
                '[taxRulesGroupId]' => '[id_tax_rules_group]',
                '[countryId]' => '[country_id]',
                '[countryName]' => '[country_name]',
                '[stateId]' => '[state_id]',
                '[stateName]' => '[state_name]',
                '[taxName]' => '[tax_name]',
            ],
        ),
    ],
)]
class TaxRuleList
{
    #[ApiProperty(identifier: true)]
    public int $taxRuleId;

    public int $taxRulesGroupId;

    public int $countryId;

    public string $countryName;

    // 0 when the tax rule applies to the whole country (no state scope)
    public int $stateId;

    public string $stateName;

    public string $zipcode;

    public int $behavior;

    public ?DecimalNumber $rate = null;

    public ?string $taxName = null;

    public string $description;

    public function setRate(?string $rate): self
    {
        $this->rate = $rate !== null ? new DecimalNumber($rate) : null;

        return $this;
    }
}
