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

use ApiPlatform\Metadata\ApiResource;
use PrestaShop\PrestaShop\Core\Domain\TaxRulesGroup\Exception\TaxRulesGroupNotFoundException;
use PrestaShop\PrestaShop\Core\Domain\TaxRulesGroup\TaxRule\Query\GetTaxRuleList;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSPaginate;
use Symfony\Component\HttpFoundation\Response;

#[ApiResource(
    operations: [
        new CQRSPaginate(
            uriTemplate: '/tax-rules-groups/{taxRulesGroupId}/tax-rules',
            requirements: ['taxRulesGroupId' => '\d+'],
            CQRSQuery: GetTaxRuleList::class,
            scopes: [
                'tax_rules_group_read',
            ],
            CQRSQueryMapping: [
                '[_context][langId]' => '[languageId]',
            ],
            itemsField: 'taxRules',
            countField: 'totalCount',
            experimentalOperation: true,
        ),
    ],
    exceptionToStatus: [
        TaxRulesGroupNotFoundException::class => Response::HTTP_NOT_FOUND,
    ],
)]
class TaxRuleList
{
    public int $taxRulesGroupId;

    public int $taxRuleId;

    public string $countryName;

    public string $stateName;

    public string $zipcode;

    public int $behavior;

    public string $taxName;

    public string $taxRate;

    public string $description;
}
