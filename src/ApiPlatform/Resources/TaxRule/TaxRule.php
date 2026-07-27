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
use PrestaShop\PrestaShop\Core\Domain\TaxRulesGroup\Exception\TaxRulesGroupNotFoundException;
use PrestaShop\PrestaShop\Core\Domain\TaxRulesGroup\TaxRule\Command\AddTaxRuleCommand;
use PrestaShop\PrestaShop\Core\Domain\TaxRulesGroup\TaxRule\Command\DeleteTaxRuleCommand;
use PrestaShop\PrestaShop\Core\Domain\TaxRulesGroup\TaxRule\Exception\CannotAddTaxRuleException;
use PrestaShop\PrestaShop\Core\Domain\TaxRulesGroup\TaxRule\Exception\CannotDeleteTaxRuleException;
use PrestaShop\PrestaShop\Core\Domain\TaxRulesGroup\TaxRule\Exception\TaxRuleConstraintException;
use PrestaShop\PrestaShop\Core\Domain\TaxRulesGroup\TaxRule\Exception\TaxRuleNotFoundException;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSCreate;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSDelete;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    operations: [
        new CQRSCreate(
            uriTemplate: '/tax-rules',
            validationContext: ['groups' => ['Default', 'Create']],
            CQRSCommand: AddTaxRuleCommand::class,
            scopes: ['tax_rule_write'],
            CQRSCommandMapping: self::COMMAND_MAPPING,
            experimentalOperation: true,
        ),
        new CQRSDelete(
            uriTemplate: '/tax-rules/{taxRuleId}',
            requirements: ['taxRuleId' => '\d+'],
            CQRSCommand: DeleteTaxRuleCommand::class,
            scopes: ['tax_rule_write'],
            experimentalOperation: true,
        ),
    ],
    normalizationContext: ['skip_null_values' => false],
    exceptionToStatus: [
        TaxRuleNotFoundException::class => Response::HTTP_NOT_FOUND,
        TaxRulesGroupNotFoundException::class => Response::HTTP_NOT_FOUND,
        TaxRuleConstraintException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        CannotAddTaxRuleException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        CannotDeleteTaxRuleException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
    ],
)]
class TaxRule
{
    // Null when the country/state pair already had a unique tax rule and the Core silently created nothing
    #[ApiProperty(identifier: true)]
    public ?int $taxRuleId = null;

    #[Assert\GreaterThan(0, groups: ['Create'])]
    public int $taxRulesGroupId;

    // The Core command also accepts 0 to mean "all active countries", but this endpoint always creates
    // exactly one tax rule, so fan-out is rejected here rather than exposing a variable-size response
    #[Assert\GreaterThan(0, groups: ['Create'])]
    public int $countryId;

    #[Assert\Count(max: 1, groups: ['Create'])]
    public array $stateIds = [0];

    // 0 means no tax is associated to this rule (see testListTaxRuleWithoutTax)
    #[Assert\GreaterThanOrEqual(0, groups: ['Create'])]
    public int $taxId;

    #[Assert\Range(min: 0, max: 2, groups: ['Create'])]
    public int $behavior = 0;

    public string $zipCode = '';

    public string $description = '';

    public const COMMAND_MAPPING = [
        '[taxRuleIds][0]' => '[taxRuleId]',
    ];
}
