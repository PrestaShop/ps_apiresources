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
use PrestaShop\PrestaShop\Core\Domain\TaxRulesGroup\TaxRule\Command\AddTaxRuleCommand;
use PrestaShop\PrestaShop\Core\Domain\TaxRulesGroup\TaxRule\Command\DeleteTaxRuleCommand;
use PrestaShop\PrestaShop\Core\Domain\TaxRulesGroup\TaxRule\Command\EditTaxRuleCommand;
use PrestaShop\PrestaShop\Core\Domain\TaxRulesGroup\TaxRule\Exception\CannotAddTaxRuleException;
use PrestaShop\PrestaShop\Core\Domain\TaxRulesGroup\TaxRule\Exception\CannotEditTaxRuleException;
use PrestaShop\PrestaShop\Core\Domain\TaxRulesGroup\TaxRule\Exception\TaxRuleConstraintException;
use PrestaShop\PrestaShop\Core\Domain\TaxRulesGroup\TaxRule\Exception\TaxRuleNotFoundException;
use PrestaShop\PrestaShop\Core\Domain\TaxRulesGroup\TaxRule\Query\GetTaxRuleForEditing;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSCreate;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSDelete;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSGet;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSPartialUpdate;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    operations: [
        new CQRSCreate(
            uriTemplate: '/tax-rules-groups/{taxRulesGroupId}/tax-rules',
            requirements: ['taxRulesGroupId' => '\d+'],
            CQRSCommand: AddTaxRuleCommand::class,
            scopes: ['tax_rules_group_write'],
            CQRSCommandMapping: self::COMMAND_MAPPING,
        ),
        new CQRSGet(
            uriTemplate: '/tax-rules-groups/{taxRulesGroupId}/tax-rules/{taxRuleId}',
            requirements: ['taxRulesGroupId' => '\d+', 'taxRuleId' => '\d+'],
            CQRSQuery: GetTaxRuleForEditing::class,
            scopes: ['tax_rules_group_read'],
            CQRSQueryMapping: self::QUERY_MAPPING,
        ),
        new CQRSPartialUpdate(
            uriTemplate: '/tax-rules-groups/{taxRulesGroupId}/tax-rules/{taxRuleId}',
            requirements: ['taxRulesGroupId' => '\d+', 'taxRuleId' => '\d+'],
            read: false,
            CQRSCommand: EditTaxRuleCommand::class,
            scopes: ['tax_rules_group_write'],
            CQRSCommandMapping: self::COMMAND_MAPPING,
        ),
        new CQRSDelete(
            uriTemplate: '/tax-rules-groups/{taxRulesGroupId}/tax-rules/{taxRuleId}',
            requirements: ['taxRulesGroupId' => '\d+', 'taxRuleId' => '\d+'],
            CQRSCommand: DeleteTaxRuleCommand::class,
            scopes: ['tax_rules_group_write'],
        ),
    ],
    exceptionToStatus: [
        TaxRuleNotFoundException::class => Response::HTTP_NOT_FOUND,
        TaxRuleConstraintException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        CannotAddTaxRuleException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        CannotEditTaxRuleException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
    ],
)]
class TaxRule
{
    public int $taxRulesGroupId;

    #[ApiProperty(identifier: true)]
    public int $taxRuleId;

    #[Assert\NotBlank(groups: ['Create'])]
    public int $countryId;

    public ?int $stateId = null;

    #[Assert\NotBlank(groups: ['Create'])]
    public int $taxId;

    public ?int $behavior = null;

    public ?string $zipCode = null;

    public ?string $description = null;

    public const COMMAND_MAPPING = [
        '[countryId]' => '[countryId]',
        '[stateId]' => '[stateId]',
        '[taxId]' => '[taxId]',
        '[behavior]' => '[behavior]',
        '[zipCode]' => '[zipCode]',
        '[description]' => '[description]',
    ];

    public const QUERY_MAPPING = [
        '[zipcodeFrom]' => '[zipCode]',
    ];
}
