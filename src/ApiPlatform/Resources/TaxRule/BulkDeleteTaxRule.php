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
use ApiPlatform\OpenApi\Model\Operation as OpenApiOperation;
use PrestaShop\PrestaShop\Core\Domain\TaxRulesGroup\Exception\TaxRulesGroupConstraintException;
use PrestaShop\PrestaShop\Core\Domain\TaxRulesGroup\Exception\TaxRulesGroupNotFoundException;
use PrestaShop\PrestaShop\Core\Domain\TaxRulesGroup\TaxRule\Command\BulkDeleteTaxRuleCommand;
use PrestaShop\PrestaShop\Core\Domain\TaxRulesGroup\TaxRule\Exception\CannotBulkDeleteTaxRuleException;
use PrestaShop\PrestaShop\Core\Domain\TaxRulesGroup\TaxRule\Exception\TaxRuleConstraintException;
use PrestaShopBundle\ApiPlatform\Metadata\CQRSDelete;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(
    operations: [
        new CQRSDelete(
            uriTemplate: '/tax-rules/bulk-delete',
            CQRSCommand: BulkDeleteTaxRuleCommand::class,
            scopes: ['tax_rule_write'],
            allowEmptyBody: false,
            experimentalOperation: true,
            openapi: new OpenApiOperation(
                summary: 'Delete several tax rules of a tax rules group.',
                description: 'Deletes the listed tax rules. The group is historized first when orders use it, like '
                    . 'the back office does. An unknown tax rules group answers 404 and nothing is deleted. A tax '
                    . 'rule that cannot be deleted (unknown id) does not stop the others: they are deleted, and the '
                    . 'response is a 422 for the whole request (the core bulk command does not report the failures '
                    . 'one by one). The core does not check that the tax rules belong to the given group: a tax rule '
                    . 'of another group is deleted too.',
            ),
        ),
    ],
    exceptionToStatus: [
        // The handler collects every TaxRuleException (TaxRuleNotFoundException included) and throws
        // CannotBulkDeleteTaxRuleException once the other tax rules are deleted
        CannotBulkDeleteTaxRuleException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        TaxRuleConstraintException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
        TaxRulesGroupNotFoundException::class => Response::HTTP_NOT_FOUND,
        TaxRulesGroupConstraintException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
    ],
)]
class BulkDeleteTaxRule
{
    #[Assert\NotNull]
    #[Assert\Positive]
    public int $taxRulesGroupId;

    /**
     * @var int[]
     */
    #[ApiProperty(openapiContext: ['example' => [1, 3]])]
    #[Assert\NotBlank]
    #[Assert\All([new Assert\Type('integer'), new Assert\Positive()])]
    public array $taxRuleIds;
}
