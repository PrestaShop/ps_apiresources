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
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use PrestaShop\Decimal\DecimalNumber;
use PrestaShop\PrestaShop\Core\Domain\TaxRulesGroup\TaxRule\Query\GetTaxRuleList;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSPaginate;

/**
 * Lists the same rules as GET /tax-rules?filters[taxRulesGroupId]={taxRulesGroupId} (TaxRule\TaxRuleList), but
 * built on GetTaxRuleList instead of the grid, so the items differ: no countryId and stateId, and a rule without
 * tax has a rate of 0 and an empty taxName where that list returns null.
 */
#[ApiResource(
    operations: [
        new CQRSPaginate(
            uriTemplate: '/tax-rules-groups/{taxRulesGroupId}/tax-rules',
            requirements: ['taxRulesGroupId' => '\d+'],
            openapi: new OpenApiOperation(
                summary: 'List the tax rules of a tax rules group.',
                description: 'Lists the tax rules of the tax rules group, sorted by taxRuleId. GET '
                    . '/tax-rules?filters[taxRulesGroupId]={taxRulesGroupId} lists the same rules with countryId and '
                    . 'stateId, which this endpoint does not return, and with a null rate and taxName for a rule '
                    . 'without tax, where this endpoint returns 0 and an empty string. Only limit and offset are '
                    . 'supported: orderBy, sortOrder and filters are echoed in the '
                    . 'response but ignored, use GET /tax-rules to filter or sort. An unknown tax rules group answers '
                    . 'an empty list, not a 404. '
                    . 'countryName and taxName are in the language of the context; countryName, stateName and '
                    . 'zipcode are "--" when the rule applies to every country, state or zip code. behavior is 0 '
                    . 'for this tax only, 1 to combine the taxes, 2 to apply them one after another. Requires '
                    . 'PrestaShop 9.2.0.',
            ),
            CQRSQuery: GetTaxRuleList::class,
            scopes: [
                'tax_rules_group_read',
            ],
            CQRSQueryMapping: [
                '[_context][langId]' => '[languageId]',
            ],
            ApiResourceMapping: [
                '[taxRate]' => '[rate]',
            ],
            itemsField: 'taxRules',
            countField: 'totalCount',
            extraProperties: [
                'minVersion' => '9.2.0',
            ],
        ),
    ],
)]
class TaxRulesGroupTaxRuleList
{
    public int $taxRulesGroupId;

    public int $taxRuleId;

    public string $countryName;

    public string $stateName;

    public string $zipcode;

    #[ApiProperty(openapiContext: ['enum' => [\TaxCalculator::ONE_TAX_ONLY_METHOD, \TaxCalculator::COMBINE_METHOD, \TaxCalculator::ONE_AFTER_ANOTHER_METHOD]])]
    public int $behavior;

    public string $taxName;

    public DecimalNumber $rate;

    public string $description;
}
