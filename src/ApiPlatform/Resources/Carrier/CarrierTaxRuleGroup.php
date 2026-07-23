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

namespace PrestaShop\Module\APIResources\ApiPlatform\Resources\Carrier;

use ApiPlatform\Metadata\ApiResource;
use PrestaShop\PrestaShop\Core\Domain\Carrier\Command\SetCarrierTaxRuleGroupCommand;
use PrestaShop\PrestaShop\Core\Domain\Carrier\Exception\CarrierConstraintException;
use PrestaShop\PrestaShop\Core\Domain\Carrier\Exception\CarrierNotFoundException;
use PrestaShop\PrestaShop\Core\Domain\TaxRulesGroup\Exception\TaxRulesGroupNotFoundException;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSPartialUpdate;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    operations: [
        new CQRSPartialUpdate(
            uriTemplate: '/carriers/{carrierId}/tax-rule-group',
            requirements: ['carrierId' => '\d+'],
            output: false,
            status: Response::HTTP_NO_CONTENT,
            CQRSCommand: SetCarrierTaxRuleGroupCommand::class,
            scopes: ['carrier_write'],
            CQRSCommandMapping: self::COMMAND_MAPPING,
        ),
    ],
    exceptionToStatus: [
        CarrierNotFoundException::class => Response::HTTP_NOT_FOUND,
        CarrierConstraintException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        TaxRulesGroupNotFoundException::class => Response::HTTP_NOT_FOUND,
    ],
)]
class CarrierTaxRuleGroup
{
    public int $carrierId;

    #[Assert\NotNull]
    public int $taxRuleGroupId;

    public const COMMAND_MAPPING = [
        '[_context][shopConstraint]' => '[shopConstraint]',
        '[taxRuleGroupId]' => '[carrierTaxRuleGroupId]',
    ];
}
